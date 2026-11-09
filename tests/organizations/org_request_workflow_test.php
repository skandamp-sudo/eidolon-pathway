<?php
declare(strict_types=1);

namespace Pathway\Tests;

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/organizations/organization.php';
require_once __DIR__ . '/../../app/employment/jobs.php';

use function Pathway\database;
use function Pathway\initialize_database;
use Pathway\Organizations;
use Pathway\Organizations\OrganizationError;

function ensure(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

$dbPath = sys_get_temp_dir() . '/pathway_test_org_request_' . bin2hex(random_bytes(8)) . '.sqlite';
putenv('PATHWAY_DATABASE_PATH=' . $dbPath);
$pdo = database(true, $dbPath);
initialize_database($pdo);

echo "=== CHECKPOINT 009B: ORGANIZATION REQUESTS & EIDOLON CONTROL TESTS ===\n";

// 1. Setup Admin Account
$adminPassword = 'AdminSecretPassword123!';
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
$stmt->execute(['Platform Administrator', 'admin@eidolon-test.internal', password_hash($adminPassword, PASSWORD_DEFAULT)]);
$adminId = (int) $pdo->lastInsertId();

// Setup Student Account (unauthorized for admin actions)
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')");
$stmt->execute(['Eve Attacker', 'eve@student.internal', password_hash('EvePassword123!', PASSWORD_DEFAULT)]);
$studentId = (int) $pdo->lastInsertId();

echo "PASS bootstrap admin and unauthorized student\n";

// 2. Submit Organization Requests (Company and College)
$reqCompanyId = Organizations\submit_organization_request($pdo, [
    'name' => 'Nova Robotics AI',
    'type' => 'COMPANY',
    'website' => 'https://novarobotics.ai',
    'contact_name' => 'Dr. Maya Lin',
    'contact_email' => 'maya.lin@novarobotics.ai',
    'designation' => 'Director of Research',
    'verification_notes' => 'Corporate registration CR-883921. Domain DNS verified and active robotics lab.',
]);
ensure($reqCompanyId > 0, 'Company request must return valid positive ID');

$reqCollegeId = Organizations\submit_organization_request($pdo, [
    'name' => 'Bangalore Institute of Advanced Computing',
    'type' => 'COLLEGE',
    'website' => 'biac.edu.in', // test URL normalization
    'contact_name' => 'Prof. S. Narayanan',
    'contact_email' => 'narayanan@biac.edu.in',
    'designation' => 'Dean of Placement & Training',
    'verification_notes' => 'AICTE Accreditation ID AC-2024-9912. Official university website directory link included.',
]);
ensure($reqCollegeId > 0, 'College request must return valid positive ID');

$companyReq = Organizations\get_organization_request($pdo, $reqCompanyId);
ensure($companyReq !== null && $companyReq['name'] === 'Nova Robotics AI', 'Must retrieve created company request');
ensure($companyReq['status'] === 'PENDING', 'Request status must be PENDING');

$collegeReq = Organizations\get_organization_request($pdo, $reqCollegeId);
ensure($collegeReq['website'] === 'https://biac.edu.in', 'Website URL must be normalized with https://');

echo "PASS submit valid organization requests for company and college\n";

// 3. Validation Rules
try {
    Organizations\submit_organization_request($pdo, [
        'name' => 'A', // too short
        'type' => 'COMPANY',
        'website' => 'https://example.com',
        'contact_name' => 'Valid Name',
        'contact_email' => 'valid@example.com',
        'designation' => 'Lead',
        'verification_notes' => 'Valid verification note longer than 5 chars',
    ]);
    ensure(false, 'Too short organization name must fail');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invalid_organization_name', 'Must catch invalid_organization_name');
}

try {
    Organizations\submit_organization_request($pdo, [
        'name' => 'Valid Company',
        'type' => 'INVALID_TYPE',
        'website' => 'https://example.com',
        'contact_name' => 'Valid Name',
        'contact_email' => 'valid2@example.com',
        'designation' => 'Lead',
        'verification_notes' => 'Valid verification note longer than 5 chars',
    ]);
    ensure(false, 'Invalid organization type must fail');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invalid_organization_type', 'Must catch invalid_organization_type');
}

try {
    Organizations\submit_organization_request($pdo, [
        'name' => 'Valid Company',
        'type' => 'COMPANY',
        'website' => 'https://example.com',
        'contact_name' => 'Valid Name',
        'contact_email' => 'not-an-email',
        'designation' => 'Lead',
        'verification_notes' => 'Valid verification note longer than 5 chars',
    ]);
    ensure(false, 'Invalid contact email must fail');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invalid_contact_email', 'Must catch invalid_contact_email');
}

echo "PASS validation checks for name, type, and email\n";

// 4. Duplicate Flood Control
try {
    Organizations\submit_organization_request($pdo, [
        'name' => 'Duplicate Nova',
        'type' => 'COMPANY',
        'website' => 'https://novarobotics.ai',
        'contact_name' => 'Dr. Maya Lin',
        'contact_email' => 'maya.lin@novarobotics.ai', // already pending!
        'designation' => 'Director',
        'verification_notes' => 'Another request while pending',
    ]);
    ensure(false, 'Duplicate pending request from same email must fail');
} catch (OrganizationError $e) {
    ensure($e->reason === 'request_already_pending', 'Must catch request_already_pending');
}
echo "PASS flood control: duplicate pending request from same email rejected\n";

// 5. Unauthorized User Cannot Review Request
try {
    Organizations\review_organization_request($pdo, $studentId, $reqCompanyId, 'APPROVE');
    ensure(false, 'Student cannot review organization requests');
} catch (\Pathway\HttpError $e) {
    ensure($e->status === 403, 'Must reject unauthorized review with 403');
}
echo "PASS unauthorized student cannot review organization requests\n";

// 6. Admin Approves Company Request
$approvalRes = Organizations\review_organization_request($pdo, $adminId, $reqCompanyId, 'APPROVE', 'Verified domain and registration.');
ensure($approvalRes['status'] === 'APPROVED', 'Approval result status must be APPROVED');
ensure($approvalRes['organization_id'] > 0, 'Approval must create active organization ID');
ensure(!empty($approvalRes['invitation']['raw_token']), 'Approval must issue raw invitation token');
ensure($approvalRes['invitation']['role'] === 'OWNER', 'Invitation role must be OWNER');

// Verify database state of approved request
$updatedReq = Organizations\get_organization_request($pdo, $reqCompanyId);
ensure($updatedReq['status'] === 'APPROVED', 'Request status in DB must be APPROVED');
ensure((int) $updatedReq['organization_id'] === $approvalRes['organization_id'], 'Request must reference created org');
ensure((int) $updatedReq['reviewed_by_user_id'] === $adminId, 'Reviewed by user must match admin');

// Verify newly created organization is ACTIVE
$orgStmt = $pdo->prepare('SELECT status, name, type FROM organizations WHERE id = ?');
$orgStmt->execute([$approvalRes['organization_id']]);
$newOrg = $orgStmt->fetch();
ensure($newOrg['status'] === 'ACTIVE', 'Created organization must be immediately ACTIVE');
ensure($newOrg['name'] === 'Nova Robotics AI', 'Organization name must match request');

echo "PASS admin approves company request, creating active org and OWNER invitation\n";

// 7. Admin Rejects College Request
$rejectRes = Organizations\review_organization_request($pdo, $adminId, $reqCollegeId, 'REJECT', 'AICTE ID could not be validated with state registry.');
ensure($rejectRes['status'] === 'REJECTED', 'Rejection status must be REJECTED');

$rejectedReq = Organizations\get_organization_request($pdo, $reqCollegeId);
ensure($rejectedReq['status'] === 'REJECTED', 'Request status in DB must be REJECTED');
ensure($rejectedReq['review_notes'] === 'AICTE ID could not be validated with state registry.', 'Review notes must match');

// Cannot re-review a completed request
try {
    Organizations\review_organization_request($pdo, $adminId, $reqCollegeId, 'APPROVE');
    ensure(false, 'Cannot review already completed request');
} catch (OrganizationError $e) {
    ensure($e->reason === 'request_not_pending', 'Must catch request_not_pending');
}
echo "PASS admin rejects college request with audit note and prevents re-review\n";

// 8. Staff Invitation Revocation
// Issue a staff invitation
$invRes = Organizations\create_invitation($pdo, $adminId, $approvalRes['organization_id'], 'lead.engineer@novarobotics.ai', 'STAFF');
$invId = $invRes['invitation_id'];
$rawToken = $invRes['raw_token'];

// Student cannot revoke invitation
try {
    Organizations\revoke_invitation($pdo, $studentId, $invId);
    ensure(false, 'Student cannot revoke staff invitation');
} catch (\Pathway\HttpError $e) {
    ensure($e->status === 403, 'Unauthorized revocation must fail closed 403');
}

// Admin revokes invitation
Organizations\revoke_invitation($pdo, $adminId, $invId);

// Attempting to accept a revoked invitation MUST FAIL
try {
    Organizations\register_and_accept_invitation($pdo, $rawToken, 'Lead Engineer', 'SecureP@ssw0rd1234');
    ensure(false, 'Revoked invitation cannot be redeemed');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invitation_revoked', 'Must catch invitation_revoked');
}

// Cannot re-revoke an already revoked invitation
try {
    Organizations\revoke_invitation($pdo, $adminId, $invId);
    ensure(false, 'Cannot revoke already revoked invitation');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invitation_already_revoked', 'Must catch invitation_already_revoked');
}
echo "PASS staff invitation revocation cuts off redemption and prevents re-revocation\n";

// 9. List All Invitations Dynamic State
$invitations = Organizations\list_all_invitations($pdo);
ensure(count($invitations) >= 2, 'Must list all issued invitations');
$foundRevoked = false;
$foundPending = false;
foreach ($invitations as $inv) {
    if ($inv['id'] === $invId) {
        ensure($inv['state'] === 'REVOKED', 'Revoked invitation must have state REVOKED');
        $foundRevoked = true;
    }
    if ($inv['id'] === $approvalRes['invitation']['invitation_id']) {
        ensure($inv['state'] === 'PENDING', 'Unused invitation must have state PENDING');
        $foundPending = true;
    }
}
ensure($foundRevoked && $foundPending, 'Must correctly identify both REVOKED and PENDING invitations');
echo "PASS list_all_invitations calculates dynamic invitation states\n";

// 10. List All Users with Filtering
$allUsers = Organizations\list_all_users($pdo);
ensure(count($allUsers) >= 2, 'Must list all registered users');

$adminUsers = Organizations\list_all_users($pdo, 'admin');
ensure(count($adminUsers) === 1 && $adminUsers[0]['email'] === 'admin@eidolon-test.internal', 'Must filter users by role');

$searchedUsers = Organizations\list_all_users($pdo, null, 'Eve');
ensure(count($searchedUsers) === 1 && $searchedUsers[0]['name'] === 'Eve Attacker', 'Must search users by query');
echo "PASS list_all_users supports search and role filters\n";

// 11. System Health Metrics for EIDOLON CONTROL
$metrics = Organizations\get_admin_system_metrics($pdo);
ensure(isset($metrics['pending_requests']), 'Must have pending_requests metric');
ensure(isset($metrics['active_companies']) && $metrics['active_companies'] >= 1, 'Must count active companies');
ensure(isset($metrics['total_audit_events']) && $metrics['total_audit_events'] >= 4, 'Must count immutable audit events');
ensure(isset($metrics['active_invitations']) && $metrics['active_invitations'] >= 1, 'Must count active unrevoked invitations');
echo "PASS get_admin_system_metrics computes live database aggregates\n";

// Cleanup test db
@unlink($dbPath);
echo "====================================================================\n";
echo "RESULT: 11 passed, 0 failed\n";
echo "====================================================================\n";
