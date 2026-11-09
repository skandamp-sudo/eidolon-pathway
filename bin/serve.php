<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
try {
    \Pathway\database(); // Refuse to launch with absent/unrecognized schema.
    $port = $argv[1] ?? '8080';
    if (!ctype_digit($port) || (int) $port < 1024 || (int) $port > 65535) {
        throw new RuntimeException('Choose a port between 1024 and 65535.');
    }
    $root = dirname(__DIR__);
    $args = [PHP_BINARY, '-d', 'display_errors=0', '-d', 'expose_php=0', '-d', 'post_max_size=8K',
        '-d', 'upload_max_filesize=1K', '-d', 'max_input_vars=10', '-d', 'max_input_nesting_level=4',
        '-d', 'memory_limit=128M', '-d', 'max_execution_time=10',
        '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/app/router.php'];
    echo "Local student slice: http://127.0.0.1:$port (Ctrl-C to stop)\n";
    passthru(implode(' ', array_map('escapeshellarg', $args)), $code);
    exit($code);
} catch (Throwable $error) {
    fwrite(STDERR, "Launch failed: " . $error->getMessage() . "\n");
    exit(1);
}
