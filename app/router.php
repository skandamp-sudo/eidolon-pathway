<?php
declare(strict_types=1);

// Always dispatch through the allowlist; never serve arbitrary filesystem paths.
require dirname(__DIR__) . '/public/index.php';
