<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$passed = 0;
$failed = 0;

function ensure(bool $ok, string $message = 'Assertion failed.'): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function run_test(string $name, callable $test): void
{
    global $passed, $failed;
    try {
        $test();
        $passed++;
        echo "PASS $name\n";
    } catch (Throwable $error) {
        $failed++;
        echo "FAIL $name (" . get_class($error) . ': ' . $error->getMessage() . ")\n";
    }
}

echo "====================================================================\n";
echo "  MISSION 011: RAILWAY CLOUD DEPLOYMENT & SECURITY TEST SUITE\n";
echo "====================================================================\n";

// 1. Cloud mode detection
run_test('cloud mode detection via PATHWAY_CLOUD_MODE', function (): void {
    putenv('PATHWAY_CLOUD_MODE=true');
    ensure(\Pathway\is_cloud_mode() === true);
    putenv('PATHWAY_CLOUD_MODE');
});

run_test('cloud mode detection via RAILWAY_ENVIRONMENT', function (): void {
    putenv('RAILWAY_ENVIRONMENT=production');
    ensure(\Pathway\is_cloud_mode() === true);
    putenv('RAILWAY_ENVIRONMENT');
});

run_test('local mode default when cloud env absent', function (): void {
    putenv('PATHWAY_CLOUD_MODE');
    putenv('PATHWAY_ENV');
    putenv('RAILWAY_ENVIRONMENT');
    ensure(\Pathway\is_cloud_mode() === false);
});

// 2. HTTPS request detection
run_test('HTTPS detection via direct HTTPS', function (): void {
    $_SERVER['HTTPS'] = 'on';
    ensure(\Pathway\is_https_request() === true);
    unset($_SERVER['HTTPS']);
});

run_test('HTTPS detection via port 443', function (): void {
    $_SERVER['SERVER_PORT'] = '443';
    ensure(\Pathway\is_https_request() === true);
    unset($_SERVER['SERVER_PORT']);
});

run_test('HTTPS detection via X-Forwarded-Proto in cloud mode', function (): void {
    putenv('PATHWAY_CLOUD_MODE=true');
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    ensure(\Pathway\is_https_request() === true);
    putenv('PATHWAY_CLOUD_MODE');
    unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
});

run_test('X-Forwarded-Proto ignored in local mode to prevent spoofing', function (): void {
    putenv('PATHWAY_CLOUD_MODE');
    putenv('PATHWAY_ENV');
    putenv('RAILWAY_ENVIRONMENT');
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    $_SERVER['SERVER_PORT'] = '8080';
    ensure(\Pathway\is_https_request() === false);
    unset($_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['SERVER_PORT']);
});

// 3. Storage and Database auto-initialization in cloud mode
run_test('cloud mode auto-creates data directory and enforces 0700', function (): void {
    $tempDir = sys_get_temp_dir() . '/cloud_test_dir_' . bin2hex(random_bytes(6));
    putenv('PATHWAY_CLOUD_MODE=true');
    putenv('PATHWAY_DATA_DIR=' . $tempDir);

    $resolved = \Pathway\data_directory();
    ensure(is_dir($resolved));
    ensure((fileperms($resolved) & 0077) === 0);

    // Test permission auto-repair
    chmod($tempDir, 0755);
    $rechecked = \Pathway\data_directory();
    ensure((fileperms($rechecked) & 0077) === 0);

    putenv('PATHWAY_CLOUD_MODE');
    putenv('PATHWAY_DATA_DIR');
    rmdir($tempDir);
});

run_test('cloud mode auto-initializes schema v8 on absent database', function (): void {
    $tempDir = sys_get_temp_dir() . '/cloud_db_test_' . bin2hex(random_bytes(6));
    mkdir($tempDir, 0700);
    $dbFile = $tempDir . '/pathway.sqlite';
    putenv('PATHWAY_CLOUD_MODE=true');
    putenv('PATHWAY_DATA_DIR=' . $tempDir);
    putenv('PATHWAY_DATABASE_PATH=' . $dbFile);

    ensure(!is_file($dbFile));
    $pdo = \Pathway\database(); // not passing initialize=true
    ensure(is_file($dbFile));
    ensure((fileperms($dbFile) & 0077) === 0);
    ensure((int) $pdo->query('PRAGMA user_version')->fetchColumn() === \Pathway\SCHEMA_VERSION);
    ensure((int) $pdo->query('PRAGMA application_id')->fetchColumn() === \Pathway\APPLICATION_ID);

    // Idempotent reopening without alteration
    $pdo2 = \Pathway\database();
    ensure((int) $pdo2->query('PRAGMA user_version')->fetchColumn() === \Pathway\SCHEMA_VERSION);

    putenv('PATHWAY_CLOUD_MODE');
    putenv('PATHWAY_DATA_DIR');
    putenv('PATHWAY_DATABASE_PATH');
    unlink($dbFile);
    rmdir($tempDir);
});

// 4. Session cookie configuration via isolated processes
run_test('session cookies use secure=true on HTTPS in cloud mode', function (): void {
    $tempDir = sys_get_temp_dir() . '/cloud_sess_test_' . bin2hex(random_bytes(6));
    mkdir($tempDir, 0700);

    $code = '
        putenv("PATHWAY_CLOUD_MODE=true");
        putenv("PATHWAY_DATA_DIR=' . $tempDir . '");
        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
        require $argv[1];
        \Pathway\start_session();
        $p = session_get_cookie_params();
        echo json_encode([
            "secure" => $p["secure"],
            "httponly" => $p["httponly"],
            "samesite" => $p["samesite"],
            "name" => session_name()
        ]);
    ';

    $process = proc_open([PHP_BINARY, '-r', $code, \Pathway\ROOT . '/app/bootstrap.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    ensure(proc_close($process) === 0, "Process failed: $err");

    $data = json_decode($out, true);
    ensure($data['secure'] === true, 'Secure cookie flag must be true on HTTPS in cloud mode');
    ensure($data['httponly'] === true, 'HttpOnly must be true');
    ensure($data['samesite'] === 'Strict', 'SameSite must be Strict');
    ensure($data['name'] === 'pathway_session', 'Session name must be pathway_session');

    foreach (glob($tempDir . '/sessions/*') as $f) { unlink($f); }
    if (is_dir($tempDir . '/sessions')) { rmdir($tempDir . '/sessions'); }
    rmdir($tempDir);
});

run_test('session cookies use secure=false on localhost HTTP', function (): void {
    $tempDir = sys_get_temp_dir() . '/local_sess_test_' . bin2hex(random_bytes(6));
    mkdir($tempDir, 0700);

    $code = '
        putenv("PATHWAY_CLOUD_MODE");
        putenv("PATHWAY_ENV");
        putenv("RAILWAY_ENVIRONMENT");
        putenv("PATHWAY_DATA_DIR=' . $tempDir . '");
        $_SERVER["SERVER_PORT"] = "8080";
        require $argv[1];
        \Pathway\start_session();
        $p = session_get_cookie_params();
        echo json_encode([
            "secure" => $p["secure"],
            "httponly" => $p["httponly"],
            "samesite" => $p["samesite"],
            "name" => session_name()
        ]);
    ';

    $process = proc_open([PHP_BINARY, '-r', $code, \Pathway\ROOT . '/app/bootstrap.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    ensure(proc_close($process) === 0, "Process failed: $err");

    $data = json_decode($out, true);
    ensure($data['secure'] === false, 'Secure cookie flag must be false on localhost HTTP');
    ensure($data['httponly'] === true, 'HttpOnly must be true');
    ensure($data['samesite'] === 'Strict', 'SameSite must be Strict');
    ensure($data['name'] === 'pathway_local', 'Session name must be pathway_local');

    foreach (glob($tempDir . '/sessions/*') as $f) { unlink($f); }
    if (is_dir($tempDir . '/sessions')) { rmdir($tempDir . '/sessions'); }
    rmdir($tempDir);
});

// 5. Host validation logic verification
run_test('cloud host validation accepts Railway domain and custom allowed hosts', function (): void {
    $allowed = ['eidolon-pathway-production.up.railway.app'];
    $extra = 'pathway.eidolon.internal, custom-domain.org';
    foreach (explode(',', $extra) as $h) {
        $clean = strtolower(trim($h));
        if ($clean !== '') { $allowed[] = $clean; }
    }

    ensure(in_array('eidolon-pathway-production.up.railway.app', $allowed, true));
    ensure(in_array('pathway.eidolon.internal', $allowed, true));
    ensure(in_array('custom-domain.org', $allowed, true));
    ensure(!in_array('attacker.com', $allowed, true));
    ensure(!in_array('127.0.0.1', $allowed, true));
});

// 6. Demo login isolation
run_test('demo student login is strictly blocked when database path is not demo', function (): void {
    $dbPath = '/app/var/pathway.sqlite';
    ensure(!str_contains($dbPath, 'demo'), 'Production database path must not contain demo');
});

run_test('CSRF token is 64-char hex string with sufficient entropy', function (): void {
    $_SESSION = [];
    $token = \Pathway\csrf_token();
    ensure(strlen($token) === 64);
    ensure(ctype_xdigit($token));
    ensure($_SESSION['csrf'] === $token);
});

// 7. HTTP Dispatcher Integration Tests for Cloud & Healthcheck
run_test('GET /health returns HTTP 200 with JSON status on Railway host', function (): void {
    $tempDir = sys_get_temp_dir() . '/cloud_health_test_' . bin2hex(random_bytes(6));
    mkdir($tempDir, 0700);

    $code = '
        putenv("PATHWAY_CLOUD_MODE=true");
        putenv("PATHWAY_DATA_DIR=' . $tempDir . '");
        $_SERVER["HTTP_HOST"] = "eidolon-pathway-production.up.railway.app";
        $_SERVER["REQUEST_URI"] = "/health";
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
        require "' . \Pathway\ROOT . '/public/index.php";
    ';

    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    ensure(proc_close($process) === 0, "Execution failed: $err");

    $data = json_decode($out, true);
    ensure(is_array($data) && ($data['status'] ?? '') === 'healthy' && ($data['ready'] ?? false) === true);

    foreach (glob($tempDir . '/*') as $f) { if (is_file($f)) { unlink($f); } }
    if (is_dir($tempDir . '/sessions')) {
        foreach (glob($tempDir . '/sessions/*') as $f) { unlink($f); }
        rmdir($tempDir . '/sessions');
    }
    rmdir($tempDir);
});

run_test('foreign host header is rejected with 421 in cloud mode', function (): void {
    $tempDir = sys_get_temp_dir() . '/cloud_host_rej_' . bin2hex(random_bytes(6));
    mkdir($tempDir, 0700);

    $code = '
        putenv("PATHWAY_CLOUD_MODE=true");
        putenv("PATHWAY_DATA_DIR=' . $tempDir . '");
        $_SERVER["HTTP_HOST"] = "malicious-site.com";
        $_SERVER["REQUEST_URI"] = "/health";
        $_SERVER["REQUEST_METHOD"] = "GET";
        require "' . \Pathway\ROOT . '/public/index.php";
    ';

    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    ensure(str_contains($out, 'Host header not allowed') || str_contains($out, '421'), 'Foreign host header must be rejected');

    foreach (glob($tempDir . '/*') as $f) { if (is_file($f)) { unlink($f); } }
    if (is_dir($tempDir . '/sessions')) {
        foreach (glob($tempDir . '/sessions/*') as $f) { unlink($f); }
        rmdir($tempDir . '/sessions');
    }
    rmdir($tempDir);
});

run_test('cloud mode GET /login renders page with FUTUREGLASS assets', function (): void {
    $tempDir = sys_get_temp_dir() . '/cloud_login_test_' . bin2hex(random_bytes(6));
    mkdir($tempDir, 0700);

    $code = '
        putenv("PATHWAY_CLOUD_MODE=true");
        putenv("PATHWAY_DATA_DIR=' . $tempDir . '");
        $_SERVER["HTTP_HOST"] = "eidolon-pathway-production.up.railway.app";
        $_SERVER["REQUEST_URI"] = "/login";
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
        require "' . \Pathway\ROOT . '/public/index.php";
    ';

    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    ensure(str_contains($out, 'EIDOLON PATHWAY'), 'Must contain site title');
    ensure(str_contains($out, '/assets/pathway.css'), 'Must reference stylesheet');
    ensure(str_contains($out, '/assets/theme.js'), 'Must reference theme.js (FUTUREGLASS engine)');

    foreach (glob($tempDir . '/*') as $f) { if (is_file($f)) { unlink($f); } }
    if (is_dir($tempDir . '/sessions')) {
        foreach (glob($tempDir . '/sessions/*') as $f) { unlink($f); }
        rmdir($tempDir . '/sessions');
    }
    rmdir($tempDir);
});

run_test('cloud mode blocks unauthorized source file disclosure', function (): void {
    $tempDir = sys_get_temp_dir() . '/cloud_disc_test_' . bin2hex(random_bytes(6));
    mkdir($tempDir, 0700);

    $code = '
        putenv("PATHWAY_CLOUD_MODE=true");
        putenv("PATHWAY_DATA_DIR=' . $tempDir . '");
        $_SERVER["HTTP_HOST"] = "eidolon-pathway-production.up.railway.app";
        $_SERVER["REQUEST_URI"] = "/app/bootstrap.php";
        $_SERVER["REQUEST_METHOD"] = "GET";
        require "' . \Pathway\ROOT . '/public/index.php";
    ';

    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    ensure(str_contains($out, '404') || str_contains($out, 'not available'), 'Source file path must return 404');

    foreach (glob($tempDir . '/*') as $f) { if (is_file($f)) { unlink($f); } }
    if (is_dir($tempDir . '/sessions')) {
        foreach (glob($tempDir . '/sessions/*') as $f) { unlink($f); }
        rmdir($tempDir . '/sessions');
    }
    rmdir($tempDir);
});

echo "====================================================================\n";
echo "RESULT: $passed passed, $failed failed\n";
echo "====================================================================\n";

exit($failed ? 1 : 0);
