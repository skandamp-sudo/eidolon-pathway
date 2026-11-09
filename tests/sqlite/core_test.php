<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$passed = 0; $failed = 0;
function ensure(bool $ok): void { if (!$ok) { throw new RuntimeException('Assertion failed.'); } }
function run_test(string $name, callable $test): void {
    global $passed, $failed;
    try { $test(); $passed++; echo "PASS $name\n"; }
    catch (Throwable $error) { $failed++; echo "FAIL $name (" . get_class($error) . ")\n"; }
}
function rejects(callable $call, ?int $status = null): void {
    try { $call(); } catch (Throwable $error) {
        if ($status !== null) { ensure($error instanceof \Pathway\HttpError && $error->status === $status); }
        return;
    }
    throw new RuntimeException('Expected rejection.');
}

$directory = sys_get_temp_dir() . '/pathway-sqlite-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
putenv('PATHWAY_DATA_DIR=' . $directory);
register_shutdown_function(function () use ($directory): void {
    foreach (glob($directory . '/*') as $file) { if (is_file($file)) { unlink($file); } }
    rmdir($directory);
});
$pdo = \Pathway\database(true);
run_test('transactional schema initialization', function () use ($pdo): void {
    \Pathway\initialize_database($pdo); \Pathway\verify_schema($pdo);
    ensure((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1);
});
run_test('schema initialization is idempotent', function () use ($pdo): void {
    \Pathway\initialize_database($pdo);
    ensure((int) $pdo->query('SELECT count(*) FROM schema_migrations')->fetchColumn() === \Pathway\SCHEMA_VERSION);
});
run_test('database is private and outside public root', function () use ($directory): void {
    ensure((fileperms($directory . '/pathway.sqlite') & 0077) === 0);
    ensure(!str_starts_with(realpath($directory), realpath(\Pathway\ROOT . '/public')));
});
$password = bin2hex(random_bytes(16));
$input = ['name' => 'Fixture Student', 'email' => 'student@example.test', 'password' => $password, 'role' => 'admin'];
$id = 0;
run_test('registration hashes password and ignores elevated role', function () use ($pdo, $input, &$id): void {
    $id = \Pathway\register_student($pdo, $input);
    $row = $pdo->query('SELECT * FROM users')->fetch();
    ensure($row['role'] === 'student' && $row['password_hash'] !== $input['password']);
    ensure(password_verify($input['password'], $row['password_hash']));
    ensure((int) $pdo->query('SELECT count(*) FROM student_profiles')->fetchColumn() === 1);
});
run_test('duplicate case-insensitive email rejected', function () use ($pdo, $input): void {
    rejects(fn() => \Pathway\register_student($pdo, array_replace($input, ['email' => 'STUDENT@example.test'])), 409);
    ensure((int) $pdo->query('SELECT count(*) FROM users')->fetchColumn() === 1);
});
run_test('valid authentication returns identity without hash', function () use ($pdo, $input, &$id): void {
    $user = \Pathway\authenticate($pdo, $input);
    ensure($user['id'] === $id && !isset($user['password_hash']));
});
run_test('wrong password rejected', function () use ($pdo, $input): void {
    ensure(\Pathway\authenticate($pdo, array_replace($input, ['password' => bin2hex(random_bytes(16))])) === null);
});
run_test('unknown user rejected', function () use ($pdo, $input): void {
    ensure(\Pathway\authenticate($pdo, array_replace($input, ['email' => 'missing@example.test'])) === null);
});
run_test('SQL injection password cannot authenticate', function () use ($pdo, $input): void {
    ensure(\Pathway\authenticate($pdo, array_replace($input, ['password' => "' OR 1=1 --"])) === null);
});
run_test('SQL injection email rejected by validation', function () use ($pdo, $input): void {
    rejects(fn() => \Pathway\authenticate($pdo, array_replace($input, ['email' => "' OR 1=1 --"])), 422);
});
foreach (['array' => [], 'short' => 'short', 'overlong' => str_repeat('x', 73), 'nul' => "invalid\0value"] as $name => $value) {
    run_test("invalid password $name rejected", function () use ($pdo, $input, $value): void {
        rejects(fn() => \Pathway\register_student($pdo, array_replace($input, ['password' => $value])), 422);
    });
}
run_test('SQL metacharacters in name stored as data', function () use ($pdo, $input): void {
    $name = "Fixture'); DROP TABLE users;--";
    $id = \Pathway\register_student($pdo, array_replace($input, ['email' => 'literal@example.test', 'name' => $name]));
    $statement = $pdo->prepare('SELECT name FROM users WHERE id = ?'); $statement->execute([$id]);
    ensure($statement->fetchColumn() === $name);
});
run_test('foreign key rejects orphan profile', function () use ($pdo): void {
    rejects(fn() => $pdo->exec('INSERT INTO student_profiles(user_id) VALUES (999999)'));
});
run_test('referenced account deletion restricted', function () use ($pdo, &$id): void {
    $statement = $pdo->prepare('DELETE FROM users WHERE id = ?');
    rejects(fn() => $statement->execute([$id]));
});
run_test('invalid role constraint enforced', function () use ($pdo): void {
    rejects(fn() => $pdo->exec("UPDATE users SET role = 'owner'"));
});
run_test('registration rolls back on profile failure', function () use ($pdo, $input): void {
    $before = (int) $pdo->query('SELECT count(*) FROM users')->fetchColumn();
    $pdo->exec("CREATE TRIGGER reject_profile BEFORE INSERT ON student_profiles BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
    try { rejects(fn() => \Pathway\register_student($pdo, array_replace($input, ['email' => 'rollback@example.test']))); }
    finally { $pdo->exec('DROP TRIGGER reject_profile'); }
    ensure((int) $pdo->query('SELECT count(*) FROM users')->fetchColumn() === $before);
});
run_test('explicit rollback restores update', function () use ($pdo, &$id): void {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?'); $statement->execute(['Temporary', $id]);
    $pdo->rollBack();
    $statement = $pdo->prepare('SELECT name FROM users WHERE id = ?'); $statement->execute([$id]);
    ensure($statement->fetchColumn() === 'Fixture Student');
});
run_test('anonymous dashboard denied', function () use ($pdo): void {
    $_SESSION = []; rejects(fn() => \Pathway\current_student($pdo), 401);
});
run_test('student dashboard authorized', function () use ($pdo, &$id): void {
    $_SESSION = ['user_id' => $id, 'role' => 'student']; ensure(\Pathway\current_student($pdo)['id'] === $id);
});
run_test('wrong session role denied', function () use ($pdo, &$id): void {
    $_SESSION = ['user_id' => $id, 'role' => 'company']; rejects(fn() => \Pathway\current_student($pdo), 403);
});
run_test('changed database role invalidates student access', function () use ($pdo, &$id): void {
    $_SESSION = ['user_id' => $id, 'role' => 'student']; $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare("UPDATE users SET role = 'company' WHERE id = ?"); $statement->execute([$id]);
        rejects(fn() => \Pathway\current_student($pdo), 403);
    } finally { $pdo->rollBack(); }
});
run_test('valid CSRF accepted', function (): void {
    $_SESSION = []; $token = \Pathway\csrf_token(); \Pathway\verify_csrf(['csrf_token' => $token]);
});
foreach (['missing' => null, 'invalid' => str_repeat('0', 64), 'array' => []] as $name => $value) {
    run_test("CSRF $name rejected", function () use ($value): void {
        $_SESSION = []; \Pathway\csrf_token(); rejects(fn() => \Pathway\verify_csrf(['csrf_token' => $value]), 403);
    });
}
run_test('HTML output is escaped', function (): void {
    ensure(\Pathway\escape('<script>"&') === '&lt;script&gt;&quot;&amp;');
});
run_test('throttle caps persisted attempts', function () use ($pdo): void {
    for ($i = 0; $i < 20; $i++) { \Pathway\throttle($pdo, 'fixture'); }
    rejects(fn() => \Pathway\throttle($pdo, 'fixture'), 429);
    ensure((int) $pdo->query('SELECT attempts FROM auth_throttle')->fetchColumn() === 20);
});
run_test('expired throttle is cleared', function () use ($pdo): void {
    $pdo->exec('UPDATE auth_throttle SET expires_at = 0'); \Pathway\throttle($pdo, 'fixture');
    ensure((int) $pdo->query('SELECT attempts FROM auth_throttle')->fetchColumn() === 1);
});
run_test('migration checksum mismatch fails closed', function () use ($pdo): void {
    $pdo->beginTransaction();
    try {
        $pdo->exec("UPDATE schema_migrations SET checksum = '0000000000000000000000000000000000000000000000000000000000000000'");
        rejects(fn() => \Pathway\verify_schema($pdo));
    } finally { $pdo->rollBack(); }
});
run_test('existing unrelated database is not migrated', function (): void {
    $other = new PDO('sqlite::memory:'); $other->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $other->exec('CREATE TABLE unrelated (id INTEGER PRIMARY KEY)');
    rejects(fn() => \Pathway\initialize_database($other));
    ensure((int) $other->query('PRAGMA user_version')->fetchColumn() === 0);
    ensure($other->query('SELECT count(*) FROM unrelated')->fetchColumn() === 0);
});
run_test('database content survives a fresh PHP process', function () use ($directory): void {
    $code = 'require $argv[1]; echo count(\\Pathway\\database()->query("SELECT id FROM users")->fetchAll());';
    $process = proc_open([PHP_BINARY, '-r', $code, \Pathway\ROOT . '/app/bootstrap.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    ensure(proc_close($process) === 0 && $out === '2' && $err === '');
});
run_test('public data directory rejected before creation', function () use ($directory): void {
    putenv('PATHWAY_DATA_DIR=' . realpath(\Pathway\ROOT . '/public') . '/forbidden-data');
    try { rejects(fn() => \Pathway\data_directory(true)); } finally { putenv('PATHWAY_DATA_DIR=' . $directory); }
    ensure(!file_exists(\Pathway\ROOT . '/public/forbidden-data'));
});
echo "RESULT: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
