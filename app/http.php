<?php
declare(strict_types=1);

namespace Pathway;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/evidence/agent.php';
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');

$screen = 'error';
$message = '';
$user = null;
$flash = null;
try {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $port = (string) ($_SERVER['SERVER_PORT'] ?? '');
    if (!in_array($host, ['127.0.0.1:' . $port, 'localhost:' . $port], true) ||
        !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        throw new HttpError(421, 'Open this application through its localhost address.');
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strlen($uri) > 2048) {
        throw new HttpError(414, 'Request address is too long.');
    }
    $path = parse_url($uri, PHP_URL_PATH);
    $routes = ['/' => ['GET'], '/login' => ['GET', 'POST'], '/register' => ['GET', 'POST'],
        '/dashboard' => ['GET'], '/assess' => ['GET', 'POST'], '/logout' => ['POST'],
        '/assets/pathway.css' => ['GET']];
    if (!is_string($path) || !isset($routes[$path])) {
        throw new HttpError(404, 'This route is not available in the local student slice.');
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, $routes[$path], true)) {
        header('Allow: ' . implode(', ', $routes[$path]));
        throw new HttpError(405, 'Method not allowed.');
    }
    if ($path === '/assets/pathway.css') {
        header('Content-Type: text/css; charset=UTF-8');
        readfile(ROOT . '/public/assets/pathway.css');
        exit;
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
        throw new HttpError(413, 'The submitted form is too large.');
    }
    if ($method === 'POST' && strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded') {
        throw new HttpError(415, 'Submit the HTML form to continue.');
    }
    $pdo = database();
    start_session();
    if ($path === '/') {
        header('Location: ' . (isset($_SESSION['user_id']) ? '/dashboard' : '/login'), true, 303);
        exit;
    }
    if ($method === 'POST') {
        verify_csrf($_POST);
        if ($path === '/logout') {
            current_student($pdo);
            $_SESSION = [];
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Strict']);
            session_destroy();
            header('Location: /login', true, 303);
            exit;
        }
        throttle($pdo, $path);
        if ($path === '/assess') {
            $user = current_student($pdo);
            $repo = field($_POST, 'repository', 180);
            $role = field($_POST, 'target_role', 20);
            $assessmentId = null;
            try {
                $assessmentId = Evidence\queue($pdo, (int) $user['id'], $repo, $role);
                Evidence\run($pdo, $assessmentId);
                $_SESSION['flash'] = 'Assessment completed. View your results below.';
            } catch (Evidence\EvidenceError $error) {
                $friendly = match ($error->reason) {
                    'invalid_repository' => 'Enter a valid public GitHub repository (owner/name or full URL).',
                    'invalid_role' => 'Choose frontend, backend or fullstack.',
                    'assessment_already_active' => 'You already have an assessment in progress. Wait for it to complete.',
                    'public_repository_unavailable' => 'That repository was not found or is not public.',
                    'empty_repository' => 'That repository has no commits.',
                    'rate_limited' => 'GitHub rate limit reached. Please wait a few minutes and try again.',
                    'network_unavailable' => 'Could not reach GitHub. Check your connection and try again.',
                    'request_budget_exceeded' => 'Assessment budget exhausted. Try again later.',
                    'deadline_exceeded' => 'The assessment timed out. Try again with a smaller repository.',
                    'tree_limit_exceeded' => 'That repository has too many files to assess.',
                    'blob_integrity_mismatch', 'unsupported_file_encoding' => 'Repository contents could not be verified safely.',
                    default => 'Assessment could not be completed. Please check the repository and try again.',
                };
                $_SESSION['flash_error'] = $friendly;
            } catch (\Throwable $error) {
                error_log('pathway.assessment_failed: ' . get_class($error));
                if ($assessmentId !== null) {
                    Evidence\fail($pdo, $assessmentId, 'internal_error');
                }
                $_SESSION['flash_error'] = 'Assessment could not be completed due to an internal error. Please try again later.';
            }
            header('Location: /dashboard', true, 303);
            exit;
        }
        if ($path === '/register') {
            register_student($pdo, $_POST);
            $_SESSION['flash'] = 'Your local account is ready. Sign in to continue.';
            header('Location: /login', true, 303);
            exit;
        }
        $user = authenticate($pdo, $_POST);
        if (!$user) {
            throw new HttpError(401, 'Email or password is incorrect.');
        }
        if ($user['role'] !== 'student') {
            throw new HttpError(403, 'Only the student workflow is available in this local slice.');
        }
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Session rotation failed.');
        }
        $_SESSION = ['user_id' => (int) $user['id'], 'role' => 'student', 'last_seen' => time()];
        csrf_token();
        header('Location: /dashboard', true, 303);
        exit;
    }
    if ($path === '/dashboard') {
        $user = current_student($pdo);
        $assessments = Evidence\history($pdo, (int) $user['id']);
        $screen = 'dashboard';
    } elseif ($path === '/assess') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $assessmentId = (int) ($query['id'] ?? 0);
        if ($assessmentId < 1) {
            throw new HttpError(400, 'Assessment ID is required.');
        }
        $assessment = Evidence\detail($pdo, $assessmentId, (int) $user['id']);
        if (!$assessment) {
            throw new HttpError(404, 'Assessment not found or access denied.');
        }
        $screen = 'assessment';
    } else {
        $screen = $path === '/register' ? 'register' : 'login';
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
    }
} catch (HttpError $error) {
    http_response_code($error->status);
    if ($error->status === 429) {
        header('Retry-After: 300');
    }
    $message = $error->getMessage();
    $screen = 'error';
} catch (\Throwable $error) {
    http_response_code(503);
    error_log('pathway.request_failed');
    $message = 'Local storage is unavailable or incompatible. Check initialization and the server terminal.';
    $screen = 'error';
}
header('Content-Type: text/html; charset=UTF-8');
require __DIR__ . '/views/page.php';
