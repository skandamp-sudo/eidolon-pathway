<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/organizations/organization.php';
require_once dirname(__DIR__, 2) . '/app/employment/jobs.php';
require_once dirname(__DIR__, 2) . '/app/evidence/agent.php';

use Pathway\Organizations;
use Pathway\Employment;
use Pathway\Evidence;

$passed = 0;
$failed = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function test_step(string $name, \Closure $callback): void
{
    global $passed, $failed;
    try {
        $callback();
        $passed++;
        echo "PASS [E2E DEMO] $name\n";
    } catch (\Throwable $error) {
        $failed++;
        echo "FAIL [E2E DEMO] $name (" . get_class($error) . ": " . $error->getMessage() . ")\n";
    }
}

// In-memory / isolated temporary test directory for pristine end-to-end demo
$demoDir = sys_get_temp_dir() . '/pathway-e2e-demo-' . bin2hex(random_bytes(8));
mkdir($demoDir, 0700);
$dbPath = $demoDir . '/pathway.sqlite';
putenv('PATHWAY_DATA_DIR=' . $demoDir);

$pdo = new \PDO('sqlite:' . $dbPath);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
\Pathway\initialize_database($pdo);

register_shutdown_function(function () use ($demoDir, $dbPath) {
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
    if (is_dir($demoDir . '/sessions')) {
        foreach (glob($demoDir . '/sessions/*') as $f) {
            unlink($f);
        }
        rmdir($demoDir . '/sessions');
    }
    if (is_dir($demoDir)) {
        rmdir($demoDir);
    }
});

echo "====================================================================\n";
echo "  EIDOLON PATHWAY · END-TO-END MULTI-ROLE VERIFICATION & DEMO (006C)\n";
echo "====================================================================\n";

$adminId = 0;
$recruiterId = 0;
$companyId = 0;
$jobId = 0;
$aliceId = 0;
$assessmentId = 0;
$resumeId = 0;
$applicationId = 0;
$companyBId = 0;
$companyBStaffId = 0;

// Helper mock transport for deterministic GitHub evidence assessment
final class DemoMockTransport implements Evidence\Transport
{
    public array $responses = [];
    public array $calls = [];

    public function fetch(string $resource, int $limit, float $deadline): array
    {
        $this->calls[] = $resource;
        if (!isset($this->responses[$resource])) {
            return ['status' => 404, 'headers' => [], 'body' => '{"message":"Not Found"}'];
        }
        $resp = $this->responses[$resource];
        if (strlen($resp['body']) > $limit) {
            throw new \Pathway\Evidence\EvidenceError('response_too_large');
        }
        return $resp;
    }

    public static function create(string $repo, string $commitSha, string $treeSha, array $files): self
    {
        $mock = new self();
        $mock->responses['/repos/' . $repo] = [
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['private' => false, 'visibility' => 'public', 'full_name' => $repo]),
        ];
        $mock->responses['/repos/' . $repo . '/commits?per_page=1'] = [
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([['sha' => $commitSha, 'commit' => ['tree' => ['sha' => $treeSha]]]]),
        ];
        $treeEntries = [];
        foreach ($files as $path => $content) {
            $blobSha = sha1('blob ' . strlen($content) . "\0" . $content);
            $treeEntries[] = ['path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => $blobSha, 'size' => strlen($content)];
            $mock->responses['/repos/' . $repo . '/git/blobs/' . $blobSha] = [
                'status' => 200,
                'headers' => ['content-type' => 'application/vnd.github.raw+json'],
                'body' => $content,
            ];
        }
        $mock->responses['/repos/' . $repo . '/git/trees/' . $treeSha . '?recursive=1'] = [
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['sha' => $treeSha, 'truncated' => false, 'tree' => $treeEntries]),
        ];
        return $mock;
    }
}

// STEP 1: Platform Admin Bootstrap
test_step('Step 1: Platform admin bootstrapped via CLI', function () use ($pdo, &$adminId) {
    $now = time();
    $hash = password_hash('AdminSecret2026!#', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'admin', ?)");
    $stmt->execute(['Platform Security Officer', 'admin@pathway.test', $hash, $now]);
    $adminId = (int) $pdo->lastInsertId();

    Organizations\record_audit($pdo, $adminId, 'admin.bootstrap', 'user', $adminId, ['email' => 'admin@pathway.test']);
    check($adminId > 0, 'Admin account successfully bootstrapped');
});

// STEP 2: Company Registration (starts PENDING)
test_step('Step 2: Company founder registers and creates organization in PENDING state', function () use ($pdo, &$recruiterId, &$companyId) {
    $now = time();
    $hash = password_hash('EidolonCompany2026!', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'company', ?)");
    $stmt->execute(['Elena Rostova', 'elena@eidolon-systems.test', $hash, $now]);
    $recruiterId = (int) $pdo->lastInsertId();

    $companyId = Organizations\create_organization($pdo, $recruiterId, 'Eidolon AI Systems', 'COMPANY');
    check($companyId > 0, 'Organization created');

    $stmt = $pdo->prepare('SELECT status, type FROM organizations WHERE id = ?');
    $stmt->execute([$companyId]);
    $org = $stmt->fetch();
    check($org['status'] === 'PENDING', 'Organization begins in PENDING status');
    check($org['type'] === 'COMPANY', 'Organization type is COMPANY');
});

// STEP 3: Verification of Unapproved Company Block
test_step('Step 3: Unapproved company cannot post jobs or mutate resources', function () use ($pdo, $recruiterId, $companyId) {
    try {
        Employment\create_job($pdo, $recruiterId, $companyId, [
            'title' => 'High Assurance Systems Intern',
            'description' => 'Help build verifiable evidence pipelines.',
            'skills' => 'PHP, SQLite, C',
            'role_category' => 'backend',
            'location' => 'San Francisco, CA',
            'status' => 'active',
        ]);
        throw new \RuntimeException('Expected job creation to be rejected for unapproved company');
    } catch (Employment\EmploymentError $e) {
        check($e->statusCode === 403, 'Fails with HTTP 403 Forbidden');
    }
});

// STEP 4: Platform Admin Approval
test_step('Step 4: Platform admin inspects and approves pending organization', function () use ($pdo, $adminId, $companyId) {
    $pending = Organizations\list_organizations($pdo, 'PENDING');
    check(count($pending) >= 1, 'Admin finds pending organization');
    check((int) $pending[0]['id'] === $companyId, 'Pending organization matches Eidolon AI Systems');

    Organizations\approve_organization($pdo, $adminId, $companyId);

    $stmt = $pdo->prepare('SELECT status FROM organizations WHERE id = ?');
    $stmt->execute([$companyId]);
    check($stmt->fetchColumn() === 'ACTIVE', 'Company status transitioned to ACTIVE');
});

// STEP 5: Company Posts Active Internship
test_step('Step 5: Approved company posts verified internship opportunity', function () use ($pdo, $recruiterId, $companyId, &$jobId) {
    $jobId = Employment\create_job($pdo, $recruiterId, $companyId, [
        'title' => 'Kernel & Evidence Systems Engineering Intern',
        'description' => 'Develop high-assurance SQLite pipelines and deterministic verification engines in PHP 8.4.',
        'skills' => 'PHP 8.4, SQLite, C, Static Analysis',
        'role_category' => 'backend',
        'location' => 'San Francisco, CA / Remote',
        'status' => 'active',
    ]);
    check($jobId > 0, 'Internship successfully created and assigned ID');

    $activeJobs = Employment\list_active_jobs($pdo);
    check(count($activeJobs) >= 1, 'Job appears in public active catalog');
    check($activeJobs[0]['company_name'] === 'Eidolon AI Systems', 'Company name verified in listing');
});

// STEP 6: Student Registers & Conducts GitHub Evidence Assessment
test_step('Step 6: Student Alice registers and assesses GitHub portfolio evidence', function () use ($pdo, &$aliceId, &$assessmentId) {
    $now = time();
    $hash = password_hash('AliceStudent2026!', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'student', ?)");
    $stmt->execute(['Alice Chen', 'alice@university.test', $hash, $now]);
    $aliceId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, college, skills, github_username, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$aliceId, 'UC Berkeley', 'PHP 8.4, SQLite, Systems Design', 'alice-code', $now]);

    // Bounded offline mock of GitHub repository
    $commitSha = '8a3b2c1d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b';
    $treeSha = '9f8e7d6c5b4a3f2e1d0c9b8a7f6e5d4c3b2a1f0e';
    $files = [
        'README.md' => "# MicroEngine\nHigh assurance SQLite storage.\n",
        'src/Engine.php' => "<?php\nnamespace App;\nclass Engine {\n    public function execute(string \$sql): void {}\n}\n",
        'tests/EngineTest.php' => "<?php\nnamespace App\\Tests;\nuse App\\Engine;\nclass EngineTest {}\n",
        '.github/workflows/ci.yml' => "name: CI\non: [push]\njobs:\n  test:\n    runs-on: ubuntu-latest\n",
    ];
    $mockTransport = DemoMockTransport::create('alice-code/micro-engine', $commitSha, $treeSha, $files);

    $assessmentId = Evidence\queue($pdo, $aliceId, 'alice-code/micro-engine', 'backend');
    $result = Evidence\run($pdo, $assessmentId, $mockTransport);

    check($result['commit_sha'] === $commitSha, 'Assessment pinned commit SHA');
    check(count($result['tasks']) === 3, 'Exactly three prioritized improvement tasks generated');

    $detail = Evidence\detail($pdo, $assessmentId, $aliceId);
    check($detail['state'] === 'completed', 'Assessment state is completed');
});

// STEP 7: Student Builds Verifiable Resume with Evidence Citation
test_step('Step 7: Student Alice constructs ATS resume citing verified GitHub assessment', function () use ($pdo, $aliceId, $assessmentId, &$resumeId) {
    $resumeData = [
        'title' => 'Systems Software Engineer',
        'summary' => 'Focused on deterministic database engines, zero-daemon architectures, and formal evidence verification.',
        'assessment_id' => $assessmentId,
        'skills' => ['PHP 8.4', 'SQLite', 'C', 'Static Analysis'],
        'education' => [
            ['institution' => 'UC Berkeley', 'degree' => 'B.S. Electrical Engineering & Computer Science', 'year' => '2023 – 2026', 'gpa' => '3.95']
        ],
        'experience' => [
            ['company' => 'EIDOLON Open Source', 'role' => 'Core Contributor', 'start_date' => 'Jan 2025', 'end_date' => 'Present', 'description' => "Authored SQLite persistence layers\nVerified ATS resume formatting"]
        ],
        'projects' => [
            ['name' => 'MicroEngine', 'technologies' => 'PHP 8.4, SQLite', 'description' => 'Deterministic high-assurance storage engine', 'link' => 'https://github.com/alice-code/micro-engine']
        ]
    ];
    $resumeId = Employment\save_resume($pdo, $aliceId, $resumeData);
    check($resumeId > 0, 'Resume draft saved successfully');

    $resume = Employment\get_resume($pdo, $resumeId, $aliceId);
    check($resume['assessment_id'] === $assessmentId, 'Verified assessment citation attached');
    check($resume['assessment_repo'] === 'alice-code/micro-engine', 'Cited repository matches');
    check(!empty($resume['assessment_tasks']), 'Milestone improvement tasks embedded in resume');
});

// STEP 8: Student Applies to Internship
test_step('Step 8: Student Alice applies to Eidolon AI Systems internship', function () use ($pdo, $aliceId, $jobId, &$applicationId) {
    $applicationId = Employment\apply_for_job($pdo, $aliceId, $jobId, 'Attached verified evidence and resume citing my MicroEngine repository.');
    check($applicationId > 0, 'Application submitted with atomic conflict protection');

    $apps = Employment\list_student_applications($pdo, $aliceId);
    check(count($apps) === 1, 'Alice has 1 tracked application');
    check($apps[0]['status'] === 'applied', 'Initial application review status is applied (Under Review)');
});

// STEP 9: Cross-Tenant Isolation Enforcement
test_step('Step 9: Cross-tenant isolation blocks rival Company B from accessing or altering application', function () use ($pdo, $applicationId, &$companyBId, &$companyBStaffId) {
    $now = time();
    $hash = password_hash('BetaPassword123!', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'company', ?)");
    $stmt->execute(['Rival Recruiter', 'rival@competitor.test', $hash, $now]);
    $companyBStaffId = (int) $pdo->lastInsertId();

    $companyBId = Organizations\create_organization($pdo, $companyBStaffId, 'Competitor Dynamics', 'COMPANY', true);
    // Approve Company B
    $pdo->exec("UPDATE organizations SET status = 'ACTIVE' WHERE id = $companyBId");

    // Company B attempts to list Company A applicants
    $betaApplicants = Employment\list_company_applications($pdo, $companyBStaffId, $companyBId);
    check(count($betaApplicants) === 0, 'Company B sees 0 applicants');

    // Company B attempts to tamper with Alice's application status
    try {
        Employment\update_application_status($pdo, $companyBStaffId, $companyBId, $applicationId, 'rejected');
        throw new \RuntimeException('Expected cross-tenant status update to fail');
    } catch (Employment\EmploymentError $e) {
        check($e->statusCode === 404, 'Fails with 404 application_not_found');
    }
});

// STEP 10: Company Recruiter Shortlists Alice
test_step('Step 10: Eidolon recruiter reviews candidate evidence and shortlists Alice', function () use ($pdo, $recruiterId, $companyId, $applicationId) {
    $applicants = Employment\list_company_applications($pdo, $recruiterId, $companyId);
    check(count($applicants) === 1, 'Eidolon sees 1 applicant');
    check($applicants[0]['student_name'] === 'Alice Chen', 'Applicant name verified');
    check($applicants[0]['college'] === 'UC Berkeley', 'College verified');
    check($applicants[0]['github_username'] === 'alice-code', 'GitHub evidence link verified');

    Employment\update_application_status($pdo, $recruiterId, $companyId, $applicationId, 'shortlisted');

    $updatedApplicants = Employment\list_company_applications($pdo, $recruiterId, $companyId);
    check($updatedApplicants[0]['status'] === 'shortlisted', 'Status updated to shortlisted');
});

// STEP 11: Student Verifies Status Update
test_step('Step 11: Student Alice tracks real-time shortlisted status', function () use ($pdo, $aliceId) {
    $apps = Employment\list_student_applications($pdo, $aliceId);
    check($apps[0]['status'] === 'shortlisted', 'Alice application status is Shortlisted');
});

// STEP 12: Re-run GitHub Evidence Assessment
test_step('Step 12: Re-run portfolio evidence assessment on a second repository', function () use ($pdo, $aliceId) {
    $commitSha2 = '11223344556677889900aabbccddeeff00112233';
    $treeSha2 = 'aabbccddeeff0011223344556677889900112233';
    $files2 = [
        'README.md' => "# MicroNet\nNetwork protocol engine.\n",
        'src/Socket.php' => "<?php\nnamespace App;\nclass Socket {}\n",
        'tests/SocketTest.php' => "<?php\nnamespace App\\Tests;\nclass SocketTest {}\n",
    ];
    $mock2 = DemoMockTransport::create('alice-code/micronet', $commitSha2, $treeSha2, $files2);

    $id2 = Evidence\queue($pdo, $aliceId, 'alice-code/micronet', 'backend');
    $result2 = Evidence\run($pdo, $id2, $mock2);

    check($result2['commit_sha'] === $commitSha2, 'Second assessment successfully pinned commit SHA');
    check(count($result2['tasks']) === 3, 'Second assessment generated three improvement tasks');

    $history = Evidence\history($pdo, $aliceId);
    check(count($history) === 2, 'Student history shows both completed assessments');
    check($history[0]['state'] === 'completed' && $history[1]['state'] === 'completed', 'All assessments completed successfully');
});

// STEP 13: College Portal Onboarding & Placement Staff Verification
$collegeId = 0;
$collegeStaffId = 0;
test_step('Step 13: Platform admin approves college organization and placement officer views consented student records', function () use ($pdo, $adminId, $aliceId, &$collegeId, &$collegeStaffId) {
    // Admin creates and approves college
    $collegeId = Organizations\create_organization($pdo, $adminId, 'Dayananda Sagar Academy of Technology and Management', 'COLLEGE');
    Organizations\approve_organization($pdo, $adminId, $collegeId);

    // Onboard placement officer
    $inv = Organizations\create_invitation($pdo, $adminId, $collegeId, 'placement.officer@dsatm.test', 'STAFF');
    $accepted = Organizations\register_and_accept_invitation($pdo, $inv['raw_token'], 'Prof. M. S. Rao', 'CollegeOfficerPass#2026');
    $collegeStaffId = (int) $accepted['user_id'];

    // Student Alice links to DSATM with explicit placement share consent
    Employment\save_profile($pdo, $aliceId, [
        'college_org_id' => $collegeId,
        'college_share_consent' => 1,
    ]);

    // Placement officer lists students
    $students = Employment\list_college_students($pdo, $collegeId, $collegeStaffId);
    check(count($students) === 1, 'Placement officer sees 1 student in DSATM roster');
    check($students[0]['student_name'] === 'Alice Chen', 'Alice listed in roster');
    check($students[0]['student_email'] === 'alice@university.test', 'Alice email visible to college');
    check((int) $students[0]['applications_count'] === 1, 'Alice application count visible');

    // Placement officer accesses Alice placement detail
    $details = Employment\get_college_student_placement_details($pdo, $collegeId, $aliceId, $collegeStaffId);
    check($details['student']['student_name'] === 'Alice Chen', 'Placement details retrieved for Alice');
    check(count($details['applications']) === 1, 'Shortlisted application visible to placement office');
    check($details['resume'] !== null, 'Verified resume visible to placement office');
});

// STEP 14: Consent-Based Privacy Gate Blocks Non-Consenting Student
$studentBobId = 0;
test_step('Step 14: Non-consenting student privacy gate masks email and rejects detail lookup with 403', function () use ($pdo, $collegeId, $collegeStaffId, &$studentBobId) {
    $now = time();
    $hash = password_hash('StudentBobPass#2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'student', ?)");
    $stmt->execute(['Bob Confidential', 'bob.confidential@student.test', $hash, (string) $now]);
    $studentBobId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, college, skills, github_username, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$studentBobId, 'DSATM Bengaluru', 'Security', 'bob-priv', $now]);

    // Bob associates with college but WITHHOLDS consent (college_share_consent = 0)
    Employment\save_profile($pdo, $studentBobId, [
        'college_org_id' => $collegeId,
        'college_share_consent' => 0,
    ]);

    // Placement officer checks roster
    $students = Employment\list_college_students($pdo, $collegeId, $collegeStaffId);
    check(count($students) === 2, 'Two students in DSATM roster');

    $bobEntry = null;
    foreach ($students as $s) {
        if ($s['student_name'] === 'Bob Confidential') {
            $bobEntry = $s;
            break;
        }
    }
    check($bobEntry !== null, 'Bob found in roster');
    check($bobEntry['student_email'] === '[Consent Withheld]', 'Non-consenting student email is masked');
    check((int) $bobEntry['applications_count'] === 0, 'Non-consenting application count is masked to 0');

    // Placement officer attempts to view Bob's placement records
    $blocked = false;
    try {
        Employment\get_college_student_placement_details($pdo, $collegeId, $studentBobId, $collegeStaffId);
    } catch (Pathway\Employment\EmploymentError $e) {
        if ($e->statusCode === 403 && $e->reason === 'student_consent_required') {
            $blocked = true;
        }
    }
    check($blocked, 'Viewing non-consenting student detail rejected with 403 student_consent_required');
});

// STEP 15: Cross-College Tenant Isolation
test_step('Step 15: Cross-college tenant isolation blocks foreign college staff from DSATM records', function () use ($pdo, $adminId, $collegeId, $aliceId) {
    // Admin creates rival College B
    $collegeBId = Organizations\create_organization($pdo, $adminId, 'Rival Institute of Tech', 'COLLEGE');
    Organizations\approve_organization($pdo, $adminId, $collegeBId);

    $invB = Organizations\create_invitation($pdo, $adminId, $collegeBId, 'officer@rival.test', 'STAFF');
    $acceptedB = Organizations\register_and_accept_invitation($pdo, $invB['raw_token'], 'Rival Placement Staff', 'RivalOfficerPass#2026');
    $rivalStaffId = (int) $acceptedB['user_id'];

    // Rival college staff attempts to access DSATM roster
    $blockedRoster = false;
    try {
        Employment\list_college_students($pdo, $collegeId, $rivalStaffId);
    } catch (Pathway\Employment\EmploymentError $e) {
        if ($e->statusCode === 403 && $e->reason === 'unauthorized_college_action') {
            $blockedRoster = true;
        }
    }
    check($blockedRoster, 'Rival college staff denied access to DSATM roster (403)');

    // Rival college staff attempts to view Alice's placement details
    $blockedDetail = false;
    try {
        Employment\get_college_student_placement_details($pdo, $collegeId, $aliceId, $rivalStaffId);
    } catch (Pathway\Employment\EmploymentError $e) {
        if ($e->statusCode === 403 && $e->reason === 'unauthorized_college_action') {
            $blockedDetail = true;
        }
    }
    check($blockedDetail, 'Rival college staff denied access to Alice placement details (403)');
});

echo "====================================================================\n";
echo "RESULT: $passed passed, $failed failed\n";
echo "====================================================================\n";
exit($failed > 0 ? 1 : 0);
