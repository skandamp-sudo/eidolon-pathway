<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/organizations/organization.php';
require dirname(__DIR__, 2) . '/app/employment/jobs.php';

use Pathway\Employment;
use Pathway\Organizations;

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

function expect_employment_error(callable $call, string $reason, ?int $status = null): void
{
    try {
        $call();
    } catch (Employment\EmploymentError $error) {
        ensure($error->reason === $reason, "Expected reason {$reason}, got {$error->reason}");
        if ($status !== null) {
            ensure($error->statusCode === $status, "Expected status {$status}, got {$error->statusCode}");
        }
        return;
    }
    throw new RuntimeException("Expected EmploymentError: {$reason}");
}

// Temporary SQLite DB
$dir = sys_get_temp_dir() . '/pathway-emp-test-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
putenv('PATHWAY_DATA_DIR=' . $dir);
register_shutdown_function(function () use ($dir): void {
    foreach (glob($dir . '/*') as $file) {
        if (is_file($file)) { unlink($file); }
    }
    @rmdir($dir);
});

$pdo = \Pathway\database(true);
\Pathway\initialize_database($pdo);

echo "=== MISSION 005: EMPLOYMENT & PROFILE INTEGRATION TESTS ===\n";

// Helper to create a user
function create_test_user(\PDO $pdo, string $name, string $email, string $role): int
{
    $hash = password_hash('password123456', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, $hash, $role]);
    $userId = (int) $pdo->lastInsertId();
    if ($role === 'student') {
        $stmt = $pdo->prepare('INSERT INTO student_profiles (user_id) VALUES (?)');
        $stmt->execute([$userId]);
    }
    return $userId;
}

$studentA = create_test_user($pdo, 'Student Alice', 'alice@test.edu', 'student');
$studentB = create_test_user($pdo, 'Student Bob', 'bob@test.edu', 'student');
$companyUserA = create_test_user($pdo, 'Acme Recruiter', 'recruiter@acme.test', 'company');
$companyUserB = create_test_user($pdo, 'Globex Recruiter', 'recruiter@globex.test', 'company');
$platformAdmin = create_test_user($pdo, 'Admin User', 'admin@pathway.test', 'admin');

// --- 1. Student Profile Tests ---

run_test('get_profile returns null for non-existent user', function () use ($pdo): void {
    ensure(Employment\get_profile($pdo, 99999) === null);
});

run_test('get_profile returns initial empty profile for registered student', function () use ($pdo, $studentA): void {
    $prof = Employment\get_profile($pdo, $studentA);
    ensure($prof !== null);
    ensure($prof['user_id'] === $studentA);
    ensure($prof['name'] === 'Student Alice');
    ensure($prof['college'] === '');
    ensure($prof['skills'] === '');
    ensure($prof['bio'] === '');
});

run_test('save_profile updates student profile with valid data', function () use ($pdo, $studentA): void {
    $updated = Employment\save_profile($pdo, $studentA, [
        'college' => 'MIT',
        'skills' => 'PHP, SQLite, JavaScript, Python',
        'bio' => 'Aspiring systems engineer focused on verifiable security.',
        'github_username' => 'alice-coder',
    ]);
    ensure($updated['college'] === 'MIT');
    ensure($updated['skills'] === 'PHP, SQLite, JavaScript, Python');
    ensure($updated['bio'] === 'Aspiring systems engineer focused on verifiable security.');
    ensure($updated['github_username'] === 'alice-coder');
    ensure($updated['updated_at'] > 0);
});

run_test('save_profile rejects non-student user', function () use ($pdo, $companyUserA): void {
    expect_employment_error(function () use ($pdo, $companyUserA): void {
        Employment\save_profile($pdo, $companyUserA, ['college' => 'Harvard']);
    }, 'student_required', 403);
});

run_test('save_profile rejects overlong college', function () use ($pdo, $studentA): void {
    expect_employment_error(function () use ($pdo, $studentA): void {
        Employment\save_profile($pdo, $studentA, ['college' => str_repeat('A', 201)]);
    }, 'college_too_long', 422);
});

run_test('save_profile rejects overlong skills', function () use ($pdo, $studentA): void {
    expect_employment_error(function () use ($pdo, $studentA): void {
        Employment\save_profile($pdo, $studentA, ['skills' => str_repeat('B', 501)]);
    }, 'skills_too_long', 422);
});

run_test('save_profile rejects overlong bio', function () use ($pdo, $studentA): void {
    expect_employment_error(function () use ($pdo, $studentA): void {
        Employment\save_profile($pdo, $studentA, ['bio' => str_repeat('C', 2001)]);
    }, 'bio_too_long', 422);
});

run_test('save_profile rejects invalid github_username', function () use ($pdo, $studentA): void {
    expect_employment_error(function () use ($pdo, $studentA): void {
        Employment\save_profile($pdo, $studentA, ['github_username' => '-invalid-start']);
    }, 'invalid_github_username', 422);

    expect_employment_error(function () use ($pdo, $studentA): void {
        Employment\save_profile($pdo, $studentA, ['github_username' => 'invalid!user']);
    }, 'invalid_github_username', 422);
});

run_test('save_profile rejects invalid college organization', function () use ($pdo, $studentA): void {
    expect_employment_error(function () use ($pdo, $studentA): void {
        Employment\save_profile($pdo, $studentA, ['college_org_id' => 8888]);
    }, 'invalid_college_organization', 422);
});

// --- 2. Organization Setup for Employment ---

// Create Company A (Active)
$orgAId = Organizations\create_organization($pdo, $companyUserA, 'Acme Corp', 'COMPANY');
Organizations\approve_organization($pdo, $platformAdmin, $orgAId);

// Create Company B (Active)
$orgBId = Organizations\create_organization($pdo, $companyUserB, 'Globex Industries', 'COMPANY');
Organizations\approve_organization($pdo, $platformAdmin, $orgBId);

// Create Company C (Pending - unapproved)
$companyUserC = create_test_user($pdo, 'Pending Corp Owner', 'owner@pending.test', 'company');
$orgCId = Organizations\create_organization($pdo, $companyUserC, 'Pending Startup', 'COMPANY');

// Create College Organization (Active)
$collegeUser = create_test_user($pdo, 'Dean Smith', 'dean@mit.test', 'company');
$collegeOrgId = Organizations\create_organization($pdo, $collegeUser, 'MIT College', 'COLLEGE');
Organizations\approve_organization($pdo, $platformAdmin, $collegeOrgId);

run_test('student profile accepts approved college organization', function () use ($pdo, $studentA, $collegeOrgId): void {
    $updated = Employment\save_profile($pdo, $studentA, ['college_org_id' => $collegeOrgId]);
    ensure($updated['college_org_id'] === $collegeOrgId);
    ensure($updated['college_org_name'] === 'MIT College');
});

// --- 3. Company Job Creation Tests ---

run_test('create_job rejects user without mutation permissions', function () use ($pdo, $studentA, $orgAId): void {
    expect_employment_error(function () use ($pdo, $studentA, $orgAId): void {
        Employment\create_job($pdo, $studentA, $orgAId, [
            'title' => 'Backend Intern',
            'description' => 'Great role building reliable APIs.',
            'skills' => 'PHP, SQLite',
        ]);
    }, 'unauthorized_company_action', 403);
});

run_test('create_job rejects unapproved pending company', function () use ($pdo, $companyUserC, $orgCId): void {
    expect_employment_error(function () use ($pdo, $companyUserC, $orgCId): void {
        Employment\create_job($pdo, $companyUserC, $orgCId, [
            'title' => 'Software Engineer',
            'description' => 'Early stage startup looking for talented devs.',
            'skills' => 'TypeScript, React',
        ]);
    }, 'unauthorized_company_action', 403);
});

run_test('create_job rejects college organization posting jobs', function () use ($pdo, $collegeUser, $collegeOrgId): void {
    expect_employment_error(function () use ($pdo, $collegeUser, $collegeOrgId): void {
        Employment\create_job($pdo, $collegeUser, $collegeOrgId, [
            'title' => 'Research Assistant',
            'description' => 'Assist professors in distributed computing research.',
            'skills' => 'C++, Rust',
        ]);
    }, 'verified_active_company_required', 403);
});

$jobA1 = 0;
run_test('create_job succeeds for authorized company member and logs audit', function () use ($pdo, $companyUserA, $orgAId, &$jobA1): void {
    $jobA1 = Employment\create_job($pdo, $companyUserA, $orgAId, [
        'title' => 'Backend Engineering Intern',
        'description' => 'Build high-performance SQLite storage engines and REST endpoints.',
        'skills' => 'PHP, SQLite, Git, Linux',
        'role_category' => 'backend',
        'location' => 'Remote / San Francisco',
    ]);
    ensure($jobA1 > 0);

    // Verify audit log
    $auditStmt = $pdo->prepare("SELECT * FROM admin_audit_log WHERE action = 'job.create' AND target_id = ?");
    $auditStmt->execute([$jobA1]);
    $audit = $auditStmt->fetch();
    ensure($audit !== false);
    ensure($audit['actor_user_id'] === $companyUserA);
});

$jobA2 = 0;
run_test('create_job creates second job for Company A', function () use ($pdo, $companyUserA, $orgAId, &$jobA2): void {
    $jobA2 = Employment\create_job($pdo, $companyUserA, $orgAId, [
        'title' => 'Frontend UI/UX Intern',
        'description' => 'Design accessible, high-contrast dark interfaces.',
        'skills' => 'CSS, HTML5, Vanilla JS, A11y',
        'role_category' => 'frontend',
        'location' => 'Remote',
    ]);
    ensure($jobA2 > 0);
});

$jobB1 = 0;
run_test('create_job creates job for Company B', function () use ($pdo, $companyUserB, $orgBId, &$jobB1): void {
    $jobB1 = Employment\create_job($pdo, $companyUserB, $orgBId, [
        'title' => 'Fullstack Software Intern',
        'description' => 'Deliver complete end-to-end features for Globex cloud platform.',
        'skills' => 'Node.js, PostgreSQL, React',
        'role_category' => 'fullstack',
        'location' => 'New York, NY',
    ]);
    ensure($jobB1 > 0);
});

run_test('create_job rejects invalid title (<3 chars or >140 chars)', function () use ($pdo, $companyUserA, $orgAId): void {
    expect_employment_error(function () use ($pdo, $companyUserA, $orgAId): void {
        Employment\create_job($pdo, $companyUserA, $orgAId, [
            'title' => 'ab',
            'description' => 'Valid description for testing purposes.',
            'skills' => 'Testing',
        ]);
    }, 'invalid_job_title', 422);

    expect_employment_error(function () use ($pdo, $companyUserA, $orgAId): void {
        Employment\create_job($pdo, $companyUserA, $orgAId, [
            'title' => str_repeat('X', 141),
            'description' => 'Valid description for testing purposes.',
            'skills' => 'Testing',
        ]);
    }, 'invalid_job_title', 422);
});

run_test('create_job rejects invalid description (<10 chars)', function () use ($pdo, $companyUserA, $orgAId): void {
    expect_employment_error(function () use ($pdo, $companyUserA, $orgAId): void {
        Employment\create_job($pdo, $companyUserA, $orgAId, [
            'title' => 'Valid Job Title',
            'description' => 'Too short',
            'skills' => 'Testing',
        ]);
    }, 'invalid_job_description', 422);
});

run_test('create_job rejects invalid role category', function () use ($pdo, $companyUserA, $orgAId): void {
    expect_employment_error(function () use ($pdo, $companyUserA, $orgAId): void {
        Employment\create_job($pdo, $companyUserA, $orgAId, [
            'title' => 'Valid Job Title',
            'description' => 'Valid description for testing purposes.',
            'skills' => 'Testing',
            'role_category' => 'astronaut',
        ]);
    }, 'invalid_role_category', 422);
});

// --- 4. Job Catalog & Browsing Tests ---

run_test('list_active_jobs lists all active jobs from verified companies', function () use ($pdo): void {
    $jobs = Employment\list_active_jobs($pdo);
    ensure(count($jobs) === 3);
    ensure($jobs[0]['company_name'] !== '');
});

run_test('list_active_jobs filters by role_category', function () use ($pdo): void {
    $frontendJobs = Employment\list_active_jobs($pdo, 'frontend');
    ensure(count($frontendJobs) === 1);
    ensure($frontendJobs[0]['title'] === 'Frontend UI/UX Intern');

    $backendJobs = Employment\list_active_jobs($pdo, 'backend');
    ensure(count($backendJobs) === 1);
    ensure($backendJobs[0]['title'] === 'Backend Engineering Intern');
});

run_test('list_active_jobs searches by keyword', function () use ($pdo): void {
    $results = Employment\list_active_jobs($pdo, null, 'SQLite');
    ensure(count($results) === 1);
    ensure($results[0]['title'] === 'Backend Engineering Intern');

    $resultsCompany = Employment\list_active_jobs($pdo, null, 'Globex');
    ensure(count($resultsCompany) === 1);
    ensure($resultsCompany[0]['company_name'] === 'Globex Industries');
});

run_test('get_job returns job details with company status', function () use ($pdo, $jobA1): void {
    $job = Employment\get_job($pdo, $jobA1);
    ensure($job !== null);
    ensure($job['title'] === 'Backend Engineering Intern');
    ensure($job['company_name'] === 'Acme Corp');
    ensure($job['company_status'] === 'ACTIVE');
});

// --- 5. Application Submission & Duplicate Prevention Tests ---

$app1 = 0;
run_test('apply_for_job succeeds for student', function () use ($pdo, $studentA, $jobA1, &$app1): void {
    $app1 = Employment\apply_for_job($pdo, $studentA, $jobA1, 'Excited to contribute to SQLite optimization.');
    ensure($app1 > 0);
});

run_test('apply_for_job prevents duplicate application (atomic 409)', function () use ($pdo, $studentA, $jobA1): void {
    expect_employment_error(function () use ($pdo, $studentA, $jobA1): void {
        Employment\apply_for_job($pdo, $studentA, $jobA1, 'Second attempt should fail.');
    }, 'duplicate_application', 409);
});

run_test('apply_for_job rejects non-student', function () use ($pdo, $companyUserA, $jobB1): void {
    expect_employment_error(function () use ($pdo, $companyUserA, $jobB1): void {
        Employment\apply_for_job($pdo, $companyUserA, $jobB1);
    }, 'student_required', 403);
});

run_test('apply_for_job rejects non-existent job', function () use ($pdo, $studentB): void {
    expect_employment_error(function () use ($pdo, $studentB): void {
        Employment\apply_for_job($pdo, $studentB, 99999);
    }, 'job_not_available', 404);
});

$app2 = 0;
run_test('student B applies for job A1 and job B1', function () use ($pdo, $studentB, $jobA1, $jobB1, &$app2): void {
    $app2 = Employment\apply_for_job($pdo, $studentB, $jobA1, 'Bob cover note for Acme.');
    ensure($app2 > 0);

    $app3 = Employment\apply_for_job($pdo, $studentB, $jobB1, 'Bob cover note for Globex.');
    ensure($app3 > 0);
});

// --- 6. Student Application Tracking & Isolation Tests ---

run_test('list_student_applications returns own applications only', function () use ($pdo, $studentA, $studentB): void {
    $aliceApps = Employment\list_student_applications($pdo, $studentA);
    ensure(count($aliceApps) === 1);
    ensure($aliceApps[0]['job_title'] === 'Backend Engineering Intern');
    ensure($aliceApps[0]['status'] === 'applied');

    $bobApps = Employment\list_student_applications($pdo, $studentB);
    ensure(count($bobApps) === 2);
});

run_test('get_student_application_for_job returns status if applied, null if not', function () use ($pdo, $studentA, $jobA1, $jobB1): void {
    $applied = Employment\get_student_application_for_job($pdo, $studentA, $jobA1);
    ensure($applied !== null);
    ensure($applied['status'] === 'applied');

    $notApplied = Employment\get_student_application_for_job($pdo, $studentA, $jobB1);
    ensure($notApplied === null);
});

// --- 7. Company Applicant Review & Tenant Isolation Tests ---

run_test('list_company_applications lists applicants for Company A jobs', function () use ($pdo, $companyUserA, $orgAId): void {
    $apps = Employment\list_company_applications($pdo, $companyUserA, $orgAId);
    // Alice and Bob applied to Job A1
    ensure(count($apps) === 2, 'Count expected 2, got ' . count($apps) . ': ' . json_encode($apps));
    ensure($apps[0]['student_name'] === 'Student Bob', 'Expected Bob at 0, got ' . ($apps[0]['student_name'] ?? 'none'));
    ensure($apps[1]['student_name'] === 'Student Alice', 'Expected Alice at 1, got ' . ($apps[1]['student_name'] ?? 'none'));
    ensure($apps[1]['college'] === 'MIT', 'Expected MIT, got ' . ($apps[1]['college'] ?? 'none'));
    ensure($apps[1]['github_username'] === 'alice-coder', 'Expected alice-coder, got ' . ($apps[1]['github_username'] ?? 'none'));
});

run_test('tenant isolation: Company B cannot see Company A applicants', function () use ($pdo, $companyUserB, $orgBId): void {
    $apps = Employment\list_company_applications($pdo, $companyUserB, $orgBId);
    // Only Bob applied to Job B1
    ensure(count($apps) === 1);
    ensure($apps[0]['student_name'] === 'Student Bob');
    ensure($apps[0]['job_title'] === 'Fullstack Software Intern');
});

run_test('tenant isolation: Company B staff rejected when requesting Company A applicants', function () use ($pdo, $companyUserB, $orgAId): void {
    expect_employment_error(function () use ($pdo, $companyUserB, $orgAId): void {
        Employment\list_company_applications($pdo, $companyUserB, $orgAId);
    }, 'unauthorized_company_action', 403);
});

run_test('student cannot list company applicants', function () use ($pdo, $studentA, $orgAId): void {
    expect_employment_error(function () use ($pdo, $studentA, $orgAId): void {
        Employment\list_company_applications($pdo, $studentA, $orgAId);
    }, 'unauthorized_company_action', 403);
});

// --- 8. Company Application Status Updates & Security ---

run_test('update_application_status shortlists an applicant for own job', function () use ($pdo, $companyUserA, $orgAId, $app1): void {
    Employment\update_application_status($pdo, $companyUserA, $orgAId, $app1, 'shortlisted');

    // Verify persisted
    $stmt = $pdo->prepare('SELECT status FROM applications WHERE id = ?');
    $stmt->execute([$app1]);
    ensure($stmt->fetchColumn() === 'shortlisted');

    // Verify audit log
    $auditStmt = $pdo->prepare("SELECT * FROM admin_audit_log WHERE action = 'application.status_update' AND target_id = ?");
    $auditStmt->execute([$app1]);
    $audit = $auditStmt->fetch();
    ensure($audit !== false);
    ensure($audit['actor_user_id'] === $companyUserA);
});

run_test('cross-tenant status update rejected (Company B cannot modify Company A application)', function () use ($pdo, $companyUserB, $orgBId, $app1): void {
    expect_employment_error(function () use ($pdo, $companyUserB, $orgBId, $app1): void {
        Employment\update_application_status($pdo, $companyUserB, $orgBId, $app1, 'rejected');
    }, 'application_not_found', 404);
});

run_test('student cannot modify application status', function () use ($pdo, $studentA, $orgAId, $app1): void {
    expect_employment_error(function () use ($pdo, $studentA, $orgAId, $app1): void {
        Employment\update_application_status($pdo, $studentA, $orgAId, $app1, 'shortlisted');
    }, 'unauthorized_company_action', 403);
});

run_test('update_application_status rejects invalid status value', function () use ($pdo, $companyUserA, $orgAId, $app1): void {
    expect_employment_error(function () use ($pdo, $companyUserA, $orgAId, $app1): void {
        Employment\update_application_status($pdo, $companyUserA, $orgAId, $app1, 'hired_on_the_spot');
    }, 'invalid_application_status', 422);
});

// --- 9. Resume Builder Persistence (Phase 3 Foundation) ---

run_test('save_resume and get_resume persist structured resume', function () use ($pdo, $studentA): void {
    $resumeId = Employment\save_resume($pdo, $studentA, [
        'title' => 'Software Engineering Resume 2026',
        'summary' => 'Focused on backend scalability and reliable data pipelines.',
        'skills' => ['PHP', 'SQLite', 'C', 'Docker'],
        'education' => [['institution' => 'MIT', 'degree' => 'B.S. CS', 'year' => '2026']],
        'experience' => [['company' => 'Pathway Labs', 'role' => 'Security Intern', 'duration' => 'Summer 2025']],
        'projects' => [['title' => 'EIDOLON PATHWAY', 'description' => 'Local-first verifiable talent platform.']],
    ]);
    ensure($resumeId > 0);

    $resume = Employment\get_resume($pdo, $resumeId, $studentA);
    ensure($resume !== null);
    ensure($resume['title'] === 'Software Engineering Resume 2026');
    ensure(json_decode($resume['skills_json'], true) === ['PHP', 'SQLite', 'C', 'Docker']);

    $resumes = Employment\list_resumes($pdo, $studentA);
    ensure(count($resumes) === 1);
});

run_test('get_resume isolates between users (Student B cannot access Student A resume)', function () use ($pdo, $studentB): void {
    // Student A has resume ID 1
    $resume = Employment\get_resume($pdo, 1, $studentB);
    ensure($resume === null);
});

// --- 10. Security & Injection Tests ---

run_test('SQL injection attempts in job search handled safely as literal text', function () use ($pdo): void {
    $results = Employment\list_active_jobs($pdo, null, "' OR 1=1 --");
    ensure(count($results) === 0);

    $wildcard = Employment\list_active_jobs($pdo, null, '%');
    ensure(count($wildcard) === 0);
});

run_test('XSS payloads in student profile stored safely as text', function () use ($pdo, $studentB): void {
    $xss = '<script>alert("xss")</script>';
    $prof = Employment\save_profile($pdo, $studentB, [
        'skills' => $xss,
        'bio' => $xss,
    ]);
    ensure($prof['skills'] === $xss);
    ensure($prof['bio'] === $xss);
});

run_test('XSS payloads in job application notes stored safely as text', function () use ($pdo, $studentA, $jobA2): void {
    $xssNotes = '<img src=x onerror=alert(1)>';
    $appId = Employment\apply_for_job($pdo, $studentA, $jobA2, $xssNotes);
    ensure($appId > 0);

    $apps = Employment\list_student_applications($pdo, $studentA);
    $found = null;
    foreach ($apps as $a) {
        if ($a['id'] === $appId) { $found = $a; break; }
    }
    ensure($found !== null);
    ensure($found['notes'] === $xssNotes);
});

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
