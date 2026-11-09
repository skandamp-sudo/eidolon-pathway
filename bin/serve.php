<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $root = dirname(__DIR__);
    $port = '8080';
    $isDemo = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--demo') {
            $isDemo = true;
        } elseif (ctype_digit($arg)) {
            $port = $arg;
        }
    }

    if ($isDemo) {
        $demoDb = $root . '/var/pathway_demo.sqlite';
        if (!is_file($demoDb)) {
            throw new RuntimeException("Isolated demo database not found ($demoDb).\nSeed it first with: php bin/seed_demo.php --confirm-demo-seed --demo-db=var/pathway_demo.sqlite");
        }
        putenv('PATHWAY_DATABASE_PATH=' . $demoDb);
        echo "Running in DEMO MODE with isolated database: var/pathway_demo.sqlite\n";
    }

    \Pathway\database(); // Refuse to launch with absent/unrecognized schema.

    if ((int) $port < 1024 || (int) $port > 65535) {
        throw new RuntimeException('Choose a port between 1024 and 65535.');
    }

    $args = [
        PHP_BINARY,
        '-d', 'display_errors=0',
        '-d', 'expose_php=0',
        '-d', 'post_max_size=8K',
        '-d', 'upload_max_filesize=1K',
        '-d', 'max_input_vars=10',
        '-d', 'max_input_nesting_level=4',
        '-d', 'memory_limit=128M',
        '-d', 'max_execution_time=10',
        '-S', '127.0.0.1:' . $port,
        '-t', $root . '/public',
        $root . '/app/router.php'
    ];

    echo "EIDOLON PATHWAY server running at: http://127.0.0.1:$port (Ctrl-C to stop)\n";
    passthru(implode(' ', array_map('escapeshellarg', $args)), $code);
    exit($code);
} catch (Throwable $error) {
    fwrite(STDERR, "Launch failed: " . $error->getMessage() . "\n");
    exit(1);
}
