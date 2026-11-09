<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/organizations/organization.php';
require_once dirname(__DIR__, 2) . '/app/employment/jobs.php';

use Pathway\Organizations;
use Pathway\Employment;

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function test_case(string $name, \Closure $callback): void
{
    global $passed, $failed;
    try {
        $callback();
        $passed++;
        echo "PASS $name\n";
    } catch (\Throwable $error) {
        $failed++;
        echo "FAIL $name (" . get_class($error) . ": " . $error->getMessage() . ")\n";
    }
}

// Temporary isolated SQLite database for unit testing
$testDir = sys_get_temp_dir() . '/pathway-company-tests-' . bin2hex(random_bytes(8));
mkdir($testDir, 0700);
$dbPath = $testDir . '/test.sqlite';

$pdo = new \PDO('sqlite:' . $dbPath);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
\Pathway\initialize_database($pdo);

register_shutdown_function(function () use ($testDir, $dbPath) {
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
    if (is_dir($testDir)) {
        rmdir($testDir);
    }
});

echo "=== CHECKPOINT 006B: COMPANY WORKFLOW & ADMIN APPROVAL TESTS ===\n";

$adminId = 0;
$companyAStaffId = 0;
$companyBStaffId = 0;
$studentAliceId = 0;
$companyAId = 0;
$companyBId = 0;
$jobA1Id = 0;
$applicationId = 0;

test_case('bootstrap admin and seed initial test accounts', function () use ($pdo, &$adminId, &$companyAStaffId, &$companyBStaffId, &$studentAliceId) {
    $now = time();
    $hash = password_hash('CorrectPassword123!', PASSWORD_DEFAULT);

    // Platform Admin
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'admin', ?)");
    $stmt->execute(['Platform Admin', 'admin@pathway.test', $hash, $now]);
    $adminId = (int) $pdo->lastInsertId();

    // Company A Staff
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'company', ?)");
    $stmt->execute(['Acme Recruiter', 'recruiter@acme.test', $hash, $now]);
    $companyAStaffId = (int) $pdo->lastInsertId();

    // Company B Staff
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'company', ?)");
    $stmt->execute(['Beta Talent', 'talent@beta.test', $hash, $now]);
    $companyBStaffId = (int) $pdo->lastInsertId();

    // Student Alice
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'student', ?)");
    $stmt->execute(['Alice Student', 'alice@student.test', $hash, $now]);
    $studentAliceId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, college, skills, github_username, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$studentAliceId, 'MIT', 'PHP 8.4, SQLite, REST', 'alice-dev', $now]);

    check($adminId > 0 && $companyAStaffId > 0 && $companyBStaffId > 0 && $studentAliceId > 0, 'All accounts seeded');
});

test_case('company A registration creates organization in PENDING state', function () use ($pdo, $companyAStaffId, &$companyAId) {
    $companyAId = Organizations\create_organization($pdo, $companyAStaffId, 'Acme Technologies', 'COMPANY');
    check($companyAId > 0, 'Company A created');

    $stmt = $pdo->prepare('SELECT status, type, name FROM organizations WHERE id = ?');
    $stmt->execute([$companyAId]);
    $org = $stmt->fetch();
    check($org['status'] === 'PENDING', 'Organization starts in PENDING status');
    check($org['type'] === 'COMPANY', 'Type is COMPANY');
});

test_case('unapproved PENDING company cannot post jobs', function () use ($pdo, $companyAStaffId, $companyAId) {
    try {
        Employment\create_job($pdo, $companyAStaffId, $companyAId, [
            'title' => 'Junior Backend Engineer',
            'description' => 'Work on SQLite systems and PHP 8.4 services.',
            'skills' => 'PHP, SQLite',
            'role_category' => 'backend',
            'location' => 'Remote',
            'status' => 'active',
        ]);
        throw new \RuntimeException('Expected job creation to fail for unapproved company');
    } catch (Employment\EmploymentError $e) {
        check($e->statusCode === 403, 'Fails with 403 Forbidden');
        check(in_array($e->reason, ['unauthorized_company_action', 'verified_active_company_required'], true), 'Correct reason code');
    }
});

test_case('platform admin lists pending organizations and approves Company A', function () use ($pdo, $adminId, $companyAId) {
    $pending = Organizations\list_organizations($pdo, 'PENDING');
    check(count($pending) >= 1, 'Pending organization listed');
    check((int) $pending[0]['id'] === $companyAId, 'Company A is in pending list');

    Organizations\approve_organization($pdo, $adminId, $companyAId);

    $stmt = $pdo->prepare('SELECT status FROM organizations WHERE id = ?');
    $stmt->execute([$companyAId]);
    $status = $stmt->fetchColumn();
    check($status === 'ACTIVE', 'Company A is now ACTIVE');

    $active = Organizations\list_organizations($pdo, 'ACTIVE');
    check(count($active) >= 1, 'Company A listed in active list');
});

test_case('approved Company A staff can successfully create and publish job', function () use ($pdo, $companyAStaffId, $companyAId, &$jobA1Id) {
    $jobA1Id = Employment\create_job($pdo, $companyAStaffId, $companyAId, [
        'title' => 'Junior Backend Engineer',
        'description' => 'Work on SQLite systems and PHP 8.4 services.',
        'skills' => 'PHP, SQLite, REST',
        'role_category' => 'backend',
        'location' => 'San Francisco, CA',
        'status' => 'active',
    ]);
    check($jobA1Id > 0, 'Job A1 created');

    $job = Employment\get_job($pdo, $jobA1Id);
    check($job['title'] === 'Junior Backend Engineer', 'Job title matches');
    check($job['company_status'] === 'ACTIVE', 'Company status is ACTIVE');
});

test_case('Company A staff can update existing job', function () use ($pdo, $companyAStaffId, $companyAId, $jobA1Id) {
    Employment\update_job($pdo, $companyAStaffId, $companyAId, $jobA1Id, [
        'title' => 'Senior Backend Engineer (PHP 8.4)',
        'description' => 'Architect modern SQLite and PHP services with zero external daemon dependencies.',
        'skills' => 'PHP 8.4, SQLite, Architecture',
        'role_category' => 'backend',
        'location' => 'Remote / US',
        'status' => 'active',
    ]);

    $job = Employment\get_job($pdo, $jobA1Id);
    check($job['title'] === 'Senior Backend Engineer (PHP 8.4)', 'Job title updated');
    check($job['location'] === 'Remote / US', 'Location updated');
});

test_case('create and approve Company B for multi-tenant isolation tests', function () use ($pdo, $adminId, $companyBStaffId, &$companyBId) {
    $companyBId = Organizations\create_organization($pdo, $companyBStaffId, 'Beta Corp', 'COMPANY');
    Organizations\approve_organization($pdo, $adminId, $companyBId);

    $stmt = $pdo->prepare('SELECT status FROM organizations WHERE id = ?');
    $stmt->execute([$companyBId]);
    check($stmt->fetchColumn() === 'ACTIVE', 'Company B is ACTIVE');
});

test_case('cross-tenant job update: Company B cannot update Company A job', function () use ($pdo, $companyBStaffId, $companyBId, $jobA1Id) {
    try {
        Employment\update_job($pdo, $companyBStaffId, $companyBId, $jobA1Id, [
            'title' => 'Hijacked Title',
            'description' => 'Malicious update attempt from tenant B',
            'skills' => 'Hacking',
            'role_category' => 'backend',
            'location' => 'Unknown',
            'status' => 'active',
        ]);
        throw new \RuntimeException('Expected cross-tenant job update to be rejected');
    } catch (Employment\EmploymentError $e) {
        check($e->statusCode === 404, 'Job not found in tenant B');
        check($e->reason === 'job_not_found', 'Correct reason code');
    }

    $job = Employment\get_job($pdo, $jobA1Id);
    check($job['title'] === 'Senior Backend Engineer (PHP 8.4)', 'Company A job remained unchanged');
});

test_case('student Alice applies for Company A job', function () use ($pdo, $studentAliceId, $jobA1Id, &$applicationId) {
    $applicationId = Employment\apply_for_job($pdo, $studentAliceId, $jobA1Id, 'Excited to contribute to EIDOLON PATHWAY!');
    check($applicationId > 0, 'Application submitted');

    $studentApps = Employment\list_student_applications($pdo, $studentAliceId);
    check(count($studentApps) === 1, 'Alice has 1 application');
    check($studentApps[0]['status'] === 'applied', 'Initial status is applied');
    check($studentApps[0]['company_name'] === 'Acme Technologies', 'Applied to Acme');
});

test_case('Company A staff can list applicants and sees Alice', function () use ($pdo, $companyAStaffId, $companyAId, $applicationId) {
    $applicants = Employment\list_company_applications($pdo, $companyAStaffId, $companyAId);
    check(count($applicants) === 1, 'Company A sees 1 applicant');
    check((int) $applicants[0]['application_id'] === $applicationId, 'Application ID matches');
    check($applicants[0]['student_name'] === 'Alice Student', 'Candidate name matches');
    check($applicants[0]['student_email'] === 'alice@student.test', 'Candidate email matches');
    check($applicants[0]['github_username'] === 'alice-dev', 'GitHub username matches');
    check($applicants[0]['college'] === 'MIT', 'College matches');
});

test_case('cross-tenant applicant access: Company B cannot see Company A applicants', function () use ($pdo, $companyBStaffId, $companyBId) {
    $betaApplicants = Employment\list_company_applications($pdo, $companyBStaffId, $companyBId);
    check(count($betaApplicants) === 0, 'Company B sees 0 applicants');

    // Trying to view Company A applicants with Company B staff credentials
    try {
        Employment\list_company_applications($pdo, $companyBStaffId, 9999);
        throw new \RuntimeException('Expected unauthorized access');
    } catch (Employment\EmploymentError $e) {
        check($e->statusCode === 403, 'Denied 403');
    }
});

test_case('Company A shortlists Alice and updates review decision', function () use ($pdo, $companyAStaffId, $companyAId, $applicationId) {
    Employment\update_application_status($pdo, $companyAStaffId, $companyAId, $applicationId, 'shortlisted');

    $applicants = Employment\list_company_applications($pdo, $companyAStaffId, $companyAId);
    check($applicants[0]['status'] === 'shortlisted', 'Status in company view is shortlisted');
});

test_case('student Alice sees updated shortlisted status', function () use ($pdo, $studentAliceId) {
    $studentApps = Employment\list_student_applications($pdo, $studentAliceId);
    check($studentApps[0]['status'] === 'shortlisted', 'Alice sees shortlisted status');
});

test_case('cross-tenant status update rejected: Company B cannot modify Company A application', function () use ($pdo, $companyBStaffId, $companyBId, $applicationId) {
    try {
        Employment\update_application_status($pdo, $companyBStaffId, $companyBId, $applicationId, 'rejected');
        throw new \RuntimeException('Expected cross-tenant status update to fail');
    } catch (Employment\EmploymentError $e) {
        check($e->statusCode === 404, 'Returns 404 application_not_found');
        check($e->reason === 'application_not_found', 'Correct reason code');
    }

    // Verify application status remained shortlisted
    $stmt = $pdo->prepare('SELECT status FROM applications WHERE id = ?');
    $stmt->execute([$applicationId]);
    check($stmt->fetchColumn() === 'shortlisted', 'Application remained shortlisted');
});

test_case('platform admin suspends Company A with reason', function () use ($pdo, $adminId, $companyAId) {
    Organizations\suspend_organization($pdo, $adminId, $companyAId, 'Terms of service review');

    $stmt = $pdo->prepare('SELECT status FROM organizations WHERE id = ?');
    $stmt->execute([$companyAId]);
    check($stmt->fetchColumn() === 'SUSPENDED', 'Company A is now SUSPENDED');
});

test_case('suspended company cannot post new jobs or mutate resources', function () use ($pdo, $companyAStaffId, $companyAId) {
    try {
        Employment\create_job($pdo, $companyAStaffId, $companyAId, [
            'title' => 'Post While Suspended',
            'description' => 'Should not be allowed to post while suspended.',
            'skills' => 'PHP',
            'role_category' => 'backend',
            'location' => 'Remote',
            'status' => 'active',
        ]);
        throw new \RuntimeException('Expected job creation to fail for suspended company');
    } catch (Employment\EmploymentError $e) {
        check($e->statusCode === 403, 'Rejected 403');
    }
});

test_case('admin audit log records all lifecycle events immutably', function () use ($pdo) {
    $logs = Organizations\list_audit_logs($pdo, 50);
    check(count($logs) >= 5, 'At least 5 audit events recorded');

    $actions = array_column($logs, 'action');
    check(in_array('org.create', $actions, true), 'Audit contains org.create');
    check(in_array('org.approve', $actions, true), 'Audit contains org.approve');
    check(in_array('job.create', $actions, true), 'Audit contains job.create');
    check(in_array('job.update', $actions, true), 'Audit contains job.update');
    check(in_array('application.status_update', $actions, true), 'Audit contains application.status_update');
    check(in_array('org.suspend', $actions, true), 'Audit contains org.suspend');
});

echo "RESULT: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
