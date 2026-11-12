<?php
declare(strict_types=1);

namespace Pathway;

const ROOT = __DIR__ . '/..';
const SCHEMA_VERSION = 8;
const APPLICATION_ID = 1347895639;

final class HttpError extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

function runtime_check(): void
{
    if (PHP_VERSION_ID < 80200 || !extension_loaded('pdo_sqlite') || !extension_loaded('session')) {
        throw new \RuntimeException('PHP 8.2+ with PDO SQLite and sessions is required.');
    }
    if (stripos(PHP_BINARY, 'xampp') !== false) {
        throw new \RuntimeException('Use standalone PHP, not XAMPP.');
    }
}

function is_cloud_mode(): bool
{
    return getenv('PATHWAY_CLOUD_MODE') === 'true'
        || getenv('PATHWAY_ENV') === 'production'
        || getenv('RAILWAY_ENVIRONMENT') !== false;
}

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (is_cloud_mode() && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function data_directory(bool $create = false): string
{
    $path = getenv('PATHWAY_DATA_DIR') ?: ROOT . '/var';
    if (!str_starts_with($path, '/') || str_contains($path, "\0") || is_link($path)) {
        throw new \RuntimeException('An absolute, non-symlink data directory is required.');
    }
    // Resolve the parent before creation so a public-root alias is rejected too.
    $parent = realpath(dirname($path));
    $public = realpath(ROOT . '/public');
    $resolved = realpath($path) ?: ($parent === false ? '' : $parent . '/' . basename($path));
    if ($resolved === '' || $resolved === $public || str_starts_with($resolved, $public . '/')) {
        throw new \RuntimeException('Data must be outside the public document root.');
    }
    $isCloud = is_cloud_mode();
    if (!is_dir($path)) {
        if (!$create && !$isCloud) {
            throw new \RuntimeException('Initialize local storage with bin/init.php first.');
        }
        if (!@mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('Initialize local storage with bin/init.php first.');
        }
    }
    if ((fileperms($path) & 0077) !== 0) {
        @chmod($path, 0700);
        clearstatcache(true, $path);
        if ((fileperms($path) & 0077) !== 0) {
            throw new \RuntimeException('Data directory permissions must be 0700.');
        }
    }
    return (string) realpath($path);
}

function database(bool $initialize = false, ?string $customPath = null): \PDO
{
    runtime_check();
    umask(0077);
    $isCloud = is_cloud_mode();
    $dataDir = data_directory($initialize || $isCloud);
    $envPath = getenv('PATHWAY_DATABASE_PATH');
    if ($customPath !== null) {
        $path = $customPath;
    } elseif ($envPath && is_string($envPath) && trim($envPath) !== '') {
        $trimmed = trim($envPath);
        $path = str_starts_with($trimmed, '/') ? $trimmed : realpath(ROOT) . '/' . ltrim($trimmed, './');
    } else {
        $path = $dataDir . '/pathway.sqlite';
    }

    if (!str_starts_with($path, '/') || str_contains($path, "\0") || is_link($path)) {
        throw new \RuntimeException('An absolute, non-symlink database path is required.');
    }
    $public = realpath(ROOT . '/public');
    $resolvedDir = realpath(dirname($path));
    if ($public && ($resolvedDir === $public || str_starts_with((string) $resolvedDir, $public . '/'))) {
        throw new \RuntimeException('Database must be outside the public document root.');
    }
    if (is_link($path)) {
        throw new \RuntimeException('Symlinks are not allowed for database path.');
    }
    if (!is_file($path)) {
        if ($isCloud && !$initialize) {
            initialize_database(database(true, $path));
        } elseif (!$initialize) {
            throw new \RuntimeException('Initialize the local database first.');
        }
    }
    if (is_file($path) && (fileperms($path) & 0077) !== 0) {
        @chmod($path, 0600);
        clearstatcache(true, $path);
        if ((fileperms($path) & 0077) !== 0) {
            throw new \RuntimeException('Database permissions must be 0600.');
        }
    }
    $pdo = new \PDO('sqlite:' . $path, null, null, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 3000');
    if ((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1) {
        throw new \RuntimeException('Foreign key enforcement is required.');
    }
    if (!$initialize) {
        verify_schema($pdo);
    }
    return $pdo;
}

function migrations(): array
{
    return [
        1 => __DIR__ . '/migrations/001_student.sql',
        2 => __DIR__ . '/migrations/002_evidence.sql',
        3 => __DIR__ . '/migrations/003_organizations.sql',
        4 => __DIR__ . '/migrations/004_employment.sql',
        5 => __DIR__ . '/migrations/005_college.sql',
        6 => __DIR__ . '/migrations/006_affiliation_and_consent.sql',
        7 => __DIR__ . '/migrations/007_organization_requests.sql',
        8 => __DIR__ . '/migrations/008_oauth_identities.sql',
    ];
}

function verify_schema(\PDO $pdo, int $version = SCHEMA_VERSION): void
{
    if ((int) $pdo->query('PRAGMA application_id')->fetchColumn() !== APPLICATION_ID ||
        (int) $pdo->query('PRAGMA user_version')->fetchColumn() !== $version || $version < 1 || $version > SCHEMA_VERSION) {
        throw new \RuntimeException('Unsupported local database version.');
    }
    $statement = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
    foreach (migrations() as $number => $file) {
        if ($number > $version) { break; }
        $statement->execute([$number]);
        $checksum = $statement->fetchColumn();
        if (!is_string($checksum) || !hash_equals(hash_file('sha256', $file), $checksum)) {
            throw new \RuntimeException('Migration checksum mismatch.');
        }
    }
}

function initialize_database(\PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        if ($version !== 0) {
            verify_schema($pdo, $version);
        } else {
            $tables = (int) $pdo->query("SELECT count(*) FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'")->fetchColumn();
            if ($tables !== 0 || (int) $pdo->query('PRAGMA application_id')->fetchColumn() !== 0) {
                throw new \RuntimeException('Refusing to initialize an existing unrecognized database.');
            }
        }
        foreach (migrations() as $number => $migration) {
            if ($number <= $version) { continue; }
            $pdo->exec(file_get_contents($migration));
            $statement = $pdo->prepare('INSERT INTO schema_migrations(version, checksum) VALUES (?, ?)');
            $statement->execute([$number, hash_file('sha256', $migration)]);
            $pdo->exec('PRAGMA application_id = ' . APPLICATION_ID);
            $pdo->exec('PRAGMA user_version = ' . $number);
        }
        $pdo->exec('COMMIT');
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
}

function field(array $input, string $name, int $max): string
{
    $value = $input[$name] ?? null;
    if (!is_string($value) || strlen($value) > $max || str_contains($value, "\0")) {
        throw new HttpError(422, 'Check the form fields and try again.');
    }
    return $value;
}

function email_address(array $input): string
{
    $email = strtolower(trim(field($input, 'email', 254)));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new HttpError(422, 'Enter a valid email address.');
    }
    return $email;
}

function register_student(\PDO $pdo, array $input): int
{
    $name = trim(field($input, 'name', 100));
    $email = email_address($input);
    $password = field($input, 'password', 72);
    if ($name === '' || preg_match('/[\x00-\x1f\x7f]/', $name) || !preg_match('//u', $name) || strlen($password) < 12) {
        throw new HttpError(422, 'Use a name and a password of 12–72 bytes.');
    }
    // Explicit byte bound avoids bcrypt truncation. No user-controlled role.
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare("INSERT INTO users(name, email, password_hash, role) VALUES (?, ?, ?, 'student')");
        $statement->execute([$name, $email, $hash]);
        $id = (int) $pdo->lastInsertId();
        $statement = $pdo->prepare('INSERT INTO student_profiles(user_id) VALUES (?)');
        $statement->execute([$id]);
        $pdo->commit();
        return $id;
    } catch (\Throwable $error) {
        $pdo->rollBack();
        if ($error instanceof \PDOException && ($error->errorInfo[1] ?? null) === 19) {
            throw new HttpError(409, 'Account could not be created. Try signing in instead.');
        }
        throw $error;
    }
}

function authenticate(\PDO $pdo, array $input): ?array
{
    $email = email_address($input);
    $password = field($input, 'password', 72);
    $statement = $pdo->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ?');
    $statement->execute([$email]);
    $user = $statement->fetch();
    // Valid public dummy hash: same verification work for an unknown account.
    $dummy = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!password_verify($password, $user ? $user['password_hash'] : $dummy) || !$user) {
        return null;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $statement = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
        $statement->execute([password_hash($password, PASSWORD_DEFAULT), $user['id'], $user['password_hash']]);
        if ($statement->rowCount() !== 1) {
            throw new HttpError(409, 'Account changed. Please sign in again.');
        }
    }
    unset($user['password_hash']);
    return $user;
}

function throttle(\PDO $pdo, string $action): void
{
    // Loopback-only pilot: one bounded bucket per action, not attacker-chosen emails.
    $bucket = hash('sha256', $action);
    $now = time();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $statement = $pdo->prepare('DELETE FROM auth_throttle WHERE expires_at <= ?');
        $statement->execute([$now]);
        $statement = $pdo->prepare('SELECT attempts FROM auth_throttle WHERE bucket = ?');
        $statement->execute([$bucket]);
        if ((int) $statement->fetchColumn() >= 20) {
            throw new HttpError(429, 'Too many attempts. Wait up to five minutes before trying again.');
        }
        $statement = $pdo->prepare('INSERT INTO auth_throttle(bucket, attempts, expires_at) VALUES (?, 1, ?) ON CONFLICT(bucket) DO UPDATE SET attempts = attempts + 1');
        $statement->execute([$bucket, $now + 300]);
        $pdo->exec('COMMIT');
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

function start_session(): void
{
    $directory = data_directory() . '/sessions';
    if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700, true))) {
        throw new \RuntimeException('Session storage unavailable.');
    }
    if ((fileperms($directory) & 0077) !== 0) {
        @chmod($directory, 0700);
    }
    session_save_path($directory);
    $isCloud = is_cloud_mode();
    session_name($isCloud ? 'pathway_session' : 'pathway_local');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '1800');
    session_cache_limiter(''); // Keep the explicit no-store policy from the controller.
    $secure = $isCloud && is_https_request();
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
    if (!session_start()) {
        throw new \RuntimeException('Session unavailable.');
    }
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || strlen($_SESSION['csrf']) !== 64) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(array $input): void
{
    $token = $input['csrf_token'] ?? null;
    if (!is_string($token) || strlen($token) !== 64 || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        throw new HttpError(403, 'This form expired. Reload the page and try again.');
    }
}

function current_student(\PDO $pdo): array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) || $id < 1) {
        throw new HttpError(401, 'Sign in to open your student dashboard.');
    }
    $statement = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $statement->execute([$id]);
    $user = $statement->fetch();
    if (!$user || $user['role'] !== 'student' || ($_SESSION['role'] ?? null) !== 'student') {
        throw new HttpError(403, 'A student account is required.');
    }
    return $user;
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
