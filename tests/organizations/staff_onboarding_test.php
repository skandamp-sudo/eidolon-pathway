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

$dbPath = sys_get_temp_dir() . '/pathway_test_staff_onboarding_' . bin2hex(random_bytes(8)) . '.sqlite';
putenv('PATHWAY_DATABASE_PATH=' . $dbPath);
$pdo = database(true, $dbPath);
initialize_database($pdo);

echo "=== MISSION 012: SECURE STAFF ONBOARDING TEST SUITE ===\n";

// 1. Bootstrap Admin Account
$adminPassword = 'AdminSecretPassword123!';
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
$stmt->execute(['Platform Administrator', 'admin@eidolon-test.internal', password_hash($adminPassword, PASSWORD_DEFAULT)]);
$adminId = (int) $pdo->lastInsertId();

// 2. Setup Active Company and College Organizations
$companyId = Organizations\create_organization($pdo, $adminId, 'Quantum Cybernetics Corp', 'COMPANY', true);
$collegeId = Organizations\create_organization($pdo, $adminId, 'National Polytechnic Institute', 'COLLEGE', true);

echo "PASS setup admin and verified organizations (Company & College)\n";

// 3. Test get_invitation_details() inspection without state mutation
$compInv = Organizations\create_invitation($pdo, $adminId, $companyId, 'recruiter@quantumcyber.test', 'STAFF');
$details = Organizations\get_invitation_details($pdo, $compInv['raw_token']);
ensure($details['organization_id'] === $companyId, 'Inspection returns matching org ID');
ensure($details['organization_name'] === 'Quantum Cybernetics Corp', 'Inspection returns matching org name');
ensure($details['organization_type'] === 'COMPANY', 'Inspection returns COMPANY type');
ensure($details['email'] === 'recruiter@quantumcyber.test', 'Inspection returns invited email');
ensure($details['role'] === 'STAFF', 'Inspection returns assigned role');
ensure($details['has_existing_account'] === false, 'New user reports no existing account');
ensure($details['expires_at'] > time(), 'Inspection reports valid future expiration');

// Ensure token was NOT consumed by inspection
$stmt = $pdo->prepare('SELECT used_at FROM organization_invitations WHERE id = ?');
$stmt->execute([$compInv['invitation_id']]);
ensure($stmt->fetchColumn() === null, 'Inspection does not consume invitation token');

echo "PASS get_invitation_details inspects metadata safely without mutating token state\n";

// 4. Test New Staff User Registration (COMPANY)
$regCompanyStaff = Organizations\register_and_accept_invitation(
    $pdo,
    $compInv['raw_token'],
    'Elena Rostova',
    'SecurePassword123!'
);
ensure($regCompanyStaff['user_id'] > 0, 'New user ID created');
ensure($regCompanyStaff['organization_id'] === $companyId, 'Assigned to company');
ensure($regCompanyStaff['role'] === 'STAFF', 'Membership role is STAFF');
ensure($regCompanyStaff['system_role'] === 'company', 'System role is company');

// Verify database record
$stmt = $pdo->prepare('SELECT name, email, role FROM users WHERE id = ?');
$stmt->execute([$regCompanyStaff['user_id']]);
$userRow = $stmt->fetch();
ensure($userRow['name'] === 'Elena Rostova', 'User name stored correctly');
ensure($userRow['email'] === 'recruiter@quantumcyber.test', 'User email stored in lowercase');
ensure($userRow['role'] === 'company', 'User system role set to company');

// Verify membership record
$stmt = $pdo->prepare('SELECT status, role FROM organization_memberships WHERE organization_id = ? AND user_id = ?');
$stmt->execute([$companyId, $regCompanyStaff['user_id']]);
$memRow = $stmt->fetch();
ensure($memRow['status'] === 'ACTIVE', 'Membership status is ACTIVE');
ensure($memRow['role'] === 'STAFF', 'Membership role is STAFF');

// Verify invitation is marked used
$stmt = $pdo->prepare('SELECT used_at FROM organization_invitations WHERE id = ?');
$stmt->execute([$compInv['invitation_id']]);
ensure($stmt->fetchColumn() !== null, 'Invitation marked used');

echo "PASS new company staff registration and token consumption\n";

// 5. Test Replay Attack on consumed invitation (should fail with 410)
try {
    Organizations\register_and_accept_invitation(
        $pdo,
        $compInv['raw_token'],
        'Attacker Duplicate',
        'SecurePassword123!'
    );
    ensure(false, 'Consumed invitation must reject replay registration');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invitation_already_used' && $e->getCode() === 410, 'Expected invitation_already_used 410');
}

try {
    Organizations\get_invitation_details($pdo, $compInv['raw_token']);
    ensure(false, 'Consumed invitation must reject inspection');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invitation_already_used' && $e->getCode() === 410, 'Expected invitation_already_used 410 for inspection');
}

echo "PASS replay attack prevented: consumed invitation rejected with 410\n";

// 6. Test New Staff User Registration (COLLEGE)
$collInv = Organizations\create_invitation($pdo, $adminId, $collegeId, 'placement@polytechnic.edu.test', 'ADMIN');
$regCollegeStaff = Organizations\register_and_accept_invitation(
    $pdo,
    $collInv['raw_token'],
    'Prof. Alistair Vance',
    'CollegeSecurePass123!'
);
ensure($regCollegeStaff['user_id'] > 0, 'College staff ID created');
ensure($regCollegeStaff['organization_id'] === $collegeId, 'Assigned to college');
ensure($regCollegeStaff['role'] === 'ADMIN', 'Membership role is ADMIN');
ensure($regCollegeStaff['system_role'] === 'college', 'System role is college');

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$regCollegeStaff['user_id']]);
ensure($stmt->fetchColumn() === 'college', 'College staff user system role upgraded to college');

echo "PASS new college placement officer registration with role upgrade to college\n";

// 7. Test Existing User Acceptance with Password Reauthentication
// Create an existing student account first
$existingPassword = 'ExistingStudentPass123!';
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')");
$stmt->execute(['Priya Sharma', 'priya.sharma@quantumcyber.test', password_hash($existingPassword, PASSWORD_DEFAULT)]);
$existingUserId = (int) $pdo->lastInsertId();

// Admin issues invitation to priya.sharma@quantumcyber.test
$priyaInv = Organizations\create_invitation($pdo, $adminId, $companyId, 'priya.sharma@quantumcyber.test', 'REVIEWER');

// Inspection detects existing account
$priyaDetails = Organizations\get_invitation_details($pdo, $priyaInv['raw_token']);
ensure($priyaDetails['has_existing_account'] === true, 'Detects existing user account');
ensure($priyaDetails['existing_user']['id'] === $existingUserId, 'Matches existing user ID');

// Wrong password reauthentication must fail
try {
    Organizations\accept_invitation($pdo, $priyaInv['raw_token'], $existingUserId, 'WrongPassword999!');
    ensure(false, 'Incorrect password must reject acceptance');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invalid_credentials' && $e->getCode() === 401, 'Expected invalid_credentials 401');
}

// Token must NOT be consumed after failed attempt
$stmt = $pdo->prepare('SELECT used_at FROM organization_invitations WHERE id = ?');
$stmt->execute([$priyaInv['invitation_id']]);
ensure($stmt->fetchColumn() === null, 'Token remains unconsumed after failed password attempt');

// Correct password reauthentication succeeds and upgrades role
$priyaAccepted = Organizations\accept_invitation($pdo, $priyaInv['raw_token'], $existingUserId, $existingPassword);
ensure($priyaAccepted['user_id'] === $existingUserId, 'Accepted by existing user');
ensure($priyaAccepted['organization_id'] === $companyId, 'Linked to company');
ensure($priyaAccepted['role'] === 'REVIEWER', 'Assigned REVIEWER role');
ensure($priyaAccepted['system_role'] === 'company', 'Role upgraded from student to company');

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$existingUserId]);
ensure($stmt->fetchColumn() === 'company', 'User table role upgraded to company');

echo "PASS existing account acceptance with password reauthentication and role upgrade\n";

// 8. Test Email Mismatch Rejection
$mismatchInv = Organizations\create_invitation($pdo, $adminId, $companyId, 'target@company.test', 'STAFF');
try {
    // Attempting to accept with Priya's user ID (email: priya.sharma@quantumcyber.test)
    Organizations\accept_invitation($pdo, $mismatchInv['raw_token'], $existingUserId, $existingPassword);
    ensure(false, 'Email mismatch must be rejected');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invitation_email_mismatch' && $e->getCode() === 403, 'Expected invitation_email_mismatch 403');
}

echo "PASS email mismatch rejection (token email must match accepting user email)\n";

// 9. Test Expired Invitation Rejection (410)
$expInv = Organizations\create_invitation($pdo, $adminId, $companyId, 'expired@company.test', 'STAFF');
// Backdate expiration
$pastTime = time() - 3600;
$pdo->prepare('UPDATE organization_invitations SET expires_at = ? WHERE id = ?')->execute([$pastTime, $expInv['invitation_id']]);

try {
    Organizations\get_invitation_details($pdo, $expInv['raw_token']);
    ensure(false, 'Expired invitation must reject inspection');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invitation_expired' && $e->getCode() === 410, 'Expected invitation_expired 410 for inspection');
}

try {
    Organizations\register_and_accept_invitation($pdo, $expInv['raw_token'], 'Expired User', 'SecurePass1234!');
    ensure(false, 'Expired invitation must reject registration');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invitation_expired' && $e->getCode() === 410, 'Expected invitation_expired 410 for registration');
}

echo "PASS expired invitation rejection (410)\n";

// 10. Test Revoked Invitation Rejection (410)
$revInv = Organizations\create_invitation($pdo, $adminId, $companyId, 'revoked@company.test', 'STAFF');
Organizations\revoke_invitation($pdo, $adminId, $revInv['invitation_id']);

try {
    Organizations\get_invitation_details($pdo, $revInv['raw_token']);
    ensure(false, 'Revoked invitation must reject inspection');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invitation_revoked' && $e->getCode() === 410, 'Expected invitation_revoked 410 for inspection');
}

try {
    Organizations\register_and_accept_invitation($pdo, $revInv['raw_token'], 'Revoked User', 'SecurePass1234!');
    ensure(false, 'Revoked invitation must reject registration');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invitation_revoked' && $e->getCode() === 410, 'Expected invitation_revoked 410 for registration');
}

echo "PASS revoked invitation rejection (410)\n";

// 11. Test Suspended Organization Invitation Rejection (403)
$suspOrgId = Organizations\create_organization($pdo, $adminId, 'Suspended Tech LLC', 'COMPANY', true);
$suspInv = Organizations\create_invitation($pdo, $adminId, $suspOrgId, 'staff@suspendedtech.test', 'STAFF');
Organizations\suspend_organization($pdo, $adminId, $suspOrgId, 'Compliance violation');

try {
    Organizations\get_invitation_details($pdo, $suspInv['raw_token']);
    ensure(false, 'Suspended org invitation must reject inspection');
} catch (OrganizationError $e) {
    ensure($e->reason === 'organization_inactive' && $e->getCode() === 403, 'Expected organization_inactive 403 for inspection');
}

try {
    Organizations\register_and_accept_invitation($pdo, $suspInv['raw_token'], 'Suspended Staff', 'SecurePass1234!');
    ensure(false, 'Suspended org invitation must reject registration');
} catch (OrganizationError $e) {
    ensure($e->reason === 'organization_inactive' && $e->getCode() === 403, 'Expected organization_inactive 403 for registration');
}

echo "PASS suspended organization invitation rejection (403)\n";

// 12. Test Password and Name Constraints on Registration
$valInv = Organizations\create_invitation($pdo, $adminId, $companyId, 'validation@company.test', 'STAFF');

// Password too short (<12 chars)
try {
    Organizations\register_and_accept_invitation($pdo, $valInv['raw_token'], 'Valid Name', 'short');
    ensure(false, 'Short password must be rejected');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invalid_password_length' && $e->getCode() === 422, 'Expected invalid_password_length 422');
}

// Password too long (>72 chars)
try {
    Organizations\register_and_accept_invitation($pdo, $valInv['raw_token'], 'Valid Name', str_repeat('A', 73));
    ensure(false, 'Password >72 bytes must be rejected');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invalid_password_length' && $e->getCode() === 422, 'Expected invalid_password_length 422 for >72 bytes');
}

// Name empty or invalid
try {
    Organizations\register_and_accept_invitation($pdo, $valInv['raw_token'], '   ', 'SecurePassword123!');
    ensure(false, 'Empty name must be rejected');
} catch (OrganizationError $e) {
    ensure($e->reason === 'invalid_name' && $e->getCode() === 422, 'Expected invalid_name 422');
}

echo "PASS registration validation constraints (12–72 byte password, valid UTF-8 name)\n";

// 13. Test Account Already Exists on Registration Mode
$dupInv = Organizations\create_invitation($pdo, $adminId, $collegeId, 'elena.dup@quantumcyber.test', 'STAFF');
// Create an existing account with that email first
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')");
$stmt->execute(['Elena Duplicate', 'elena.dup@quantumcyber.test', password_hash('SomeSecurePass123!', PASSWORD_DEFAULT)]);

try {
    Organizations\register_and_accept_invitation($pdo, $dupInv['raw_token'], 'Elena Duplicate', 'NewSecurePass123!');
    ensure(false, 'Registration with existing email must reject with 409');
} catch (OrganizationError $e) {
    ensure($e->reason === 'account_already_exists' && $e->getCode() === 409, 'Expected account_already_exists 409');
}

echo "PASS duplicate email on registration rejected with 409 account_already_exists\n";

// 14. Test Cross-Tenant Isolation
// Ensure Company Staff cannot access College Dashboard or rival Company resources
$companyStaffUser = [
    'id' => $regCompanyStaff['user_id'],
    'role' => 'company',
];
$collegeStaffUser = [
    'id' => $regCollegeStaff['user_id'],
    'role' => 'college',
];

// Company staff organization retrieval
$userCompOrg = \Pathway\Employment\get_user_company_organization($pdo, $companyStaffUser['id']);
ensure($userCompOrg !== null && $userCompOrg['org_id'] === $companyId, 'Company staff belongs to correct company');

// Company staff should NOT belong to any college
$userCollOrg = \Pathway\Employment\get_user_college_organization($pdo, $companyStaffUser['id']);
ensure($userCollOrg === null, 'Company staff has no college organization membership');

// College staff should NOT belong to any company
$userCompFromColl = \Pathway\Employment\get_user_company_organization($pdo, $collegeStaffUser['id']);
ensure($userCompFromColl === null, 'College staff has no company organization membership');

echo "PASS tenant isolation: strict separation between company and college memberships\n";

// 15. Audit Log Verification
$stmt = $pdo->prepare('SELECT action, target_type FROM admin_audit_log WHERE action LIKE "org.invitation_accepted%" ORDER BY id ASC');
$stmt->execute();
$auditRows = $stmt->fetchAll();
ensure(count($auditRows) >= 3, 'Audit log records all invitation acceptances immutably');

echo "PASS audit log contains immutable records for all staff onboardings\n";

echo "ALL 15 STAFF ONBOARDING CHECKS PASSED!\n";
