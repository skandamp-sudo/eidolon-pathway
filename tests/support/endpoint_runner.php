<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Executed in a private temporary copy containing only fixture configuration.
$request = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$allowed = ['view_applicants.php', 'update_status.php', 'shortlist.php'];
if (!in_array($request['endpoint'], $allowed, true)) {
    throw new RuntimeException('Unknown fixture endpoint.');
}
ini_set('display_errors', '0');
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
session_save_path(__DIR__ . '/sessions');
session_id(bin2hex(random_bytes(16)));
session_start();
$_SESSION = $request['session'];
session_write_close();
$_SERVER['REQUEST_METHOD'] = $request['method'];
$_POST = $request['post'];
http_response_code(200);
ob_start();
register_shutdown_function(function () {
    $body = ob_get_clean();
    echo json_encode([
        'status' => http_response_code(),
        'body' => $body,
        'connected' => isset($GLOBALS['conn']),
        'statuses' => isset($GLOBALS['conn']) ? $GLOBALS['conn']->statuses() : null,
        'csrf_token' => $_SESSION['application_csrf_token'] ?? null,
    ], JSON_THROW_ON_ERROR);
});
require __DIR__ . '/' . $request['endpoint'];
