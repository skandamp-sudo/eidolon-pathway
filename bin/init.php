<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
try {
    \Pathway\initialize_database(\Pathway\database(true));
    echo 'Local SQLite schema v' . \Pathway\SCHEMA_VERSION . " ready. No accounts seeded or legacy data imported.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Initialization failed: " . $error->getMessage() . "\n");
    exit(1);
}
