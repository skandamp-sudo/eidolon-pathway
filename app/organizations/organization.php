<?php
declare(strict_types=1);

namespace Pathway\Organizations;

use Pathway\HttpError;

final class OrganizationError extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $statusCode = 400)
    {
        parent::__construct($reason, $statusCode);
    }
}

/**
 * Record an immutable audit log entry.
 */
function record_audit(
    \PDO $pdo,
    ?int $actorUserId,
    string $action,
    string $targetType,
    ?int $targetId,
    array $metadata = [],
    string $ipAddress = '127.0.0.1'
): int {
    $now = time();
    $metadataJson = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    if (strlen($metadataJson) > 4096) {
        $metadataJson = json_encode(['error' => 'metadata_too_large'], JSON_THROW_ON_ERROR);
    }

    $statement = $pdo->prepare(
        'INSERT INTO admin_audit_log (actor_user_id, action, target_type, target_id, metadata_json, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([$actorUserId, $action, $targetType, $targetId, $metadataJson, $ipAddress, $now]);
    return (int) $pdo->lastInsertId();
}

/**
 * Generate a safe URL-friendly slug from an organization name.
 */
function slugify(string $name): string
{
    $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    if (strlen($slug) < 2) {
        throw new OrganizationError('invalid_organization_name', 422);
    }
    return substr($slug, 0, 60);
}

/**
 * Retrieve user's system-level role.
 */
function get_system_role(\PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $role = $stmt->fetchColumn();
    return is_string($role) ? $role : null;
}

/**
 * Retrieve active membership details for a user in an organization.
 */
function get_membership(\PDO $pdo, int $userId, int $orgId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.id, m.organization_id, m.user_id, m.role, m.status, o.status AS org_status, o.type AS org_type
         FROM organization_memberships m
         JOIN organizations o ON o.id = m.organization_id
         WHERE m.user_id = ? AND m.organization_id = ?'
    );
    $stmt->execute([$userId, $orgId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * Deny-by-default permission check.
 *
 * Supported permissions:
 * - perm.admin: Platform administration
 * - perm.org.create: Create an organization
 * - perm.org.approve: Approve a pending organization (Admin only)
 * - perm.org.suspend: Suspend an organization (Admin only)
 * - perm.org.view: View organization details
 * - perm.org.invite: Issue staff invitations (Admin or active Org OWNER/ADMIN)
 * - perm.org.mutate: Mutate organization resources (Admin or active Org OWNER/ADMIN/STAFF in an ACTIVE org)
 */
function can(\PDO $pdo, int $userId, string $permission, ?int $orgId = null): bool
{
    $systemRole = get_system_role($pdo, $userId);
    if ($systemRole === null) {
        return false;
    }

    // Platform admin has broad privileges, but still cannot perform org operations without a valid org
    $isPlatformAdmin = ($systemRole === 'admin');

    if ($permission === 'perm.admin') {
        return $isPlatformAdmin;
    }

    if ($permission === 'perm.org.approve' || $permission === 'perm.org.suspend') {
        return $isPlatformAdmin;
    }

    if ($permission === 'perm.org.create') {
        // Platform admin or authenticated user may initiate organization creation
        return true;
    }

    if ($orgId === null) {
        return false;
    }

    // Load organization status
    $stmt = $pdo->prepare('SELECT status, type FROM organizations WHERE id = ?');
    $stmt->execute([$orgId]);
    $org = $stmt->fetch();
    if (!$org) {
        return false;
    }

    // Platform admins can view any organization, even if pending or suspended
    if ($permission === 'perm.org.view' && $isPlatformAdmin) {
        return true;
    }

    $membership = get_membership($pdo, $userId, $orgId);
    if (!$membership || $membership['status'] !== 'ACTIVE') {
        return false;
    }

    if ($permission === 'perm.org.view') {
        return true;
    }

    // Privileged actions require the organization itself to be ACTIVE (unapproved/suspended orgs cannot operate)
    if ($org['status'] !== 'ACTIVE') {
        return false;
    }

    $memberRole = $membership['role'];

    if ($permission === 'perm.org.invite') {
        return $isPlatformAdmin || in_array($memberRole, ['OWNER', 'ADMIN'], true);
    }

    if ($permission === 'perm.org.mutate') {
        return $isPlatformAdmin || in_array($memberRole, ['OWNER', 'ADMIN', 'STAFF'], true);
    }

    return false;
}

/**
 * Enforce permission or fail-closed with HTTP 403.
 */
function require_permission(\PDO $pdo, int $userId, string $permission, ?int $orgId = null): void
{
    if (!can($pdo, $userId, $permission, $orgId)) {
        throw new HttpError(403, 'Access denied. You do not have permission to perform this action.');
    }
}

/**
 * Enforce per-resource ownership and prevent cross-organization/cross-tenant access.
 */
function verify_resource_ownership(\PDO $pdo, int $userId, int $expectedOrgId, int $resourceOrgId): void
{
    if ($expectedOrgId !== $resourceOrgId) {
        throw new HttpError(403, 'Cross-organization resource access is prohibited.');
    }
    require_permission($pdo, $userId, 'perm.org.mutate', $expectedOrgId);
}

/**
 * Create a new organization.
 * Platform admins can create pre-approved (ACTIVE) organizations.
 * General users create organizations with PENDING status.
 */
function create_organization(
    \PDO $pdo,
    int $actorUserId,
    string $name,
    string $type,
    bool $autoApprove = false
): int {
    $trimmedName = trim($name);
    if (strlen($trimmedName) < 2 || strlen($trimmedName) > 120) {
        throw new OrganizationError('invalid_organization_name', 422);
    }

    if (!in_array($type, ['COMPANY', 'COLLEGE'], true)) {
        throw new OrganizationError('invalid_organization_type', 422);
    }

    $systemRole = get_system_role($pdo, $actorUserId);
    $isPlatformAdmin = ($systemRole === 'admin');

    $status = ($isPlatformAdmin && $autoApprove) ? 'ACTIVE' : 'PENDING';
    $baseSlug = slugify($trimmedName);
    $slug = $baseSlug;
    $suffix = 1;

    $now = time();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        // Ensure slug uniqueness
        while (true) {
            $stmt = $pdo->prepare('SELECT count(*) FROM organizations WHERE slug = ?');
            $stmt->execute([$slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                break;
            }
            $slug = substr($baseSlug, 0, 50) . '-' . (++$suffix);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO organizations (name, slug, type, status, created_by_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$trimmedName, $slug, $type, $status, $actorUserId, $now, $now]);
        $orgId = (int) $pdo->lastInsertId();

        // Assign creator as OWNER in organization_memberships
        $stmt = $pdo->prepare(
            'INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orgId, $actorUserId, 'OWNER', 'ACTIVE', $now, $now]);

        record_audit($pdo, $actorUserId, 'org.create', 'organization', $orgId, [
            'name' => $trimmedName,
            'type' => $type,
            'status' => $status,
            'slug' => $slug,
        ]);

        $pdo->exec('COMMIT');
        return $orgId;
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

/**
 * Approve a pending organization (Platform Admin only).
 */
function approve_organization(\PDO $pdo, int $adminUserId, int $orgId): void
{
    require_permission($pdo, $adminUserId, 'perm.org.approve');

    $now = time();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $pdo->prepare('SELECT id, status, name FROM organizations WHERE id = ?');
        $stmt->execute([$orgId]);
        $org = $stmt->fetch();
        if (!$org) {
            throw new OrganizationError('organization_not_found', 404);
        }
        if ($org['status'] !== 'PENDING') {
            throw new OrganizationError('organization_not_pending', 409);
        }

        $stmt = $pdo->prepare('UPDATE organizations SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute(['ACTIVE', $now, $orgId]);

        record_audit($pdo, $adminUserId, 'org.approve', 'organization', $orgId, [
            'previous_status' => $org['status'],
            'new_status' => 'ACTIVE',
        ]);

        $pdo->exec('COMMIT');
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

/**
 * Suspend an active organization (Platform Admin only).
 */
function suspend_organization(\PDO $pdo, int $adminUserId, int $orgId, string $reason): void
{
    require_permission($pdo, $adminUserId, 'perm.org.suspend');

    $now = time();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $pdo->prepare('SELECT id, status, name FROM organizations WHERE id = ?');
        $stmt->execute([$orgId]);
        $org = $stmt->fetch();
        if (!$org) {
            throw new OrganizationError('organization_not_found', 404);
        }
        if ($org['status'] === 'SUSPENDED') {
            throw new OrganizationError('organization_already_suspended', 409);
        }

        $stmt = $pdo->prepare('UPDATE organizations SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute(['SUSPENDED', $now, $orgId]);

        record_audit($pdo, $adminUserId, 'org.suspend', 'organization', $orgId, [
            'previous_status' => $org['status'],
            'new_status' => 'SUSPENDED',
            'reason' => substr($reason, 0, 200),
        ]);

        $pdo->exec('COMMIT');
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

/**
 * Create a single-use, expiring staff invitation.
 * Stores only the SHA-256 hash of the token. Returns raw token to caller once.
 */
function create_invitation(
    \PDO $pdo,
    int $actorUserId,
    int $orgId,
    string $email,
    string $role = 'STAFF',
    int $ttlSeconds = 86400
): array {
    require_permission($pdo, $actorUserId, 'perm.org.invite', $orgId);

    $normalizedEmail = strtolower(trim($email));
    if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
        throw new OrganizationError('invalid_email', 422);
    }

    if (!in_array($role, ['OWNER', 'ADMIN', 'STAFF', 'REVIEWER'], true)) {
        throw new OrganizationError('invalid_role', 422);
    }

    // Check if target is already an active member of this org
    $stmt = $pdo->prepare(
        'SELECT count(*) FROM organization_memberships m
         JOIN users u ON u.id = m.user_id
         WHERE m.organization_id = ? AND u.email = ? AND m.status = "ACTIVE"'
    );
    $stmt->execute([$orgId, $normalizedEmail]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new OrganizationError('user_already_member', 409);
    }

    // Generate high-entropy 256-bit random raw secret token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    $now = time();
    $expiresAt = $now + max(300, min(604800, $ttlSeconds)); // between 5m and 7d

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        // Expire any existing pending invitations for this email & org
        $stmt = $pdo->prepare(
            'UPDATE organization_invitations SET used_at = ? WHERE organization_id = ? AND email = ? AND used_at IS NULL'
        );
        $stmt->execute([$now, $orgId, $normalizedEmail]);

        $stmt = $pdo->prepare(
            'INSERT INTO organization_invitations (organization_id, email, role, token_hash, invited_by_user_id, expires_at, used_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?)'
        );
        $stmt->execute([$orgId, $normalizedEmail, $role, $tokenHash, $actorUserId, $expiresAt, $now]);
        $invitationId = (int) $pdo->lastInsertId();

        record_audit($pdo, $actorUserId, 'org.invite', 'invitation', $invitationId, [
            'organization_id' => $orgId,
            'email' => $normalizedEmail,
            'role' => $role,
            'expires_at' => $expiresAt,
        ]);

        $pdo->exec('COMMIT');

        return [
            'invitation_id' => $invitationId,
            'organization_id' => $orgId,
            'email' => $normalizedEmail,
            'role' => $role,
            'raw_token' => $rawToken,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ];
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

/**
 * Accept a single-use staff invitation for an existing authenticated user.
 * Verifies token hash, checks expiration, checks unused status, checks org active status.
 * If password is provided, verifies user credentials to ensure the session claimant
 * is the legitimate account holder before binding the invitation.
 */
function accept_invitation(\PDO $pdo, string $rawToken, int $userId, ?string $password = null): array
{
    $tokenHash = hash('sha256', trim($rawToken));
    $now = time();

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $pdo->prepare(
            'SELECT i.id, i.organization_id, i.email, i.role, i.expires_at, i.used_at, o.status AS org_status
             FROM organization_invitations i
             JOIN organizations o ON o.id = i.organization_id
             WHERE i.token_hash = ?'
        );
        $stmt->execute([$tokenHash]);
        $invitation = $stmt->fetch();

        if (!$invitation) {
            throw new OrganizationError('invitation_not_found', 404);
        }

        if ($invitation['used_at'] !== null) {
            throw new OrganizationError('invitation_already_used', 410);
        }

        if ($invitation['expires_at'] < $now) {
            throw new OrganizationError('invitation_expired', 410);
        }

        if ($invitation['org_status'] !== 'ACTIVE') {
            throw new OrganizationError('organization_inactive', 403);
        }

        // Verify the accepting user's email matches the invited email
        $stmt = $pdo->prepare('SELECT email, password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch();
        if (!$userRow || !is_string($userRow['email']) || strtolower($userRow['email']) !== strtolower($invitation['email'])) {
            throw new OrganizationError('invitation_email_mismatch', 403);
        }

        // If credentials provided, verify password to prevent unverified session hijacking
        if ($password !== null) {
            if (!password_verify($password, $userRow['password_hash'])) {
                throw new OrganizationError('invalid_credentials', 401);
            }
        }

        // Mark single-use invitation as consumed
        $stmt = $pdo->prepare('UPDATE organization_invitations SET used_at = ? WHERE id = ?');
        $stmt->execute([$now, $invitation['id']]);

        // Insert or activate organization membership
        $stmt = $pdo->prepare(
            'INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at)
             VALUES (?, ?, ?, "ACTIVE", ?, ?)
             ON CONFLICT(organization_id, user_id) DO UPDATE SET role = excluded.role, status = "ACTIVE", updated_at = excluded.updated_at'
        );
        $stmt->execute([$invitation['organization_id'], $userId, $invitation['role'], $now, $now]);

        record_audit($pdo, $userId, 'org.invitation_accepted', 'invitation', $invitation['id'], [
            'organization_id' => $invitation['organization_id'],
            'role' => $invitation['role'],
            'email' => $invitation['email'],
            'email_ownership_verified_by_token' => true,
        ]);

        $pdo->exec('COMMIT');

        return [
            'organization_id' => (int) $invitation['organization_id'],
            'role' => $invitation['role'],
        ];
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

/**
 * Register a new staff account and redeem invitation in a single atomic operation.
 * Demonstrating possession of the high-entropy raw invitation token proves
 * delivery to and ownership of the invited email address.
 */
function register_and_accept_invitation(
    \PDO $pdo,
    string $rawToken,
    string $name,
    string $password
): array {
    $trimmedName = trim($name);
    if ($trimmedName === '' || strlen($trimmedName) > 100 || preg_match('/[\x00-\x1f\x7f]/', $trimmedName) || !preg_match('//u', $trimmedName)) {
        throw new OrganizationError('invalid_name', 422);
    }

    if (strlen($password) < 12 || strlen($password) > 72) {
        throw new OrganizationError('invalid_password_length', 422);
    }

    $tokenHash = hash('sha256', trim($rawToken));
    $now = time();

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $pdo->prepare(
            'SELECT i.id, i.organization_id, i.email, i.role, i.expires_at, i.used_at, o.status AS org_status, o.type AS org_type
             FROM organization_invitations i
             JOIN organizations o ON o.id = i.organization_id
             WHERE i.token_hash = ?'
        );
        $stmt->execute([$tokenHash]);
        $invitation = $stmt->fetch();

        if (!$invitation) {
            throw new OrganizationError('invitation_not_found', 404);
        }

        if ($invitation['used_at'] !== null) {
            throw new OrganizationError('invitation_already_used', 410);
        }

        if ($invitation['expires_at'] < $now) {
            throw new OrganizationError('invitation_expired', 410);
        }

        if ($invitation['org_status'] !== 'ACTIVE') {
            throw new OrganizationError('organization_inactive', 403);
        }

        $email = strtolower($invitation['email']);

        // Check if an account already exists with this email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new OrganizationError('account_already_exists', 409);
        }

        // Create the user account with role matching the organization type
        $systemRole = match ($invitation['org_type']) {
            'COMPANY' => 'company',
            'COLLEGE' => 'college',
            default => 'student',
        };
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$trimmedName, $email, $hash, $systemRole]);
        $newUserId = (int) $pdo->lastInsertId();

        // Mark single-use invitation as consumed
        $stmt = $pdo->prepare('UPDATE organization_invitations SET used_at = ? WHERE id = ?');
        $stmt->execute([$now, $invitation['id']]);

        // Insert organization membership
        $stmt = $pdo->prepare(
            'INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at)
             VALUES (?, ?, ?, "ACTIVE", ?, ?)'
        );
        $stmt->execute([$invitation['organization_id'], $newUserId, $invitation['role'], $now, $now]);

        record_audit($pdo, $newUserId, 'org.invitation_accepted_with_registration', 'invitation', $invitation['id'], [
            'organization_id' => $invitation['organization_id'],
            'role' => $invitation['role'],
            'email' => $email,
            'email_ownership_verified_by_token' => true,
        ]);

        $pdo->exec('COMMIT');

        return [
            'user_id' => $newUserId,
            'organization_id' => (int) $invitation['organization_id'],
            'role' => $invitation['role'],
        ];
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

/**
 * Retrieve current platform admin user from active session.
 */
function current_platform_admin(\PDO $pdo): array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) || $id < 1) {
        throw new HttpError(401, 'Sign in to access platform administration.');
    }
    $statement = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $statement->execute([$id]);
    $user = $statement->fetch();
    if (!$user || $user['role'] !== 'admin' || ($_SESSION['role'] ?? null) !== 'admin') {
        throw new HttpError(403, 'Platform administrator privileges are required.');
    }
    return $user;
}

/**
 * List organizations with optional status filter.
 */
function list_organizations(\PDO $pdo, ?string $status = null, int $limit = 50): array
{
    $sql = "
        SELECT o.id, o.name, o.slug, o.type, o.status, o.created_at, o.updated_at,
               u.name AS creator_name, u.email AS creator_email,
               (SELECT count(*) FROM jobs j WHERE j.organization_id = o.id) AS job_count,
               (SELECT count(*) FROM organization_memberships m WHERE m.organization_id = o.id AND m.status = 'ACTIVE') AS member_count
        FROM organizations o
        LEFT JOIN users u ON u.id = o.created_by_user_id
    ";
    $params = [];
    if ($status !== null) {
        $sql .= " WHERE o.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY o.id DESC LIMIT ?";
    $params[] = max(1, min(200, $limit));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * List recent audit log entries for administration and compliance.
 */
function list_audit_logs(\PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare("
        SELECT a.id, a.actor_user_id, a.action, a.target_type, a.target_id,
               a.metadata_json, a.ip_address, a.created_at,
               u.name AS actor_name, u.email AS actor_email, u.role AS actor_role
        FROM admin_audit_log a
        LEFT JOIN users u ON u.id = a.actor_user_id
        ORDER BY a.id DESC
        LIMIT ?
    ");
    $stmt->execute([max(1, min(200, $limit))]);
    return $stmt->fetchAll();
}

