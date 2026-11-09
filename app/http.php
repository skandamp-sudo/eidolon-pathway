<?php
declare(strict_types=1);

namespace Pathway;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/evidence/agent.php';
require_once __DIR__ . '/employment/jobs.php';
require_once __DIR__ . '/organizations/organization.php';
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
    $routes = [
        '/' => ['GET'],
        '/login' => ['GET', 'POST'],
        '/register' => ['GET', 'POST'],
        '/dashboard' => ['GET'],
        '/assess' => ['GET', 'POST'],
        '/profile' => ['GET', 'POST'],
        '/jobs' => ['GET'],
        '/jobs/view' => ['GET'],
        '/jobs/apply' => ['POST'],
        '/applications' => ['GET'],
        '/resume' => ['GET', 'POST'],
        '/resume/preview' => ['GET'],
        '/company/login' => ['GET', 'POST'],
        '/company/dashboard' => ['GET'],
        '/company/jobs/new' => ['GET', 'POST'],
        '/company/jobs/edit' => ['GET', 'POST'],
        '/company/applicants' => ['GET'],
        '/company/applicants/status' => ['POST'],
        '/college/login' => ['GET', 'POST'],
        '/college/dashboard' => ['GET'],
        '/college/students/view' => ['GET'],
        '/admin/login' => ['GET', 'POST'],
        '/admin/dashboard' => ['GET'],
        '/admin/organizations/approve' => ['POST'],
        '/admin/organizations/suspend' => ['POST'],
        '/logout' => ['POST'],
        '/assets/pathway.css' => ['GET'],
    ];
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
        $target = '/login';
        if (isset($_SESSION['user_id'])) {
            $role = $_SESSION['role'] ?? 'student';
            $target = match ($role) {
                'company' => '/company/dashboard',
                'college' => '/college/dashboard',
                'admin' => '/admin/dashboard',
                default => '/dashboard',
            };
        }
        header('Location: ' . $target, true, 303);
        exit;
    }
    if ($method === 'POST') {
        verify_csrf($_POST);
        if ($path === '/logout') {
            $id = $_SESSION['user_id'] ?? null;
            if (!is_int($id) || $id < 1) {
                throw new HttpError(401, 'Sign in to log out.');
            }
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
        if ($path === '/resume') {
            $user = current_student($pdo);
            $normalized = Employment\normalize_resume_input($_POST);
            try {
                $resumeId = Employment\save_resume($pdo, (int) $user['id'], $normalized);
                $_SESSION['flash'] = 'Resume saved successfully.';
                header('Location: /resume?id=' . $resumeId, true, 303);
                exit;
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'invalid_resume_title' => 'Resume title must be between 1 and 100 characters.',
                    'resume_summary_too_long' => 'Summary cannot exceed 2000 characters.',
                    'education_too_large' => 'Education entries payload exceeds storage limit.',
                    'experience_too_large' => 'Experience entries payload exceeds storage limit.',
                    'projects_too_large' => 'Projects entries payload exceeds storage limit.',
                    'skills_too_large' => 'Skills payload exceeds storage limit.',
                    'invalid_assessment_citation' => 'Selected assessment is invalid or not owned by you.',
                    'resume_not_found' => 'The resume draft could not be found.',
                    default => 'Resume draft could not be saved. Check input fields.',
                };
                $redirectTarget = !empty($normalized['id']) ? '/resume?id=' . $normalized['id'] : '/resume';
                header('Location: ' . $redirectTarget, true, 303);
                exit;
            }
        }
        if ($path === '/profile') {
            $user = current_student($pdo);
            try {
                Employment\save_profile($pdo, (int) $user['id'], $_POST);
                $_SESSION['flash'] = 'Profile updated successfully.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'college_too_long' => 'College name cannot exceed 200 characters.',
                    'skills_too_long' => 'Skills cannot exceed 500 characters.',
                    'bio_too_long' => 'Bio cannot exceed 2000 characters.',
                    'invalid_github_username' => 'Invalid GitHub username format.',
                    default => 'Profile could not be saved. Check input fields.',
                };
            }
            header('Location: /profile', true, 303);
            exit;
        }
        if ($path === '/jobs/apply') {
            $user = current_student($pdo);
            $jobId = (int) ($_POST['job_id'] ?? 0);
            $notes = trim((string) ($_POST['notes'] ?? ''));
            try {
                Employment\apply_for_job($pdo, (int) $user['id'], $jobId, $notes);
                $_SESSION['flash'] = 'Application submitted successfully! Track your status below.';
                header('Location: /applications', true, 303);
                exit;
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'duplicate_application' => 'You have already applied for this position.',
                    'job_not_available' => 'This job listing is no longer active.',
                    'notes_too_long' => 'Cover note cannot exceed 1000 characters.',
                    default => 'Application could not be submitted.',
                };
                header('Location: /jobs/view?id=' . $jobId, true, 303);
                exit;
            }
        }
        if ($path === '/company/login') {
            $user = authenticate($pdo, $_POST);
            if (!$user) {
                throw new HttpError(401, 'Email or password is incorrect.');
            }
            if ($user['role'] !== 'company') {
                throw new HttpError(403, 'A company staff account is required. Students should use the student portal.');
            }
            $membership = Employment\get_user_company_organization($pdo, (int) $user['id']);
            if (!$membership) {
                throw new HttpError(403, 'No company organization is associated with this account.');
            }
            if ($membership['org_status'] !== 'ACTIVE') {
                throw new HttpError(403, 'Your company is ' . strtolower($membership['org_status']) . '. Awaiting platform administrator approval.');
            }
            if (!session_regenerate_id(true)) {
                throw new \RuntimeException('Session rotation failed.');
            }
            $_SESSION = ['user_id' => (int) $user['id'], 'role' => 'company', 'org_id' => (int) $membership['org_id'], 'last_seen' => time()];
            csrf_token();
            header('Location: /company/dashboard', true, 303);
            exit;
        }
        if ($path === '/college/login') {
            $user = authenticate($pdo, $_POST);
            if (!$user) {
                throw new HttpError(401, 'Email or password is incorrect.');
            }
            if ($user['role'] !== 'college') {
                throw new HttpError(403, 'A college placement officer account is required. Students should use the student portal.');
            }
            $membership = Employment\get_user_college_organization($pdo, (int) $user['id']);
            if (!$membership) {
                throw new HttpError(403, 'No college organization is associated with this account.');
            }
            if ($membership['org_status'] !== 'ACTIVE') {
                throw new HttpError(403, 'Your college is ' . strtolower($membership['org_status']) . '. Awaiting platform administrator approval.');
            }
            if (!session_regenerate_id(true)) {
                throw new \RuntimeException('Session rotation failed.');
            }
            $_SESSION = ['user_id' => (int) $user['id'], 'role' => 'college', 'org_id' => (int) $membership['org_id'], 'last_seen' => time()];
            csrf_token();
            header('Location: /college/dashboard', true, 303);
            exit;
        }
        if ($path === '/admin/login') {
            $user = authenticate($pdo, $_POST);
            if (!$user) {
                throw new HttpError(401, 'Email or password is incorrect.');
            }
            if ($user['role'] !== 'admin') {
                throw new HttpError(403, 'Platform administrator privileges are required.');
            }
            if (!session_regenerate_id(true)) {
                throw new \RuntimeException('Session rotation failed.');
            }
            $_SESSION = ['user_id' => (int) $user['id'], 'role' => 'admin', 'last_seen' => time()];
            csrf_token();
            header('Location: /admin/dashboard', true, 303);
            exit;
        }
        if ($path === '/company/jobs/new') {
            $staff = Employment\current_company_staff($pdo);
            try {
                $jobId = Employment\create_job($pdo, (int) $staff['user']['id'], (int) $staff['org']['org_id'], $_POST);
                $_SESSION['flash'] = 'Job listing published successfully.';
                header('Location: /company/dashboard', true, 303);
                exit;
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'invalid_job_title' => 'Job title must be between 3 and 140 characters.',
                    'invalid_job_description' => 'Job description must be between 10 and 5000 characters.',
                    'invalid_job_skills' => 'Required skills must be provided (up to 500 characters).',
                    'invalid_role_category' => 'Please select a valid role category.',
                    'invalid_job_location' => 'Location must be between 2 and 100 characters.',
                    default => 'Job listing could not be created. Check input fields.',
                };
                header('Location: /company/jobs/new', true, 303);
                exit;
            }
        }
        if ($path === '/company/jobs/edit') {
            $staff = Employment\current_company_staff($pdo);
            $jobId = (int) ($_POST['id'] ?? 0);
            if ($jobId < 1) {
                throw new HttpError(400, 'Job ID is required.');
            }
            try {
                Employment\update_job($pdo, (int) $staff['user']['id'], (int) $staff['org']['org_id'], $jobId, $_POST);
                $_SESSION['flash'] = 'Job listing updated successfully.';
                header('Location: /company/dashboard', true, 303);
                exit;
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'job_not_found' => 'Job listing not found or access denied.',
                    'invalid_job_title' => 'Job title must be between 3 and 140 characters.',
                    'invalid_job_description' => 'Job description must be between 10 and 5000 characters.',
                    'invalid_job_skills' => 'Required skills must be provided (up to 500 characters).',
                    'invalid_role_category' => 'Please select a valid role category.',
                    'invalid_job_location' => 'Location must be between 2 and 100 characters.',
                    default => 'Job listing could not be updated. Check input fields.',
                };
                header('Location: /company/jobs/edit?id=' . $jobId, true, 303);
                exit;
            }
        }
        if ($path === '/company/applicants/status') {
            $staff = Employment\current_company_staff($pdo);
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            $status = trim((string) ($_POST['status'] ?? ''));
            if ($applicationId < 1) {
                throw new HttpError(400, 'Application ID is required.');
            }
            try {
                Employment\update_application_status($pdo, (int) $staff['user']['id'], (int) $staff['org']['org_id'], $applicationId, $status);
                $_SESSION['flash'] = 'Application status updated to ' . ucfirst($status) . '.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'application_not_found' => 'Application not found or access denied.',
                    'invalid_application_status' => 'Invalid status selection.',
                    default => 'Application status could not be updated.',
                };
            }
            $jobIdParam = !empty($_POST['job_id']) ? '?job_id=' . (int) $_POST['job_id'] : '';
            header('Location: /company/applicants' . $jobIdParam, true, 303);
            exit;
        }
        if ($path === '/admin/organizations/approve') {
            $admin = Organizations\current_platform_admin($pdo);
            $orgId = (int) ($_POST['organization_id'] ?? 0);
            if ($orgId < 1) {
                throw new HttpError(400, 'Organization ID is required.');
            }
            try {
                Organizations\approve_organization($pdo, (int) $admin['id'], $orgId);
                $_SESSION['flash'] = 'Organization approved and activated successfully.';
            } catch (Organizations\OrganizationError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'organization_not_found' => 'Organization not found.',
                    'organization_not_pending' => 'Organization is not in pending status.',
                    default => 'Could not approve organization.',
                };
            }
            header('Location: /admin/dashboard', true, 303);
            exit;
        }
        if ($path === '/admin/organizations/suspend') {
            $admin = Organizations\current_platform_admin($pdo);
            $orgId = (int) ($_POST['organization_id'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? 'Administrative action'));
            if ($orgId < 1) {
                throw new HttpError(400, 'Organization ID is required.');
            }
            try {
                Organizations\suspend_organization($pdo, (int) $admin['id'], $orgId, $reason);
                $_SESSION['flash'] = 'Organization suspended.';
            } catch (Organizations\OrganizationError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'organization_not_found' => 'Organization not found.',
                    'organization_already_suspended' => 'Organization is already suspended.',
                    default => 'Could not suspend organization.',
                };
            }
            header('Location: /admin/dashboard', true, 303);
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
    } elseif ($path === '/profile') {
        $user = current_student($pdo);
        $profile = Employment\get_profile($pdo, (int) $user['id']);
        $approvedColleges = Employment\list_approved_colleges($pdo);
        $screen = 'profile';
    } elseif ($path === '/jobs') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $category = isset($query['category']) ? trim((string) $query['category']) : null;
        $search = isset($query['q']) ? trim((string) $query['q']) : null;
        $jobs = Employment\list_active_jobs($pdo, $category, $search);
        $selectedCategory = $category ?? 'all';
        $searchQuery = $search ?? '';
        $screen = 'jobs';
    } elseif ($path === '/jobs/view') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $jobId = (int) ($query['id'] ?? 0);
        if ($jobId < 1) {
            throw new HttpError(400, 'Job ID is required.');
        }
        $job = Employment\get_job($pdo, $jobId);
        if (!$job || $job['status'] !== 'active' || $job['company_status'] !== 'ACTIVE') {
            throw new HttpError(404, 'Job not found or no longer active.');
        }
        $existingApplication = Employment\get_student_application_for_job($pdo, (int) $user['id'], $jobId);
        $screen = 'job_detail';
    } elseif ($path === '/applications') {
        $user = current_student($pdo);
        $applications = Employment\list_student_applications($pdo, (int) $user['id']);
        $screen = 'applications';
    } elseif ($path === '/resume') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $resumeId = (int) ($query['id'] ?? 0);
        $resume = null;
        if ($resumeId > 0) {
            $resume = Employment\get_resume($pdo, $resumeId, (int) $user['id']);
            if (!$resume) {
                throw new HttpError(404, 'Resume not found or access denied.');
            }
        } else {
            $allResumes = Employment\list_resumes($pdo, (int) $user['id']);
            if (!empty($allResumes)) {
                $resume = Employment\get_resume($pdo, (int) $allResumes[0]['id'], (int) $user['id']);
            }
        }
        $completedAssessments = array_values(array_filter(
            Evidence\history($pdo, (int) $user['id']),
            fn($a) => ($a['state'] ?? '') === 'completed'
        ));
        $allResumes = Employment\list_resumes($pdo, (int) $user['id']);
        $profile = Employment\get_profile($pdo, (int) $user['id']);
        $screen = 'resume';
    } elseif ($path === '/resume/preview') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $resumeId = (int) ($query['id'] ?? 0);
        if ($resumeId < 1) {
            throw new HttpError(400, 'Resume ID is required.');
        }
        $resume = Employment\get_resume($pdo, $resumeId, (int) $user['id']);
        if (!$resume) {
            throw new HttpError(404, 'Resume not found or access denied.');
        }
        $screen = 'resume_preview';
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
    } elseif ($path === '/company/dashboard') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $jobs = Employment\list_company_jobs($pdo, (int) $user['id'], (int) $org['org_id']);
        $screen = 'company_dashboard';
    } elseif ($path === '/company/jobs/new') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $job = null;
        $screen = 'company_job_form';
    } elseif ($path === '/company/jobs/edit') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $jobId = (int) ($query['id'] ?? 0);
        if ($jobId < 1) {
            throw new HttpError(400, 'Job ID is required.');
        }
        $job = Employment\get_job($pdo, $jobId);
        if (!$job || (int) $job['organization_id'] !== (int) $org['org_id']) {
            throw new HttpError(404, 'Job listing not found or access denied.');
        }
        $screen = 'company_job_form';
    } elseif ($path === '/company/applicants') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $jobId = !empty($query['job_id']) ? (int) $query['job_id'] : null;
        if ($jobId !== null) {
            $checkJob = Employment\get_job($pdo, $jobId);
            if (!$checkJob || (int) $checkJob['organization_id'] !== (int) $org['org_id']) {
                throw new HttpError(404, 'Job not found.');
            }
        }
        $applicants = Employment\list_company_applications($pdo, (int) $user['id'], (int) $org['org_id'], $jobId);
        $jobs = Employment\list_company_jobs($pdo, (int) $user['id'], (int) $org['org_id']);
        $selectedJobId = $jobId;
        $screen = 'company_applicants';
    } elseif ($path === '/college/dashboard') {
        $staff = Employment\current_college_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $students = Employment\list_college_students($pdo, (int) $org['org_id'], (int) $user['id']);
        $screen = 'college_dashboard';
    } elseif ($path === '/college/students/view') {
        $staff = Employment\current_college_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $studentId = (int) ($query['id'] ?? 0);
        if ($studentId < 1) {
            throw new HttpError(400, 'Student ID is required.');
        }
        try {
            $placement = Employment\get_college_student_placement_details($pdo, (int) $org['org_id'], $studentId, (int) $user['id']);
        } catch (Employment\EmploymentError $e) {
            if ($e->statusCode === 403) {
                throw new HttpError(403, 'Student has not consented to share placement records with the college.');
            }
            throw new HttpError(404, 'Student not found in your college roster.');
        }
        $screen = 'college_student_detail';
    } elseif ($path === '/admin/dashboard') {
        $admin = Organizations\current_platform_admin($pdo);
        $user = $admin;
        $pendingOrgs = Organizations\list_organizations($pdo, 'PENDING');
        $activeOrgs = Organizations\list_organizations($pdo, 'ACTIVE');
        $suspendedOrgs = Organizations\list_organizations($pdo, 'SUSPENDED');
        $auditLogs = Organizations\list_audit_logs($pdo, 25);
        $screen = 'admin_dashboard';
    } else {
        $screen = match ($path) {
            '/register' => 'register',
            '/company/login' => 'company_login',
            '/college/login' => 'college_login',
            '/admin/login' => 'admin_login',
            default => 'login',
        };
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
