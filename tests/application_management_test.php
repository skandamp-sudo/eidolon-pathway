<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/application_management.php';
require __DIR__ . '/support/application_fixture.php';

$passed = 0;
$failed = 0;
function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function test_case($name, $callback)
{
    global $passed, $failed;
    try {
        $callback();
        $passed++;
        echo "PASS $name\n";
    } catch (Throwable $error) {
        $failed++;
        // Only test-authored messages; no request payloads, tokens or SQL output.
        echo "FAIL $name (" . get_class($error) . ")\n";
    }
}
function expect_status($status, $callback)
{
    try {
        $callback();
    } catch (ApplicationManagementError $error) {
        check($error->httpStatus === $status, 'Unexpected rejection status.');
        return;
    }
    throw new RuntimeException('Expected rejection.');
}

// The real config.php is never read, copied or loaded by this suite.
$temporary = sys_get_temp_dir() . '/pathway-app-tests-' . bin2hex(random_bytes(10));
if (!mkdir($temporary, 0700) || !mkdir($temporary . '/sessions', 0700)) {
    throw new RuntimeException('Cannot create isolated test directory.');
}
register_shutdown_function(function () use ($temporary) {
    // Remove only artifacts created beneath this unique test directory.
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($temporary);
});
foreach (['application_management.php', 'view_applicants.php', 'update_status.php', 'shortlist.php'] as $file) {
    if (!copy(dirname(__DIR__) . '/' . $file, $temporary . '/' . $file)) {
        throw new RuntimeException('Cannot copy endpoint fixture.');
    }
}
copy(__DIR__ . '/support/application_fixture.php', $temporary . '/application_fixture.php');
copy(__DIR__ . '/support/endpoint_runner.php', $temporary . '/endpoint_runner.php');
file_put_contents($temporary . '/config.php', '<?php
require __DIR__ . "/application_fixture.php";
if (!empty($GLOBALS["request"]["failure"])) {
    throw new RuntimeException("Fixture connection unavailable.");
}
$conn = new ApplicationFixture();
');

function endpoint($name, $session, $method, $post = [], $failure = false)
{
    global $temporary;
    $process = proc_open(
        [PHP_BINARY, '-n', $temporary . '/endpoint_runner.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start isolated endpoint.');
    }
    fwrite($pipes[0], json_encode(['endpoint' => $name, 'session' => $session, 'method' => $method, 'post' => $post, 'failure' => $failure]));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    check($exit === 0 && $errors === '', 'Endpoint emitted an error.');
    return json_decode($output, true, 32, JSON_THROW_ON_ERROR);
}

$session = ['user_id' => 1, 'role' => 'company'];
$token = application_csrf_token($session);
$post = ['application_id' => '100', 'status' => 'shortlisted', 'csrf_token' => $token];

foreach (['view_applicants.php', 'update_status.php', 'shortlist.php'] as $route) {
    $method = $route === 'view_applicants.php' ? 'GET' : 'POST';
    test_case("$route denies anonymous before database access", function () use ($route, $method, $post) {
        $result = endpoint($route, [], $method, $post);
        check($result['status'] === 401 && !$result['connected'], 'Anonymous request accepted.');
    });
    foreach (['student', 'admin'] as $role) {
        test_case("$route denies $role before database access", function () use ($route, $method, $post, $session, $role) {
            $result = endpoint($route, array_replace($session, ['role' => $role]), $method, $post);
            check($result['status'] === 403 && !$result['connected'], 'Wrong role accepted.');
        });
    }
    test_case("$route rejects wrong method before database access", function () use ($route, $method, $post, $session) {
        $result = endpoint($route, $session, $method === 'GET' ? 'POST' : 'GET', $post);
        check($result['status'] === 405 && !$result['connected'], 'Wrong method accepted.');
    });
    test_case("$route returns controlled database failure", function () use ($route, $method, $post, $session) {
        $result = endpoint($route, $session, $method, $post, true);
        check($result['status'] === 503 && !$result['connected'], 'Database failure reported success.');
        check($result['body'] === 'Application management unavailable. Please try again later.', 'Internal error exposed.');
    });
}

foreach (['update_status.php', 'shortlist.php'] as $route) {
    foreach (['missing' => null, 'invalid' => str_repeat('z', 64), 'array' => [], 'oversized' => str_repeat('x', 10000)] as $label => $csrf) {
        test_case("$route rejects $label CSRF", function () use ($route, $session, $post, $csrf) {
            $request = array_replace($post, ['csrf_token' => $csrf]);
            $result = endpoint($route, $session, 'POST', $request);
            check($result['status'] === 403 && !$result['connected'], 'CSRF accepted.');
        });
    }
    foreach ([null, '', '0', '-1', '01', ' 100', '100 ', '1e2', '1.0', 1.5, true, [], '2147483648', str_repeat('9', 10000), '100 OR 1=1', "100'; DROP TABLE applications;--"] as $index => $id) {
        test_case("$route rejects malformed ID case $index", function () use ($route, $session, $post, $id) {
            $result = endpoint($route, $session, 'POST', array_replace($post, ['application_id' => $id]));
            check($result['status'] === 400 && !$result['connected'], 'Malformed ID accepted.');
        });
    }
    foreach (['200', '999'] as $id) {
        test_case("$route denies foreign or missing application $id", function () use ($route, $session, $post, $id) {
            $result = endpoint($route, $session, 'POST', array_replace($post, ['application_id' => $id, 'company_id' => 2]));
            check($result['status'] === 404, 'Foreign application accepted.');
            check($result['statuses'] === [100 => 'applied', 200 => 'applied'], 'Records changed on rejection.');
        });
    }
    test_case("$route permits owner and redirects", function () use ($route, $session, $post) {
        $result = endpoint($route, $session, 'POST', $post);
        check($result['status'] === 302, 'Successful redirect missing.');
        check($result['statuses'] === [100 => 'shortlisted', 200 => 'applied'], 'Wrong records changed.');
    });
}

foreach ([null, [], '', 'accepted', "shortlisted', status='rejected"] as $index => $status) {
    test_case("update rejects invalid status case $index", function () use ($session, $post, $status) {
        $result = endpoint('update_status.php', $session, 'POST', array_replace($post, ['status' => $status]));
        check($result['status'] === 400 && !$result['connected'], 'Invalid status accepted.');
    });
}
test_case('listing contains only own applicants, escaped content and CSRF form', function () use ($session) {
    $result = endpoint('view_applicants.php', $session, 'GET');
    check($result['status'] === 200, 'Listing failed.');
    check(strpos($result['body'], 'Company A Job') !== false && strpos($result['body'], 'Company B Job') === false, 'Listing isolation failed.');
    check(strpos($result['body'], '<script>alert(1)</script>') === false && strpos($result['body'], '&lt;script&gt;') !== false, 'HTML not escaped.');
    check(strpos($result['body'], "name='csrf_token' value='" . $session['application_csrf_token'] . "'") !== false, 'CSRF form missing.');
    check(strpos($result['body'], "value='applied'") !== false, 'Canonical pending action missing.');
});
test_case('listing issues token accepted by update endpoint', function () use ($session, $post) {
    unset($session['application_csrf_token']);
    $listing = endpoint('view_applicants.php', $session, 'GET');
    $session['application_csrf_token'] = $listing['csrf_token'];
    $post['csrf_token'] = $listing['csrf_token'];
    $result = endpoint('update_status.php', $session, 'POST', $post);
    check($result['status'] === 302 && $result['statuses'][100] === 'shortlisted', 'Generated token rejected.');
});
test_case('company B lists and updates only its own application', function () use ($session, $post) {
    $session['user_id'] = 2;
    $listing = endpoint('view_applicants.php', $session, 'GET');
    check(strpos($listing['body'], 'Company B Job') !== false && strpos($listing['body'], 'Company A Job') === false, 'Company B listing failed.');
    $result = endpoint('update_status.php', $session, 'POST', array_replace($post, ['application_id' => '200']));
    check($result['status'] === 302 && $result['statuses'] === [100 => 'applied', 200 => 'shortlisted'], 'Company B update failed.');
});
test_case('POST identity and role cannot authenticate a request', function () use ($post) {
    $result = endpoint('update_status.php', [], 'POST', array_replace($post, ['user_id' => 1, 'role' => 'company']));
    check($result['status'] === 401 && !$result['connected'], 'POST identity trusted.');
});
test_case('token from a different session is rejected', function () use ($session, $post) {
    $other = [];
    $result = endpoint('update_status.php', $session, 'POST', array_replace($post, ['csrf_token' => application_csrf_token($other)]));
    check($result['status'] === 403 && !$result['connected'], 'Foreign token accepted.');
});
test_case('legacy pending request maps to schema applied', function () use ($session, $post) {
    $result = endpoint('update_status.php', $session, 'POST', array_replace($post, ['status' => 'pending']));
    check($result['status'] === 302 && $result['statuses'][100] === 'applied', 'Legacy alias failed.');
});
test_case('shortlist endpoint fixes target despite forged status', function () use ($session, $post) {
    $result = endpoint('shortlist.php', $session, 'POST', array_replace($post, ['status' => 'rejected']));
    check($result['status'] === 302 && $result['statuses'][100] === 'shortlisted', 'Fixed action changed.');
});

foreach (['applied', 'pending', 'shortlisted', 'rejected'] as $from) {
    foreach (['applied', 'shortlisted', 'rejected'] as $to) {
        test_case("permitted transition $from to $to", function () use ($from, $to) {
            $db = new ApplicationFixture();
            $statement = $db->pdo->prepare('UPDATE applications SET status = ? WHERE id = 100');
            $statement->execute([$from]);
            application_update_owned($db, 1, 100, $to);
            check($db->statuses() === [100 => $to, 200 => 'applied'], 'Transition failed.');
        });
    }
}
test_case('unknown persisted state rejected without mutation', function () {
    $db = new ApplicationFixture();
    $db->pdo->exec("UPDATE applications SET status = 'unknown' WHERE id = 100");
    expect_status(409, function () use ($db) { application_update_owned($db, 1, 100, 'shortlisted'); });
    check($db->statuses()[100] === 'unknown', 'Unknown state overwritten.');
});
foreach ([
    'status' => "UPDATE applications SET status = 'rejected' WHERE id = 100",
    'ownership' => 'UPDATE jobs SET company_id = 2 WHERE id = 10',
    'role' => "UPDATE users SET role = 'student' WHERE id = 1",
] as $label => $sql) {
    test_case("write-time $label change rejects stale update", function () use ($sql) {
        $db = new ApplicationFixture();
        $db->beforeUpdate = function ($pdo) use ($sql) { $pdo->exec($sql); };
        expect_status(409, function () use ($db) { application_update_owned($db, 1, 100, 'shortlisted'); });
        check($db->statuses()[100] !== 'shortlisted', 'Stale update accepted.');
    });
}
test_case('revoked database role cannot list or update via stale session', function () {
    $db = new ApplicationFixture();
    $db->pdo->exec("UPDATE users SET role = 'student' WHERE id = 1");
    check(application_owned_list($db, 1)->num_rows === 0, 'Revoked company can list.');
    expect_status(404, function () use ($db) { application_update_owned($db, 1, 100, 'shortlisted'); });
});
foreach (['failPrepare', 'failExecute'] as $failure) {
    test_case("database $failure does not report success", function () use ($failure) {
        $db = new ApplicationFixture();
        $db->$failure = true;
        $thrown = false;
        try { application_update_owned($db, 1, 100, 'shortlisted'); } catch (RuntimeException $error) { $thrown = true; }
        check($thrown && $db->statuses()[100] === 'applied', 'Database failure ignored.');
    });
}
test_case('invalid session identity rejected', function () use ($session, $post) {
    expect_status(401, function () use ($session, $post) {
        application_update_request(array_replace($session, ['user_id' => '1 OR 1=1']), 'POST', $post);
    });
});
test_case('CSRF token is session-specific and stable across forms', function () {
    $one = []; $two = [];
    $first = application_csrf_token($one);
    check($first === application_csrf_token($one) && $first !== application_csrf_token($two), 'Token lifecycle failed.');
});

echo "RESULT: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
