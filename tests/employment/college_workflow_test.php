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
$testDir = sys_get_temp_dir() . '/pathway-college-tests-' . bin2hex(random_bytes(8));
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

echo "=== CHECKPOINT 007A: COLLEGE WORKFLOW & CONSENT-BASED PRIVACY TESTS ===\n";

$adminId = 0;
$companyStaffId = 0;
$collegeAId = 0;
$collegeBId = 0;
$companyId = 0;
$collegeAStaffId = 0;
$collegeBStaffId = 0;
$studentAliceId = 0;
$studentBobId = 0;
$studentCharlieId = 0;
$jobId = 0;

test_case('bootstrap admin and seed company and job', function () use ($pdo, &$adminId, &$companyStaffId, &$companyId, &$jobId) {
    $now = time();
    $hash = password_hash('CorrectPassword123!', PASSWORD_DEFAULT);

    // Platform Admin
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'admin', ?)");
    $stmt->execute(['Platform Admin', 'admin@pathway.test', $hash, $now]);
    $adminId = (int) $pdo->lastInsertId();

    // Company Staff
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'company', ?)");
    $stmt->execute(['Recruiter John', 'recruiter@techcorp.test', $hash, $now]);
    $companyStaffId = (int) $pdo->lastInsertId();

    // Create an approved company
    $companyId = Organizations\create_organization($pdo, $adminId, 'TechCorp Labs', 'COMPANY');
    Organizations\approve_organization($pdo, $adminId, $companyId);

    // Add company staff membership
    $stmt = $pdo->prepare("INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at) VALUES (?, ?, 'ADMIN', 'ACTIVE', ?, ?)");
    $stmt->execute([$companyId, $companyStaffId, $now, $now]);

    $jobId = Employment\create_job($pdo, $companyStaffId, $companyId, [
        'title' => 'Software Engineering Intern',
        'description' => 'Great role for undergraduates in computer science.',
        'skills' => 'PHP, SQLite, JavaScript',
        'role_category' => 'backend',
        'location' => 'Bengaluru',
        'status' => 'active',
    ]);
    check($jobId > 0, 'Job created');
});

test_case('college organization registration starts in PENDING status', function () use ($pdo, $adminId, &$collegeAId) {
    $collegeAId = Organizations\create_organization($pdo, $adminId, 'Dayananda Sagar Academy (DSATM)', 'COLLEGE');
    check($collegeAId > 0, 'College created');

    $stmt = $pdo->prepare('SELECT status, type FROM organizations WHERE id = ?');
    $stmt->execute([$collegeAId]);
    $row = $stmt->fetch();
    check($row['status'] === 'PENDING', 'College starts pending');
    check($row['type'] === 'COLLEGE', 'College type is COLLEGE');
});

test_case('unapproved college is not included in list_approved_colleges', function () use ($pdo, $collegeAId) {
    $approved = Employment\list_approved_colleges($pdo);
    $ids = array_column($approved, 'id');
    check(!in_array($collegeAId, $ids, true), 'Pending college must not appear in approved list');
});

test_case('platform admin approves college organization', function () use ($pdo, $collegeAId, $adminId) {
    Organizations\approve_organization($pdo, $adminId, $collegeAId);

    $stmt = $pdo->prepare('SELECT status FROM organizations WHERE id = ?');
    $stmt->execute([$collegeAId]);
    check($stmt->fetchColumn() === 'ACTIVE', 'College status is active');

    $list = Employment\list_approved_colleges($pdo);
    $ids = array_column($list, 'id');
    check(in_array($collegeAId, $ids, true), 'Active college appears in approved list');
});

test_case('placement staff invitation and onboarding maps to college role', function () use ($pdo, $collegeAId, $adminId, &$collegeAStaffId) {
    $inv = Organizations\create_invitation($pdo, $adminId, $collegeAId, 'officer.dsatm@eidolon.test', 'STAFF');
    check(!empty($inv['raw_token']), 'Invitation token created');

    $accepted = Organizations\register_and_accept_invitation(
        $pdo,
        $inv['raw_token'],
        'Officer DSATM',
        'SecurePlacementPass#2026'
    );
    $collegeAStaffId = (int) $accepted['user_id'];
    check($collegeAStaffId > 0, 'Staff user created');

    $userStmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $userStmt->execute([$collegeAStaffId]);
    $role = $userStmt->fetchColumn();
    check($role === 'college', "Staff user has college role, got: $role");

    $collegeOrg = Employment\get_user_college_organization($pdo, $collegeAStaffId);
    check($collegeOrg !== null, 'Found college organization for user');
    check((int) $collegeOrg['org_id'] === $collegeAId, 'College ID matches');
    check($collegeOrg['org_status'] === 'ACTIVE', 'College is active');
});

test_case('student registration and association with college with consent withheld', function () use ($pdo, $collegeAId, &$studentAliceId) {
    $now = time();
    $hash = password_hash('StudentPass#2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Alice Private', 'alice.private@student.test', $hash, 'student', $now]);
    $studentAliceId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, college, skills, github_username, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$studentAliceId, 'DSATM Bengaluru', 'Python, Go, Security', 'alice-priv', $now]);

    Employment\save_profile($pdo, $studentAliceId, [
        'college' => 'DSATM Bengaluru',
        'skills' => 'Python, Go, Security',
        'bio' => 'Focused on systems security.',
        'college_org_id' => $collegeAId,
        'college_share_consent' => 0, // Explicitly withheld
    ]);

    $profile = Employment\get_profile($pdo, $studentAliceId);
    check((int) $profile['college_org_id'] === $collegeAId, 'College org linked');
    check((int) $profile['college_share_consent'] === 0, 'Consent withheld recorded');
});

test_case('student registration and association with college with consent granted', function () use ($pdo, $collegeAId, $jobId, &$studentBobId) {
    $now = time();
    $hash = password_hash('StudentPass#2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Bob Consenting', 'bob.consenting@student.test', $hash, 'student', $now]);
    $studentBobId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, college, skills, github_username, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$studentBobId, 'DSATM Bengaluru', 'Full-Stack, React, PHP', 'bob-dev', $now]);

    Employment\save_profile($pdo, $studentBobId, [
        'college' => 'DSATM Bengaluru',
        'skills' => 'Full-Stack, React, PHP',
        'bio' => 'Aspiring web developer.',
        'college_org_id' => $collegeAId,
        'college_share_consent' => 1, // Consent granted
    ]);

    // Student Bob also creates a resume and applies to a job
    $resumeId = Employment\save_resume($pdo, $studentBobId, [
        'title' => 'Bob Full Stack Resume',
        'summary' => 'Full stack engineer with project experience.',
        'skills' => ['PHP', 'JavaScript', 'HTML'],
    ]);
    check($resumeId > 0, 'Resume created');

    $appId = Employment\apply_for_job($pdo, $studentBobId, $jobId, 'Very interested in this role.');
    check($appId > 0, 'Application submitted');

    $profile = Employment\get_profile($pdo, $studentBobId);
    check((int) $profile['college_share_consent'] === 1, 'Consent granted recorded');
});

test_case('college placement officer lists students with consent-aware masking', function () use ($pdo, $collegeAId, $collegeAStaffId, $studentAliceId, $studentBobId) {
    $students = Employment\list_college_students($pdo, $collegeAId, $collegeAStaffId);
    check(count($students) === 2, 'Two students listed');

    $byName = [];
    foreach ($students as $s) {
        $byName[$s['student_name']] = $s;
    }

    // Bob granted consent
    check(isset($byName['Bob Consenting']), 'Bob present in roster');
    check($byName['Bob Consenting']['student_email'] === 'bob.consenting@student.test', 'Bob email is visible');
    check((int) $byName['Bob Consenting']['college_share_consent'] === 1, 'Bob consent is 1');
    check((int) $byName['Bob Consenting']['applications_count'] === 1, 'Bob application count visible');
    check((int) $byName['Bob Consenting']['resumes_count'] === 1, 'Bob resume count visible');

    // Alice withheld consent
    check(isset($byName['Alice Private']), 'Alice present in roster');
    check($byName['Alice Private']['student_email'] === '[Consent Withheld]', 'Alice email is masked');
    check((int) $byName['Alice Private']['college_share_consent'] === 0, 'Alice consent is 0');
    check((int) $byName['Alice Private']['applications_count'] === 0, 'Alice application count masked to 0');
    check((int) $byName['Alice Private']['resumes_count'] === 0, 'Alice resume count masked to 0');
});

test_case('college officer viewing consenting student placement details succeeds', function () use ($pdo, $collegeAId, $collegeAStaffId, $studentBobId) {
    $details = Employment\get_college_student_placement_details($pdo, $collegeAId, $studentBobId, $collegeAStaffId);
    check($details['student']['student_name'] === 'Bob Consenting', 'Student name matches');
    check($details['student']['student_email'] === 'bob.consenting@student.test', 'Email matches');
    check(count($details['applications']) === 1, 'Applications visible');
    check($details['applications'][0]['job_title'] === 'Software Engineering Intern', 'Job title matches');
    check($details['resume'] !== null, 'Latest resume summary present');
    check($details['resume']['title'] === 'Bob Full Stack Resume', 'Resume title matches');
});

test_case('college officer viewing non-consenting student placement details is rejected with 403', function () use ($pdo, $collegeAId, $collegeAStaffId, $studentAliceId) {
    $rejected = false;
    try {
        Employment\get_college_student_placement_details($pdo, $collegeAId, $studentAliceId, $collegeAStaffId);
    } catch (Pathway\Employment\EmploymentError $e) {
        if ($e->statusCode === 403 && $e->reason === 'student_consent_required') {
            $rejected = true;
        }
    }
    check($rejected, 'Access to non-consenting student details rejected with 403 student_consent_required');
});

test_case('student grants consent dynamically and officer can then view details', function () use ($pdo, $collegeAId, $collegeAStaffId, $studentAliceId) {
    // Alice changes mind and enables consent
    Employment\save_profile($pdo, $studentAliceId, [
        'college_share_consent' => 1,
    ]);

    $details = Employment\get_college_student_placement_details($pdo, $collegeAId, $studentAliceId, $collegeAStaffId);
    check($details['student']['student_name'] === 'Alice Private', 'Alice details now accessible');
    check($details['student']['student_email'] === 'alice.private@student.test', 'Alice email now visible');
});

test_case('cross-college tenant isolation blocks foreign student lookup', function () use ($pdo, $adminId, $collegeAId, $collegeAStaffId, &$collegeBId, &$collegeBStaffId, &$studentCharlieId) {
    // Create College B and staff
    $collegeBId = Organizations\create_organization($pdo, $adminId, 'Bangalore Institute of Tech', 'COLLEGE');
    Organizations\approve_organization($pdo, $adminId, $collegeBId);

    $invB = Organizations\create_invitation($pdo, $adminId, $collegeBId, 'officer.bit@eidolon.test', 'STAFF');
    $acceptedB = Organizations\register_and_accept_invitation($pdo, $invB['raw_token'], 'Officer BIT', 'BITPlacementPass#2026');
    $collegeBStaffId = (int) $acceptedB['user_id'];

    // Create student Charlie associated with College B
    $now = time();
    $hash = password_hash('StudentPass#2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Charlie BIT', 'charlie.bit@student.test', $hash, 'student', $now]);
    $studentCharlieId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, college, skills, github_username, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$studentCharlieId, 'BIT Bengaluru', 'Cloud, Docker', 'charlie-dev', $now]);

    Employment\save_profile($pdo, $studentCharlieId, [
        'college' => 'BIT Bengaluru',
        'college_org_id' => $collegeBId,
        'college_share_consent' => 1,
    ]);

    // College A officer tries to view Charlie under College A -> rejected (student_not_found)
    $rejectedNotFound = false;
    try {
        Employment\get_college_student_placement_details($pdo, $collegeAId, $studentCharlieId, $collegeAStaffId);
    } catch (Pathway\Employment\EmploymentError $e) {
        if ($e->statusCode === 404 && $e->reason === 'student_not_found') {
            $rejectedNotFound = true;
        }
    }
    check($rejectedNotFound, 'Lookup of foreign student under wrong college returns 404');

    // College A officer tries to view Charlie by passing College B ID -> rejected (unauthorized_college_action)
    $rejectedUnauthorized = false;
    try {
        Employment\get_college_student_placement_details($pdo, $collegeBId, $studentCharlieId, $collegeAStaffId);
    } catch (Pathway\Employment\EmploymentError $e) {
        if ($e->statusCode === 403 && $e->reason === 'unauthorized_college_action') {
            $rejectedUnauthorized = true;
        }
    }
    check($rejectedUnauthorized, 'Officer querying another college ID returns 403 unauthorized');

    // College A officer tries to list students of College B -> rejected (unauthorized_college_action)
    $rejectedList = false;
    try {
        Employment\list_college_students($pdo, $collegeBId, $collegeAStaffId);
    } catch (Pathway\Employment\EmploymentError $e) {
        if ($e->statusCode === 403 && $e->reason === 'unauthorized_college_action') {
            $rejectedList = true;
        }
    }
    check($rejectedList, 'Officer listing another college roster returns 403 unauthorized');
});

test_case('inactive or suspended college blocks placement officer', function () use ($pdo, $collegeBId, $adminId, $collegeBStaffId) {
    Organizations\suspend_organization($pdo, $adminId, $collegeBId, 'Annual review required');

    $_SESSION['user_id'] = $collegeBStaffId;
    $_SESSION['role'] = 'college';

    $blocked = false;
    try {
        Employment\current_college_staff($pdo);
    } catch (Pathway\HttpError $e) {
        if ($e->status === 403 && str_contains($e->getMessage(), 'suspended')) {
            $blocked = true;
        }
    }
    check($blocked, 'Suspended college blocks current_college_staff with 403');
    unset($_SESSION['user_id'], $_SESSION['role']);
});

test_case('student or company role cannot access current_college_staff', function () use ($pdo, $studentAliceId) {
    $_SESSION['user_id'] = $studentAliceId;
    $_SESSION['role'] = 'student';

    $blocked = false;
    try {
        Employment\current_college_staff($pdo);
    } catch (Pathway\HttpError $e) {
        if ($e->status === 403) {
            $blocked = true;
        }
    }
    check($blocked, 'Student role cannot access current_college_staff');
    unset($_SESSION['user_id'], $_SESSION['role']);
});

echo "====================================================================\n";
echo "RESULT: $passed passed, $failed failed\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
