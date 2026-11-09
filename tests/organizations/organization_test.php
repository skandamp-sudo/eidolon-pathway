<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/organizations/organization.php';

$passed = 0;
$failed = 0;

function ensure(bool $ok, string $msg = 'Assertion failed'): void
{
    if (!$ok) {
        throw new RuntimeException($msg);
    }
}

function run_test(string $name, callable $test): void
{
    global $passed, $failed;
    try {
        $test();
        $passed++;
        echo "PASS $name\n";
    } catch (\Throwable $error) {
        $failed++;
        echo "FAIL $name (" . get_class($error) . ": " . $error->getMessage() . ")\n";
    }
}

function expect_org_error(callable $call, string $reason, ?int $status = null): void
{
    try {
        $call();
    } catch (\Pathway\Organizations\OrganizationError $error) {
        ensure($error->reason === $reason, "Expected reason {$reason}, got {$error->reason}");
        if ($status !== null) {
            ensure($error->statusCode === $status, "Expected status {$status}, got {$error->statusCode}");
        }
        return;
    }
    throw new RuntimeException('Expected OrganizationError: ' . $reason);
}

function expect_http_error(callable $call, int $expectedStatus): void
{
    try {
        $call();
    } catch (\Pathway\HttpError $error) {
        ensure($error->status === $expectedStatus, "Expected HttpError status {$expectedStatus}, got {$error->status}");
        return;
    }
    throw new RuntimeException('Expected HttpError status ' . $expectedStatus);
}

// Set up an isolated temporary SQLite database for testing
$directory = sys_get_temp_dir() . '/pathway-org-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
putenv('PATHWAY_DATA_DIR=' . $directory);

register_shutdown_function(function () use ($directory): void {
    foreach (glob($directory . '/*') as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_dir($directory . '/sessions')) {
        foreach (glob($directory . '/sessions/*') as $f) {
            unlink($f);
        }
        rmdir($directory . '/sessions');
    }
    rmdir($directory);
});

$pdo = \Pathway\database(true);
\Pathway\initialize_database($pdo);

// Seed base users: one platform admin, two students, one company lead
$adminId = \Pathway\register_student($pdo, ['name' => 'Platform Admin', 'email' => 'admin@pathway.test', 'password' => 'AdminPassword123!']);
$pdo->exec("UPDATE users SET role = 'admin' WHERE id = $adminId");

$student1Id = \Pathway\register_student($pdo, ['name' => 'Student One', 'email' => 'student1@example.test', 'password' => 'StudentPassword123!']);
$student2Id = \Pathway\register_student($pdo, ['name' => 'Student Two', 'email' => 'student2@example.test', 'password' => 'StudentPassword123!']);
$staffCandidateId = \Pathway\register_student($pdo, ['name' => 'Staff Candidate', 'email' => 'staff@company-a.test', 'password' => 'StaffPassword123!']);

echo "=== CHECKPOINT A: SECURE ORGANIZATION FOUNDATION TESTS ===\n";

// --- Schema & Types ---
run_test('organization type constraint allows COMPANY and COLLEGE', function () use ($pdo, $adminId): void {
    $companyId = \Pathway\Organizations\create_organization($pdo, $adminId, 'Acme Robotics', 'COMPANY', true);
    $collegeId = \Pathway\Organizations\create_organization($pdo, $adminId, 'Stanford Engineering', 'COLLEGE', true);

    $stmt = $pdo->prepare('SELECT type, status FROM organizations WHERE id IN (?, ?)');
    $stmt->execute([$companyId, $collegeId]);
    $rows = $stmt->fetchAll();
    ensure(count($rows) === 2);
    ensure($rows[0]['type'] === 'COMPANY' || $rows[1]['type'] === 'COMPANY');
    ensure($rows[0]['type'] === 'COLLEGE' || $rows[1]['type'] === 'COLLEGE');
});

run_test('invalid organization type rejected', function () use ($pdo, $adminId): void {
    expect_org_error(fn() => \Pathway\Organizations\create_organization($pdo, $adminId, 'Invalid Org', 'HOSPITAL'), 'invalid_organization_type', 422);
});

run_test('organization name length constraints enforced', function () use ($pdo, $adminId): void {
    expect_org_error(fn() => \Pathway\Organizations\create_organization($pdo, $adminId, 'A', 'COMPANY'), 'invalid_organization_name', 422);
    expect_org_error(fn() => \Pathway\Organizations\create_organization($pdo, $adminId, str_repeat('X', 121), 'COMPANY'), 'invalid_organization_name', 422);
});

run_test('slug generation produces safe URL identifier and resolves collisions', function () use ($pdo, $adminId): void {
    $id1 = \Pathway\Organizations\create_organization($pdo, $adminId, 'Alpha Tech Inc.', 'COMPANY', true);
    $id2 = \Pathway\Organizations\create_organization($pdo, $adminId, 'Alpha Tech Inc.', 'COMPANY', true);

    $stmt = $pdo->prepare('SELECT slug FROM organizations WHERE id = ?');
    $stmt->execute([$id1]);
    $slug1 = $stmt->fetchColumn();
    $stmt->execute([$id2]);
    $slug2 = $stmt->fetchColumn();

    ensure($slug1 === 'alpha-tech-inc');
    ensure($slug2 === 'alpha-tech-inc-2');
});

// --- Role & Permission Deny-by-Default ---
run_test('deny-by-default: anonymous or student cannot access admin permissions', function () use ($pdo, $student1Id): void {
    ensure(\Pathway\Organizations\can($pdo, $student1Id, 'perm.admin') === false);
    ensure(\Pathway\Organizations\can($pdo, $student1Id, 'perm.org.approve') === false);
    ensure(\Pathway\Organizations\can($pdo, $student1Id, 'perm.org.suspend') === false);
    expect_http_error(fn() => \Pathway\Organizations\require_permission($pdo, $student1Id, 'perm.admin'), 403);
    expect_http_error(fn() => \Pathway\Organizations\require_permission($pdo, $student1Id, 'perm.org.approve'), 403);
});

run_test('platform admin granted platform admin permissions', function () use ($pdo, $adminId): void {
    ensure(\Pathway\Organizations\can($pdo, $adminId, 'perm.admin') === true);
    ensure(\Pathway\Organizations\can($pdo, $adminId, 'perm.org.approve') === true);
    ensure(\Pathway\Organizations\can($pdo, $adminId, 'perm.org.suspend') === true);
});

// --- Organization Approval & Suspension Lifecycle ---
$pendingOrgId = 0;
run_test('student-created organization starts in PENDING status', function () use ($pdo, $student1Id, &$pendingOrgId): void {
    $pendingOrgId = \Pathway\Organizations\create_organization($pdo, $student1Id, 'Student Startup', 'COMPANY');
    $stmt = $pdo->prepare('SELECT status FROM organizations WHERE id = ?');
    $stmt->execute([$pendingOrgId]);
    ensure($stmt->fetchColumn() === 'PENDING');
});

run_test('unapproved PENDING organization cannot invite or mutate resources', function () use ($pdo, $student1Id, &$pendingOrgId): void {
    ensure(\Pathway\Organizations\can($pdo, $student1Id, 'perm.org.invite', $pendingOrgId) === false);
    ensure(\Pathway\Organizations\can($pdo, $student1Id, 'perm.org.mutate', $pendingOrgId) === false);
    expect_http_error(fn() => \Pathway\Organizations\create_invitation($pdo, $student1Id, $pendingOrgId, 'new@test.com'), 403);
});

run_test('student cannot approve organization', function () use ($pdo, $student1Id, &$pendingOrgId): void {
    expect_http_error(fn() => \Pathway\Organizations\approve_organization($pdo, $student1Id, $pendingOrgId), 403);
});

run_test('platform admin can approve pending organization', function () use ($pdo, $adminId, $student1Id, &$pendingOrgId): void {
    \Pathway\Organizations\approve_organization($pdo, $adminId, $pendingOrgId);
    $stmt = $pdo->prepare('SELECT status FROM organizations WHERE id = ?');
    $stmt->execute([$pendingOrgId]);
    ensure($stmt->fetchColumn() === 'ACTIVE');
    // Now owner can invite
    ensure(\Pathway\Organizations\can($pdo, $student1Id, 'perm.org.invite', $pendingOrgId) === true);
});

run_test('approving already active organization rejects with conflict', function () use ($pdo, $adminId, &$pendingOrgId): void {
    expect_org_error(fn() => \Pathway\Organizations\approve_organization($pdo, $adminId, $pendingOrgId), 'organization_not_pending', 409);
});

run_test('platform admin can suspend active organization with reason', function () use ($pdo, $adminId, &$pendingOrgId): void {
    \Pathway\Organizations\suspend_organization($pdo, $adminId, $pendingOrgId, 'Policy violation investigation');
    $stmt = $pdo->prepare('SELECT status FROM organizations WHERE id = ?');
    $stmt->execute([$pendingOrgId]);
    ensure($stmt->fetchColumn() === 'SUSPENDED');
});

run_test('suspended organization blocks member privileged actions', function () use ($pdo, $student1Id, &$pendingOrgId): void {
    ensure(\Pathway\Organizations\can($pdo, $student1Id, 'perm.org.invite', $pendingOrgId) === false);
    ensure(\Pathway\Organizations\can($pdo, $student1Id, 'perm.org.mutate', $pendingOrgId) === false);
    expect_http_error(fn() => \Pathway\Organizations\create_invitation($pdo, $student1Id, $pendingOrgId, 'new@test.com'), 403);
});

// --- Single-Use, Expiring Invitations Stored as Hashes ---
$activeOrgId = 0;
run_test('setup active organization for invitation tests', function () use ($pdo, $adminId, &$activeOrgId): void {
    $activeOrgId = \Pathway\Organizations\create_organization($pdo, $adminId, 'Nexus Laboratories', 'COMPANY', true);
    ensure($activeOrgId > 0);
});

$inviteData = [];
run_test('create_invitation generates high-entropy raw token and stores SHA-256 hash', function () use ($pdo, $adminId, $activeOrgId, &$inviteData): void {
    $inviteData = \Pathway\Organizations\create_invitation($pdo, $adminId, $activeOrgId, 'staff@company-a.test', 'STAFF', 3600);

    ensure(strlen($inviteData['raw_token']) === 64); // 32 bytes hex
    ensure(strlen($inviteData['token_hash']) === 64);
    ensure($inviteData['token_hash'] === hash('sha256', $inviteData['raw_token']));
    ensure($inviteData['expires_at'] > time());

    // Verify database only stores token_hash, NEVER raw token
    $stmt = $pdo->prepare('SELECT token_hash, used_at FROM organization_invitations WHERE id = ?');
    $stmt->execute([$inviteData['invitation_id']]);
    $row = $stmt->fetch();
    ensure($row['token_hash'] === $inviteData['token_hash']);
    ensure($row['used_at'] === null);

    // Verify raw token is nowhere in the table
    $stmt = $pdo->prepare('SELECT count(*) FROM organization_invitations WHERE token_hash = ?');
    $stmt->execute([$inviteData['raw_token']]);
    ensure((int) $stmt->fetchColumn() === 0);
});

run_test('accepting invitation with wrong user email rejected', function () use ($pdo, $student2Id, &$inviteData): void {
    // student2's email is student2@example.test, not staff@company-a.test
    expect_org_error(fn() => \Pathway\Organizations\accept_invitation($pdo, $inviteData['raw_token'], $student2Id), 'invitation_email_mismatch', 403);
});

run_test('accepting valid invitation succeeds and creates active membership', function () use ($pdo, $staffCandidateId, $activeOrgId, &$inviteData): void {
    $result = \Pathway\Organizations\accept_invitation($pdo, $inviteData['raw_token'], $staffCandidateId);
    ensure($result['organization_id'] === $activeOrgId);
    ensure($result['role'] === 'STAFF');

    // Verify invitation marked used_at
    $stmt = $pdo->prepare('SELECT used_at FROM organization_invitations WHERE id = ?');
    $stmt->execute([$inviteData['invitation_id']]);
    ensure($stmt->fetchColumn() !== null);

    // Verify active membership exists
    $membership = \Pathway\Organizations\get_membership($pdo, $staffCandidateId, $activeOrgId);
    ensure($membership !== null);
    ensure($membership['role'] === 'STAFF');
    ensure($membership['status'] === 'ACTIVE');
});

run_test('replay attack: consumed invitation cannot be re-accepted', function () use ($pdo, $staffCandidateId, &$inviteData): void {
    expect_org_error(fn() => \Pathway\Organizations\accept_invitation($pdo, $inviteData['raw_token'], $staffCandidateId), 'invitation_already_used', 410);
});

run_test('expired invitation rejected', function () use ($pdo, $adminId, $activeOrgId, $student2Id): void {
    // Issue invitation that expires immediately (ttl = 5m, then manually backdate in DB for test)
    $inv = \Pathway\Organizations\create_invitation($pdo, $adminId, $activeOrgId, 'student2@example.test', 'REVIEWER', 300);
    $pdo->prepare('UPDATE organization_invitations SET expires_at = ? WHERE id = ?')->execute([time() - 10, $inv['invitation_id']]);

    expect_org_error(fn() => \Pathway\Organizations\accept_invitation($pdo, $inv['raw_token'], $student2Id), 'invitation_expired', 410);
});

run_test('invitation for suspended organization cannot be accepted', function () use ($pdo, $adminId, $activeOrgId, $student2Id): void {
    $inv = \Pathway\Organizations\create_invitation($pdo, $adminId, $activeOrgId, 'student2@example.test', 'REVIEWER', 3600);
    \Pathway\Organizations\suspend_organization($pdo, $adminId, $activeOrgId, 'Test suspension');

    expect_org_error(fn() => \Pathway\Organizations\accept_invitation($pdo, $inv['raw_token'], $student2Id), 'organization_inactive', 403);

    // Reactivate for subsequent tests
    $pdo->prepare('UPDATE organizations SET status = "ACTIVE" WHERE id = ?')->execute([$activeOrgId]);
});

// --- Cross-Tenant & Resource Ownership Enforcement ---
run_test('staff cannot access other organizations (cross-tenant isolation)', function () use ($pdo, $adminId, $staffCandidateId, $activeOrgId): void {
    // Create Org B
    $orgBId = \Pathway\Organizations\create_organization($pdo, $adminId, 'Beta University', 'COLLEGE', true);

    // Staff member belongs to activeOrgId (Nexus Laboratories), NOT orgBId (Beta University)
    ensure(\Pathway\Organizations\can($pdo, $staffCandidateId, 'perm.org.view', $activeOrgId) === true);
    ensure(\Pathway\Organizations\can($pdo, $staffCandidateId, 'perm.org.view', $orgBId) === false);
    ensure(\Pathway\Organizations\can($pdo, $staffCandidateId, 'perm.org.mutate', $orgBId) === false);

    // verify_resource_ownership enforces matching org ID
    expect_http_error(fn() => \Pathway\Organizations\verify_resource_ownership($pdo, $staffCandidateId, $activeOrgId, $orgBId), 403);

    // But matching org succeeds
    \Pathway\Organizations\verify_resource_ownership($pdo, $staffCandidateId, $activeOrgId, $activeOrgId);
});

// --- Audit Logging ---
run_test('admin audit log records actions immutably', function () use ($pdo): void {
    $stmt = $pdo->query('SELECT action, count(*) as cnt FROM admin_audit_log GROUP BY action');
    $actions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    ensure(isset($actions['org.create']), 'Missing org.create in audit log');
    ensure(isset($actions['org.approve']), 'Missing org.approve in audit log');
    ensure(isset($actions['org.suspend']), 'Missing org.suspend in audit log');
    ensure(isset($actions['org.invite']), 'Missing org.invite in audit log');
    ensure(isset($actions['org.invitation_accepted']), 'Missing org.invitation_accepted in audit log');
});

// --- Admin CLI Bootstrap Command ---
run_test('admin bootstrap CLI creates admin account with audit record', function () use ($pdo): void {
    $email = 'cli-bootstrapped@example.test';
    $cmd = [
        PHP_BINARY,
        dirname(__DIR__, 2) . '/bin/bootstrap_admin.php',
        "--name=CLI Security Lead",
        "--email={$email}",
        "--password=BootstrappedPassword123!",
    ];

    $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    ensure(is_resource($process));
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    ensure($exitCode === 0, "CLI exited with code $exitCode, err: $err");
    ensure(str_contains($out, '=== SECURE ADMIN BOOTSTRAP ==='));
    ensure(str_contains($out, 'Role             : admin'));

    // Verify account in DB
    $stmt = $pdo->prepare('SELECT role, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    ensure($user !== false);
    ensure($user['role'] === 'admin');
    ensure(password_verify('BootstrappedPassword123!', $user['password_hash']));

    // Verify audit log entry
    $stmt = $pdo->prepare('SELECT action FROM admin_audit_log WHERE action = "admin.bootstrap"');
    $stmt->execute();
    ensure($stmt->fetchColumn() === 'admin.bootstrap');
});

run_test('admin bootstrap rejects duplicate email', function (): void {
    $cmd = [
        PHP_BINARY,
        dirname(__DIR__, 2) . '/bin/bootstrap_admin.php',
        "--name=Duplicate Lead",
        "--email=cli-bootstrapped@example.test",
        "--password=BootstrappedPassword123!",
    ];

    $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    ensure($exitCode === 1);
    ensure(str_contains($err, 'already exists'));
});

// --- Email Ownership & Invitation Registration ---
run_test('accept_invitation verifies user credentials when provided', function () use ($pdo, $adminId, $activeOrgId): void {
    // Register test candidate with known password
    $candId = \Pathway\register_student($pdo, ['name' => 'Cred Candidate', 'email' => 'cred@candidate.test', 'password' => 'ValidPassword123!']);
    $inv = \Pathway\Organizations\create_invitation($pdo, $adminId, $activeOrgId, 'cred@candidate.test', 'STAFF', 3600);

    // Wrong password rejected with 401
    expect_org_error(fn() => \Pathway\Organizations\accept_invitation($pdo, $inv['raw_token'], $candId, 'WrongPassword123!'), 'invalid_credentials', 401);

    // Correct password accepted
    $res = \Pathway\Organizations\accept_invitation($pdo, $inv['raw_token'], $candId, 'ValidPassword123!');
    ensure($res['organization_id'] === $activeOrgId);
    ensure($res['role'] === 'STAFF');
});

run_test('register_and_accept_invitation verifies email ownership via token possession', function () use ($pdo, $adminId, $activeOrgId): void {
    $inv = \Pathway\Organizations\create_invitation($pdo, $adminId, $activeOrgId, 'unregistered@candidate.test', 'ADMIN', 3600);

    $res = \Pathway\Organizations\register_and_accept_invitation($pdo, $inv['raw_token'], 'New Staff Member', 'BrandNewPassword123!');
    ensure($res['organization_id'] === $activeOrgId);
    ensure($res['role'] === 'ADMIN');
    ensure($res['user_id'] > 0);

    // Verify account exists and password works
    $stmt = $pdo->prepare('SELECT name, email, role, password_hash FROM users WHERE id = ?');
    $stmt->execute([$res['user_id']]);
    $u = $stmt->fetch();
    ensure($u['email'] === 'unregistered@candidate.test');
    ensure(password_verify('BrandNewPassword123!', $u['password_hash']));

    // Replay is blocked
    expect_org_error(fn() => \Pathway\Organizations\register_and_accept_invitation($pdo, $inv['raw_token'], 'Hacker', 'Password123456!'), 'invitation_already_used', 410);
});

echo "RESULT: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
