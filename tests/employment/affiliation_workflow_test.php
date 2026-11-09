<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/organizations/organization.php';
require_once dirname(__DIR__, 2) . '/app/employment/jobs.php';
require_once dirname(__DIR__, 2) . '/app/employment/affiliation.php';

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
$testDir = sys_get_temp_dir() . '/pathway-affiliation-tests-' . bin2hex(random_bytes(8));
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

echo "=== CHECKPOINT 009A: AFFILIATION, PRIVACY CONSENT & TALENT DISCOVERY TESTS ===\n";

$adminUserId = null;
$collegeAId = null;
$collegeAStaffId = null;
$collegeBId = null;
$collegeBStaffId = null;
$companyAId = null;
$companyAStaffId = null;
$companyBId = null;
$companyBStaffId = null;
$jobAId = null;
$studentAliceId = null;
$studentBobId = null;
$affiliationReqId = null;
$aliceAssessmentId = null;
$achievementId = null;
$aliceApplicationId = null;

test_case('bootstrap admin and seed organizations', function () use (
    $pdo,
    &$adminUserId,
    &$collegeAId,
    &$collegeAStaffId,
    &$collegeBId,
    &$collegeBStaffId,
    &$companyAId,
    &$companyAStaffId,
    &$companyBId,
    &$companyBStaffId,
    &$jobAId,
    &$studentAliceId,
    &$studentBobId,
    &$aliceAssessmentId
) {
    $now = time();
    $hash = password_hash('Admin#MasterKey2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'admin', ?)");
    $stmt->execute(['Platform Auditor', 'admin@pathway.test', $hash, $now]);
    $adminUserId = (int) $pdo->lastInsertId();

    // College A
    $collegeAId = Organizations\create_organization($pdo, $adminUserId, 'Dayananda Sagar Academy', 'COLLEGE');
    Organizations\approve_organization($pdo, $adminUserId, $collegeAId);
    $tokA = Organizations\create_invitation($pdo, $adminUserId, $collegeAId, 'officer.a@dsatm.test', 'STAFF');
    $acceptedA = Organizations\register_and_accept_invitation($pdo, $tokA['raw_token'], 'Officer Alpha', 'StaffPass#2026');
    $collegeAStaffId = (int) $acceptedA['user_id'];

    // College B (Rival)
    $collegeBId = Organizations\create_organization($pdo, $adminUserId, 'Rival Institute', 'COLLEGE');
    Organizations\approve_organization($pdo, $adminUserId, $collegeBId);
    $tokB = Organizations\create_invitation($pdo, $adminUserId, $collegeBId, 'officer.b@rival.test', 'STAFF');
    $acceptedB = Organizations\register_and_accept_invitation($pdo, $tokB['raw_token'], 'Officer Beta', 'StaffPass#2026');
    $collegeBStaffId = (int) $acceptedB['user_id'];

    // Company A
    $companyAId = Organizations\create_organization($pdo, $adminUserId, 'Eidolon AI Systems', 'COMPANY');
    Organizations\approve_organization($pdo, $adminUserId, $companyAId);
    $tokCompA = Organizations\create_invitation($pdo, $adminUserId, $companyAId, 'recruiter.a@eidolon.test', 'STAFF');
    $acceptedCompA = Organizations\register_and_accept_invitation($pdo, $tokCompA['raw_token'], 'Recruiter Alpha', 'CompPass#2026');
    $companyAStaffId = (int) $acceptedCompA['user_id'];

    // Company B (Rival)
    $companyBId = Organizations\create_organization($pdo, $adminUserId, 'Rival Tech Inc', 'COMPANY');
    Organizations\approve_organization($pdo, $adminUserId, $companyBId);
    $tokCompB = Organizations\create_invitation($pdo, $adminUserId, $companyBId, 'recruiter.b@rivaltech.test', 'STAFF');
    $acceptedCompB = Organizations\register_and_accept_invitation($pdo, $tokCompB['raw_token'], 'Recruiter Beta', 'CompPass#2026');
    $companyBStaffId = (int) $acceptedCompB['user_id'];

    // Job posted by Company A
    $jobAId = Employment\create_job($pdo, $companyAStaffId, $companyAId, [
        'title' => 'Security Systems Intern',
        'description' => 'Build high-assurance microservices and auth systems.',
        'skills' => 'PHP, SQLite, Cryptography',
        'role_category' => 'backend',
        'location' => 'Bengaluru (Hybrid)',
    ]);

    // Student Alice
    $hash = password_hash('StudentAlice#2026', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Alice Cryptosec', 'alice@student.test', $hash, 'student', $now]);
    $studentAliceId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, college, skills, github_username, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$studentAliceId, 'DSATM Bengaluru', 'PHP, SQLite, Go', 'alice-sec', $now]);

    // Student Bob
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Bob Builder', 'bob@student.test', $hash, 'student', $now]);
    $studentBobId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, college, skills, github_username, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$studentBobId, 'DSATM Bengaluru', 'Frontend, React', 'bob-builder', $now]);

    // Completed Assessment for Alice
    $stmt = $pdo->prepare("
        INSERT INTO assessments (user_id, repository, commit_sha, target_role, state, rubric_version, created_at, updated_at)
        VALUES (?, 'alice-sec/secure-core', 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2', 'backend', 'completed', 'v1', ?, ?)
    ");
    $stmt->execute([$studentAliceId, $now - 3600, $now - 3000]);
    $aliceAssessmentId = (int) $pdo->lastInsertId();

    $tStmt = $pdo->prepare("INSERT INTO improvement_tasks (assessment_id, priority, finding_id, title, acceptance) VALUES (?, ?, ?, ?, ?)");
    $tStmt->execute([$aliceAssessmentId, 1, 'task-sec-1', 'Add Rate Limiting', 'Enforce 429 after 5 failed attempts']);
});

test_case('student Alice submits affiliation request with structured enrollment details', function () use ($pdo, $studentAliceId, $collegeAId, &$affiliationReqId) {
    $affiliationReqId = Employment\submit_affiliation_request($pdo, $studentAliceId, $collegeAId, [
        'enrollment_number' => '1DT22CS042',
        'degree' => 'B.E. Computer Science',
        'department' => 'Computer Science & Engineering',
        'graduation_year' => 2026,
        'verification_note' => 'Please verify my 7th semester enrollment.',
    ]);

    check($affiliationReqId > 0, 'Affiliation request created');

    $req = Employment\get_affiliation_request($pdo, $affiliationReqId, $studentAliceId);
    check($req !== null, 'Request retrieved');
    check($req['status'] === 'PENDING', 'Request status is PENDING');
    check($req['enrollment_number'] === '1DT22CS042', 'Enrollment number matches');
    check((int) $req['graduation_year'] === 2026, 'Graduation year matches');

    // Student profile affiliation status marked as PENDING
    $profile = Employment\get_profile($pdo, $studentAliceId);
    check($profile['affiliation_status'] === 'PENDING', 'Profile affiliation status is PENDING');
    check($profile['college_org_id'] === null, 'College org ID remains null until approval');
});

test_case('duplicate pending affiliation request rejected with 409 conflict', function () use ($pdo, $studentAliceId, $collegeAId) {
    $conflict = false;
    try {
        Employment\submit_affiliation_request($pdo, $studentAliceId, $collegeAId, [
            'enrollment_number' => '1DT22CS042',
            'degree' => 'B.E. Computer Science',
            'department' => 'Computer Science & Engineering',
            'graduation_year' => 2026,
        ]);
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 409 && $e->reason === 'pending_affiliation_exists') {
            $conflict = true;
        }
    }
    check($conflict, 'Duplicate pending request rejected with 409');
});

test_case('student Bob submits affiliation request then cancels it', function () use ($pdo, $studentBobId, $collegeAId) {
    $bobReqId = Employment\submit_affiliation_request($pdo, $studentBobId, $collegeAId, [
        'enrollment_number' => '1DT22CS099',
        'degree' => 'B.E. Information Science',
        'department' => 'Information Science',
        'graduation_year' => 2026,
    ]);

    $profile = Employment\get_profile($pdo, $studentBobId);
    check($profile['affiliation_status'] === 'PENDING', 'Bob profile status is PENDING');

    // Bob cancels
    Employment\cancel_affiliation_request($pdo, $studentBobId, $bobReqId);

    $req = Employment\get_affiliation_request($pdo, $bobReqId, $studentBobId);
    check($req['status'] === 'CANCELLED', 'Request status is CANCELLED');

    $profileAfter = Employment\get_profile($pdo, $studentBobId);
    check($profileAfter['affiliation_status'] === 'NONE', 'Bob profile returns to NONE');

    // Attempting to cancel already cancelled request fails
    $rejected = false;
    try {
        Employment\cancel_affiliation_request($pdo, $studentBobId, $bobReqId);
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 400 && $e->reason === 'cannot_cancel_non_pending_request') {
            $rejected = true;
        }
    }
    check($rejected, 'Cancelling non-pending request rejected');
});

test_case('cross-tenant isolation on affiliation review queue', function () use ($pdo, $collegeAId, $collegeBStaffId) {
    $isolated = false;
    try {
        // Officer at College B attempts to view College A affiliation queue
        Employment\list_college_affiliation_requests($pdo, $collegeAId, $collegeBStaffId);
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 403 && $e->reason === 'unauthorized_college_action') {
            $isolated = true;
        }
    }
    check($isolated, 'Rival college officer denied access to foreign college affiliation queue');
});

test_case('college staff reviews and approves affiliation request', function () use ($pdo, $collegeAId, $collegeAStaffId, $studentAliceId, $affiliationReqId) {
    // Review without reason fails
    $reasonRequired = false;
    try {
        Employment\review_affiliation_request($pdo, $collegeAId, $collegeAStaffId, $affiliationReqId, 'APPROVED', '  ');
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 422 && $e->reason === 'decision_reason_required') {
            $reasonRequired = true;
        }
    }
    check($reasonRequired, 'Audit decision reason required');

    // Valid approval
    Employment\review_affiliation_request(
        $pdo,
        $collegeAId,
        $collegeAStaffId,
        $affiliationReqId,
        'APPROVED',
        'Verified in University Registrar portal for 2026 cohort.'
    );

    $req = Employment\get_affiliation_request($pdo, $affiliationReqId);
    check($req['status'] === 'APPROVED', 'Status updated to APPROVED');
    check($req['review_decision_reason'] === 'Verified in University Registrar portal for 2026 cohort.', 'Reason recorded');
    check((int) $req['reviewed_by_user_id'] === $collegeAStaffId, 'Reviewer user recorded');

    // Student Alice profile updated with affiliation
    $profile = Employment\get_profile($pdo, $studentAliceId);
    check((int) $profile['college_org_id'] === $collegeAId, 'College org ID set to College A');
    check($profile['affiliation_status'] === 'AFFILIATED', 'Affiliation status is AFFILIATED');
    check($profile['enrollment_number'] === '1DT22CS042', 'Enrollment number synced to profile');

    // Crucial privacy gate: Master consent is STILL 0 by default!
    check((int) $profile['college_share_consent'] === 0, 'Affiliation approval does NOT automatically expose private data');

    // Re-reviewing already reviewed request fails
    $alreadyReviewed = false;
    try {
        Employment\review_affiliation_request($pdo, $collegeAId, $collegeAStaffId, $affiliationReqId, 'APPROVED', 'Second review');
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 409 && $e->reason === 'affiliation_already_reviewed') {
            $alreadyReviewed = true;
        }
    }
    check($alreadyReviewed, 'Replaying review rejected with 409');
});

test_case('college staff lists students: non-consenting Alice is masked', function () use ($pdo, $collegeAId, $collegeAStaffId, $studentAliceId) {
    $students = Employment\list_college_students($pdo, $collegeAId, $collegeAStaffId);
    check(count($students) === 1, 'Alice is in roster');
    check($students[0]['student_name'] === 'Alice Cryptosec', 'Alice name visible');
    check($students[0]['student_email'] === '[Consent Withheld]', 'Alice email is masked');
    check((int) $students[0]['applications_count'] === 0, 'Applications count is 0');
    check((int) $students[0]['resumes_count'] === 0, 'Resumes count is 0');
});

test_case('college staff detail view denied for non-consenting student and logged to access audit', function () use ($pdo, $collegeAId, $collegeAStaffId, $studentAliceId) {
    $denied = false;
    try {
        Employment\get_college_student_placement_details($pdo, $collegeAId, $studentAliceId, $collegeAStaffId);
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 403 && $e->reason === 'student_consent_required') {
            $denied = true;
        }
    }
    check($denied, 'Detail view denied with 403 student_consent_required');

    // Check sensitive record access log
    $logs = Employment\list_student_access_logs($pdo, $studentAliceId);
    check(count($logs) === 1, 'One access log recorded');
    check((int) $logs[0]['granted'] === 0, 'Access attempt marked as denied (granted = 0)');
    check($logs[0]['access_context'] === 'college_placement_details', 'Context recorded');
    check($logs[0]['accessor_role'] === 'college', 'Accessor role is college');
});

test_case('student Alice configures granular privacy permissions and grants consent', function () use ($pdo, $studentAliceId, $collegeAId, $collegeAStaffId) {
    // Alice updates profile with full consent
    Employment\save_profile($pdo, $studentAliceId, [
        'college_share_consent' => 1,
        'share_contact_details' => 1,
        'share_resume' => 1,
        'share_achievements' => 1,
        'share_applications' => 1,
        'talent_discovery_opt_in' => 0, // Keeps talent discovery disabled for now
    ]);

    $profile = Employment\get_profile($pdo, $studentAliceId);
    check((int) $profile['college_share_consent'] === 1, 'Master consent enabled');
    check((int) $profile['share_contact_details'] === 1, 'Contact sharing enabled');

    // Now college staff views details
    $details = Employment\get_college_student_placement_details($pdo, $collegeAId, $studentAliceId, $collegeAStaffId);
    check($details['student']['student_name'] === 'Alice Cryptosec', 'Student name returned');
    check($details['student']['student_email'] === 'alice@student.test', 'Email is visible when consented');

    // Verify access log has granted event
    $logs = Employment\list_student_access_logs($pdo, $studentAliceId);
    check(count($logs) === 2, 'Two access logs recorded');
    check((int) $logs[0]['granted'] === 1, 'Latest access attempt marked as granted');
});

test_case('immediate consent revocation cuts off college staff access', function () use ($pdo, $studentAliceId, $collegeAId, $collegeAStaffId) {
    // Alice revokes consent immediately
    Employment\save_profile($pdo, $studentAliceId, [
        'college_share_consent' => 0,
    ]);

    $profile = Employment\get_profile($pdo, $studentAliceId);
    check((int) $profile['college_share_consent'] === 0, 'Master consent revoked');

    $denied = false;
    try {
        Employment\get_college_student_placement_details($pdo, $collegeAId, $studentAliceId, $collegeAStaffId);
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 403 && $e->reason === 'student_consent_required') {
            $denied = true;
        }
    }
    check($denied, 'Detail access immediately denied upon revocation');
});

test_case('granular consent masking: withholding email and applications', function () use ($pdo, $studentAliceId, $collegeAId, $collegeAStaffId, $jobAId) {
    // Alice applies to a job first
    $aliceApplicationId = Employment\apply_for_job($pdo, $studentAliceId, $jobAId, 'Excited to apply.');

    // Alice enables master consent, but withholds contact details and applications
    Employment\save_profile($pdo, $studentAliceId, [
        'college_share_consent' => 1,
        'share_contact_details' => 0, // Withhold email
        'share_applications' => 0,    // Withhold applications
        'share_resume' => 1,
        'share_achievements' => 1,
    ]);

    $details = Employment\get_college_student_placement_details($pdo, $collegeAId, $studentAliceId, $collegeAStaffId);
    check($details['student']['student_email'] === '[Consent Withheld]', 'Email masked according to granular preference');
    check(count($details['applications']) === 0, 'Applications masked according to granular preference');
});

test_case('student achievements CRUD and IDOR protection', function () use ($pdo, $studentAliceId, $studentBobId, $aliceAssessmentId, &$achievementId) {
    $achievementId = Employment\create_achievement($pdo, $studentAliceId, [
        'category' => 'hackathon',
        'title' => 'Code for a Billion 2026 Finalist',
        'issuer' => 'Hackathon Jury',
        'issue_date' => 'November 2026',
        'credential_url' => 'https://example.com/verify/cfab-2026',
        'description' => 'Developed high-assurance zero-dependency career verification infrastructure.',
        'evidence_assessment_id' => $aliceAssessmentId,
    ]);

    check($achievementId > 0, 'Achievement created');

    $list = Employment\list_student_achievements($pdo, $studentAliceId);
    check(count($list) === 1, 'Alice has 1 achievement');
    check($list[0]['title'] === 'Code for a Billion 2026 Finalist', 'Title matches');
    check($list[0]['assessment_repo'] === 'alice-sec/secure-core', 'Linked GitHub assessment joined');

    // IDOR protection: Student Bob cannot delete Alice achievement
    $idorBlocked = false;
    try {
        Employment\delete_achievement($pdo, $studentBobId, $achievementId);
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 404 && $e->reason === 'achievement_not_found') {
            $idorBlocked = true;
        }
    }
    check($idorBlocked, 'IDOR: Bob cannot delete Alice achievement');

    // Invalid category rejected
    $invalidCat = false;
    try {
        Employment\create_achievement($pdo, $studentAliceId, [
            'category' => 'nonexistent',
            'title' => 'Invalid',
        ]);
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 422 && $e->reason === 'invalid_achievement_category') {
            $invalidCat = true;
        }
    }
    check($invalidCat, 'Invalid achievement category rejected with 422');
});

test_case('recruiter candidate evidence view and sensitive record access audit', function () use (
    $pdo,
    $studentAliceId,
    $companyAId,
    $companyAStaffId,
    $companyBId,
    $companyBStaffId,
    $jobAId
) {
    // Get Alice application ID
    $app = Employment\get_student_application_for_job($pdo, $studentAliceId, $jobAId);
    check($app !== null, 'Alice application found');
    $appId = (int) $app['id'];

    // Company A recruiter inspects candidate dossier
    $candidate = Employment\get_candidate_for_recruiter($pdo, $appId, $companyAStaffId, $companyAId);
    check($candidate['profile']['user_id'] === $studentAliceId, 'Alice profile loaded');
    check(count($candidate['achievements']) === 1, 'Alice achievements visible to recruiter');

    // Sensitive access recorded for recruiter
    $logs = Employment\list_student_access_logs($pdo, $studentAliceId);
    $recruiterLog = null;
    foreach ($logs as $l) {
        if ($l['accessor_role'] === 'company') {
            $recruiterLog = $l;
            break;
        }
    }
    check($recruiterLog !== null, 'Recruiter access logged to sensitive access table');
    check((int) $recruiterLog['granted'] === 1, 'Recruiter access granted');
    check($recruiterLog['access_context'] === 'recruiter_candidate_view', 'Context recorded');

    // Cross-tenant protection: Rival Company B cannot access Alice candidate dossier
    $rivalBlocked = false;
    try {
        Employment\get_candidate_for_recruiter($pdo, $appId, $companyBStaffId, $companyBId);
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 404 && $e->reason === 'application_not_found') {
            $rivalBlocked = true;
        }
    }
    check($rivalBlocked, 'Cross-tenant: Rival company recruiter blocked from accessing candidate');
});

test_case('talent discovery directory: strict opt-in enforcement', function () use ($pdo, $companyAId, $companyAStaffId, $studentAliceId) {
    // Alice has talent_discovery_opt_in = 0 currently
    $resultsBefore = Employment\list_opted_in_talent($pdo, $companyAStaffId, $companyAId);
    $aliceFoundBefore = false;
    foreach ($resultsBefore as $cand) {
        if ((int) $cand['student_id'] === $studentAliceId) {
            $aliceFoundBefore = true;
        }
    }
    check(!$aliceFoundBefore, 'Alice is hidden from talent directory when not opted in');

    // Alice opts in
    Employment\save_profile($pdo, $studentAliceId, [
        'talent_discovery_opt_in' => 1,
    ]);

    // Recruiter queries talent directory
    $resultsAfter = Employment\list_opted_in_talent($pdo, $companyAStaffId, $companyAId, 'PHP');
    $aliceFoundAfter = false;
    foreach ($resultsAfter as $cand) {
        if ((int) $cand['student_id'] === $studentAliceId) {
            $aliceFoundAfter = true;
            check((int) $cand['achievements_count'] === 1, 'Achievements count returned');
        }
    }
    check($aliceFoundAfter, 'Alice appears in talent search results after opt-in');

    // Alice opts out again
    Employment\save_profile($pdo, $studentAliceId, [
        'talent_discovery_opt_in' => 0,
    ]);

    $resultsAfterOptOut = Employment\list_opted_in_talent($pdo, $companyAStaffId, $companyAId);
    $aliceFoundAfterOptOut = false;
    foreach ($resultsAfterOptOut as $cand) {
        if ((int) $cand['student_id'] === $studentAliceId) {
            $aliceFoundAfterOptOut = true;
        }
    }
    check(!$aliceFoundAfterOptOut, 'Alice immediately removed from talent directory upon opt-out');
});

test_case('college staff revokes affiliation with auditable reason', function () use ($pdo, $collegeAId, $collegeAStaffId, $studentAliceId) {
    // Alice is currently affiliated
    $profile = Employment\get_profile($pdo, $studentAliceId);
    check($profile['affiliation_status'] === 'AFFILIATED', 'Alice is affiliated before revocation');

    // Reason required
    $reasonRequired = false;
    try {
        Employment\revoke_affiliation($pdo, $collegeAId, $collegeAStaffId, $studentAliceId, '  ');
    } catch (Employment\EmploymentError $e) {
        if ($e->statusCode === 422 && $e->reason === 'revocation_reason_required') {
            $reasonRequired = true;
        }
    }
    check($reasonRequired, 'Revocation reason required');

    // Valid revocation
    Employment\revoke_affiliation($pdo, $collegeAId, $collegeAStaffId, $studentAliceId, 'Student transferred to another university.');

    $profileAfter = Employment\get_profile($pdo, $studentAliceId);
    check($profileAfter['college_org_id'] === null, 'College org ID severed (null)');
    check($profileAfter['affiliation_status'] === 'REVOKED', 'Status marked as REVOKED');

    // Subsequent roster listing excludes Alice
    $students = Employment\list_college_students($pdo, $collegeAId, $collegeAStaffId);
    $aliceInRoster = false;
    foreach ($students as $s) {
        if ((int) $s['student_id'] === $studentAliceId) {
            $aliceInRoster = true;
        }
    }
    check(!$aliceInRoster, 'Revoked student is removed from college roster');
});

echo "====================================================================\n";
echo "RESULT: $passed passed, $failed failed\n";
echo "====================================================================\n";

exit($failed ? 1 : 0);
