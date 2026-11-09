<?php
declare(strict_types=1);

namespace Pathway\Employment;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../organizations/organization.php';

use Pathway\HttpError;
use Pathway\Organizations;

final class EmploymentError extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $statusCode = 400)
    {
        parent::__construct($reason, $statusCode);
    }
}

/**
 * Retrieve student profile by user ID.
 */
function get_profile(\PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('
        SELECT p.user_id, u.name, u.email, u.role,
               p.college, p.skills, p.bio, p.github_username,
               p.college_org_id, p.college_share_consent, p.updated_at,
               o.name AS college_org_name
        FROM student_profiles p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN organizations o ON o.id = p.college_org_id
        WHERE p.user_id = ?
    ');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * Save / update student profile.
 */
function save_profile(\PDO $pdo, int $userId, array $data): array
{
    // Verify user exists and is a student
    $userStmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if (!$user || $user['role'] !== 'student') {
        throw new EmploymentError('student_required', 403);
    }

    $existing = get_profile($pdo, $userId) ?? [];

    $college = isset($data['college']) ? trim((string) $data['college']) : ($existing['college'] ?? '');
    if (strlen($college) > 200) {
        throw new EmploymentError('college_too_long', 422);
    }

    $skills = isset($data['skills']) ? trim((string) $data['skills']) : ($existing['skills'] ?? '');
    if (strlen($skills) > 500) {
        throw new EmploymentError('skills_too_long', 422);
    }

    $bio = isset($data['bio']) ? trim((string) $data['bio']) : ($existing['bio'] ?? '');
    if (strlen($bio) > 2000) {
        throw new EmploymentError('bio_too_long', 422);
    }

    $github = isset($data['github_username']) ? trim((string) $data['github_username']) : ($existing['github_username'] ?? '');
    if ($github !== '') {
        if (strlen($github) > 39 || !preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,37}[a-zA-Z0-9])?$/', $github)) {
            throw new EmploymentError('invalid_github_username', 422);
        }
    }

    $collegeOrgId = $existing['college_org_id'] ?? null;
    if (array_key_exists('college_org_id', $data)) {
        if (!empty($data['college_org_id'])) {
            $orgId = (int) $data['college_org_id'];
            $orgStmt = $pdo->prepare("SELECT id, type, status FROM organizations WHERE id = ? AND type = 'COLLEGE' AND status = 'ACTIVE'");
            $orgStmt->execute([$orgId]);
            if (!$orgStmt->fetch()) {
                throw new EmploymentError('invalid_college_organization', 422);
            }
            $collegeOrgId = $orgId;
        } else {
            $collegeOrgId = null;
        }
    }

    $consent = (int) ($existing['college_share_consent'] ?? 0);
    if (array_key_exists('college_share_consent', $data)) {
        $val = $data['college_share_consent'];
        $consent = ($val === 1 || $val === '1' || $val === true || $val === 'on') ? 1 : 0;
    }

    $now = time();

    $stmt = $pdo->prepare('
        UPDATE student_profiles
        SET college = ?, skills = ?, bio = ?, github_username = ?, college_org_id = ?, college_share_consent = ?, updated_at = ?
        WHERE user_id = ?
    ');
    $stmt->execute([$college, $skills, $bio, $github, $collegeOrgId, $consent, $now, $userId]);

    return get_profile($pdo, $userId) ?? [];
}

/**
 * Company-controlled job creation with strict authorization.
 * Only verified active companies can post jobs.
 */
function create_job(\PDO $pdo, int $userId, int $organizationId, array $data): int
{
    // Verify caller has mutation permission in the organization
    if (!Organizations\can($pdo, $userId, 'perm.org.mutate', $organizationId)) {
        throw new EmploymentError('unauthorized_company_action', 403);
    }

    // Verify organization is an active COMPANY
    $orgStmt = $pdo->prepare('SELECT id, name, type, status FROM organizations WHERE id = ?');
    $orgStmt->execute([$organizationId]);
    $org = $orgStmt->fetch();
    if (!$org || $org['type'] !== 'COMPANY' || $org['status'] !== 'ACTIVE') {
        throw new EmploymentError('verified_active_company_required', 403);
    }

    $title = trim((string) ($data['title'] ?? ''));
    if (strlen($title) < 3 || strlen($title) > 140) {
        throw new EmploymentError('invalid_job_title', 422);
    }

    $description = trim((string) ($data['description'] ?? ''));
    if (strlen($description) < 10 || strlen($description) > 5000) {
        throw new EmploymentError('invalid_job_description', 422);
    }

    $skills = trim((string) ($data['skills'] ?? ''));
    if (strlen($skills) < 1 || strlen($skills) > 500) {
        throw new EmploymentError('invalid_job_skills', 422);
    }

    $category = strtolower(trim((string) ($data['role_category'] ?? 'general')));
    if (!in_array($category, ['frontend', 'backend', 'fullstack', 'general'], true)) {
        throw new EmploymentError('invalid_role_category', 422);
    }

    $location = trim((string) ($data['location'] ?? 'Remote'));
    if (strlen($location) < 2 || strlen($location) > 100) {
        throw new EmploymentError('invalid_job_location', 422);
    }

    $status = strtolower(trim((string) ($data['status'] ?? 'active')));
    if (!in_array($status, ['draft', 'active', 'closed'], true)) {
        throw new EmploymentError('invalid_job_status', 422);
    }

    $now = time();
    $stmt = $pdo->prepare('
        INSERT INTO jobs (organization_id, created_by_user_id, title, description, skills, role_category, location, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$organizationId, $userId, $title, $description, $skills, $category, $location, $status, $now, $now]);
    $jobId = (int) $pdo->lastInsertId();

    Organizations\record_audit($pdo, $userId, 'job.create', 'job', $jobId, [
        'organization_id' => $organizationId,
        'title' => $title,
        'role_category' => $category,
    ]);

    return $jobId;
}

/**
 * Update an existing company job with strict tenant authorization.
 */
function update_job(\PDO $pdo, int $userId, int $organizationId, int $jobId, array $data): void
{
    if (!Organizations\can($pdo, $userId, 'perm.org.mutate', $organizationId)) {
        throw new EmploymentError('unauthorized_company_action', 403);
    }

    $orgStmt = $pdo->prepare('SELECT id, type, status FROM organizations WHERE id = ?');
    $orgStmt->execute([$organizationId]);
    $org = $orgStmt->fetch();
    if (!$org || $org['type'] !== 'COMPANY' || $org['status'] !== 'ACTIVE') {
        throw new EmploymentError('verified_active_company_required', 403);
    }

    // Verify job belongs to this company
    $checkStmt = $pdo->prepare('SELECT id, status FROM jobs WHERE id = ? AND organization_id = ?');
    $checkStmt->execute([$jobId, $organizationId]);
    $existing = $checkStmt->fetch();
    if (!$existing) {
        throw new EmploymentError('job_not_found', 404);
    }

    $title = trim((string) ($data['title'] ?? ''));
    if (strlen($title) < 3 || strlen($title) > 140) {
        throw new EmploymentError('invalid_job_title', 422);
    }

    $description = trim((string) ($data['description'] ?? ''));
    if (strlen($description) < 10 || strlen($description) > 5000) {
        throw new EmploymentError('invalid_job_description', 422);
    }

    $skills = trim((string) ($data['skills'] ?? ''));
    if (strlen($skills) < 1 || strlen($skills) > 500) {
        throw new EmploymentError('invalid_job_skills', 422);
    }

    $category = strtolower(trim((string) ($data['role_category'] ?? 'general')));
    if (!in_array($category, ['frontend', 'backend', 'fullstack', 'general'], true)) {
        throw new EmploymentError('invalid_role_category', 422);
    }

    $location = trim((string) ($data['location'] ?? 'Remote'));
    if (strlen($location) < 2 || strlen($location) > 100) {
        throw new EmploymentError('invalid_job_location', 422);
    }

    $status = strtolower(trim((string) ($data['status'] ?? 'active')));
    if (!in_array($status, ['draft', 'active', 'closed'], true)) {
        throw new EmploymentError('invalid_job_status', 422);
    }

    $now = time();
    $stmt = $pdo->prepare('
        UPDATE jobs
        SET title = ?, description = ?, skills = ?, role_category = ?, location = ?, status = ?, updated_at = ?
        WHERE id = ? AND organization_id = ?
    ');
    $stmt->execute([$title, $description, $skills, $category, $location, $status, $now, $jobId, $organizationId]);

    Organizations\record_audit($pdo, $userId, 'job.update', 'job', $jobId, [
        'organization_id' => $organizationId,
        'title' => $title,
        'status' => $status,
    ]);
}

/**
 * List all jobs posted by a company with applicant counts.
 */
function list_company_jobs(\PDO $pdo, int $userId, int $organizationId): array
{
    if (!Organizations\can($pdo, $userId, 'perm.org.view', $organizationId)) {
        throw new EmploymentError('unauthorized_company_action', 403);
    }

    $stmt = $pdo->prepare("
        SELECT j.*, count(a.id) AS applicant_count
        FROM jobs j
        LEFT JOIN applications a ON a.job_id = j.id
        WHERE j.organization_id = ?
        GROUP BY j.id
        ORDER BY j.id DESC
    ");
    $stmt->execute([$organizationId]);
    return $stmt->fetchAll();
}

/**
 * Find active company organization membership for a user.
 */
function get_user_company_organization(\PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT m.organization_id, m.role AS member_role, m.status AS member_status,
               o.id AS org_id, o.name AS org_name, o.slug AS org_slug, o.status AS org_status, o.type AS org_type
        FROM organization_memberships m
        JOIN organizations o ON o.id = m.organization_id
        WHERE m.user_id = ? AND o.type = 'COMPANY' AND m.status = 'ACTIVE'
        ORDER BY o.id ASC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * Retrieve current authenticated company staff and their active company organization.
 * Fails closed (401 if unauthenticated, 403 if invalid role, missing membership, or non-active company).
 */
function current_company_staff(\PDO $pdo): array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) || $id < 1) {
        throw new HttpError(401, 'Sign in with your company credentials to continue.');
    }
    $statement = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $statement->execute([$id]);
    $user = $statement->fetch();
    if (!$user || $user['role'] !== 'company' || ($_SESSION['role'] ?? null) !== 'company') {
        throw new HttpError(403, 'A company staff account is required.');
    }

    $membership = get_user_company_organization($pdo, (int) $user['id']);
    if (!$membership) {
        throw new HttpError(403, 'No company organization is associated with this account.');
    }
    if ($membership['org_status'] !== 'ACTIVE') {
        throw new HttpError(403, 'Your company organization is ' . strtolower($membership['org_status']) . '. Awaiting platform administrator approval.');
    }
    return [
        'user' => $user,
        'org' => $membership,
    ];
}

/**
 * Find active college organization membership for a user.
 */
function get_user_college_organization(\PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT m.organization_id, m.role AS member_role, m.status AS member_status,
               o.id AS org_id, o.name AS org_name, o.slug AS org_slug, o.status AS org_status, o.type AS org_type
        FROM organization_memberships m
        JOIN organizations o ON o.id = m.organization_id
        WHERE m.user_id = ? AND o.type = 'COLLEGE' AND m.status = 'ACTIVE'
        ORDER BY o.id ASC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * Retrieve current authenticated college staff and their active college organization.
 * Fails closed (401 if unauthenticated, 403 if invalid role, missing membership, or non-active college).
 */
function current_college_staff(\PDO $pdo): array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) || $id < 1) {
        throw new HttpError(401, 'Sign in with your college credentials to continue.');
    }
    $statement = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $statement->execute([$id]);
    $user = $statement->fetch();
    if (!$user || $user['role'] !== 'college' || ($_SESSION['role'] ?? null) !== 'college') {
        throw new HttpError(403, 'A college placement officer account is required.');
    }

    $membership = get_user_college_organization($pdo, (int) $user['id']);
    if (!$membership) {
        throw new HttpError(403, 'No college organization is associated with this account.');
    }
    if ($membership['org_status'] !== 'ACTIVE') {
        throw new HttpError(403, 'Your college organization is ' . strtolower($membership['org_status']) . '. Awaiting platform administrator approval.');
    }
    return [
        'user' => $user,
        'org' => $membership,
    ];
}

/**
 * List all active, verified colleges.
 */
function list_approved_colleges(\PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, name, slug FROM organizations WHERE type = 'COLLEGE' AND status = 'ACTIVE' ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * List students associated with a college, respecting student consent.
 * If consent is NOT granted (college_share_consent = 0):
 * - Personal contact info and application metrics are withheld to protect student privacy.
 */
function list_college_students(\PDO $pdo, int $collegeOrgId, int $userId): array
{
    if (!Organizations\can($pdo, $userId, 'perm.org.view', $collegeOrgId)) {
        throw new EmploymentError('unauthorized_college_action', 403);
    }

    $stmt = $pdo->prepare("
        SELECT u.id AS student_id, u.name AS student_name,
               sp.college, sp.skills, sp.github_username,
               sp.college_share_consent, sp.updated_at,
               CASE WHEN sp.college_share_consent = 1 THEN u.email ELSE '[Consent Withheld]' END AS student_email,
               CASE WHEN sp.college_share_consent = 1 THEN (SELECT count(*) FROM applications a WHERE a.student_id = u.id) ELSE 0 END AS applications_count,
               CASE WHEN sp.college_share_consent = 1 THEN (SELECT count(*) FROM applications a WHERE a.student_id = u.id AND a.status = 'shortlisted') ELSE 0 END AS shortlisted_count,
               CASE WHEN sp.college_share_consent = 1 THEN (SELECT count(*) FROM resumes r WHERE r.user_id = u.id) ELSE 0 END AS resumes_count
        FROM student_profiles sp
        JOIN users u ON u.id = sp.user_id
        WHERE sp.college_org_id = ?
        ORDER BY sp.college_share_consent DESC, u.name ASC
    ");
    $stmt->execute([$collegeOrgId]);
    return $stmt->fetchAll();
}

/**
 * Retrieve placement and application details for a specific student associated with a college.
 * Strictly enforces student consent: If college_share_consent = 0, access is DENIED (403).
 */
function get_college_student_placement_details(\PDO $pdo, int $collegeOrgId, int $studentId, int $userId): array
{
    if (!Organizations\can($pdo, $userId, 'perm.org.view', $collegeOrgId)) {
        throw new EmploymentError('unauthorized_college_action', 403);
    }

    $stmt = $pdo->prepare("
        SELECT u.id AS student_id, u.name AS student_name, u.email AS student_email,
               sp.college, sp.skills, sp.bio, sp.github_username,
               sp.college_org_id, sp.college_share_consent, sp.updated_at
        FROM student_profiles sp
        JOIN users u ON u.id = sp.user_id
        WHERE sp.user_id = ? AND sp.college_org_id = ?
    ");
    $stmt->execute([$studentId, $collegeOrgId]);
    $student = $stmt->fetch();
    if (!$student) {
        throw new EmploymentError('student_not_found', 404);
    }

    if ((int) $student['college_share_consent'] !== 1) {
        throw new EmploymentError('student_consent_required', 403);
    }

    // Consented student: fetch applications history
    $appsStmt = $pdo->prepare("
        SELECT a.id AS application_id, a.status, a.created_at,
               j.title AS job_title, j.role_category, j.location,
               o.name AS company_name
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN organizations o ON o.id = j.organization_id
        WHERE a.student_id = ?
        ORDER BY a.id DESC
    ");
    $appsStmt->execute([$studentId]);
    $applications = $appsStmt->fetchAll();

    // Fetch latest resume if available
    $resumeStmt = $pdo->prepare("
        SELECT id, title, summary, skills_json, assessment_id, updated_at
        FROM resumes
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $resumeStmt->execute([$studentId]);
    $resume = $resumeStmt->fetch() ?: null;

    return [
        'student' => $student,
        'applications' => $applications,
        'resume' => $resume,
    ];
}



/**
 * List active jobs tied to verified active companies.
 * Supports filtering by role category and search keywords.
 */
function list_active_jobs(\PDO $pdo, ?string $roleCategory = null, ?string $search = null): array
{
    $sql = "
        SELECT j.id, j.organization_id, j.title, j.description, j.skills,
               j.role_category, j.location, j.status, j.created_at, j.updated_at,
               o.name AS company_name, o.slug AS company_slug
        FROM jobs j
        JOIN organizations o ON o.id = j.organization_id
        WHERE j.status = 'active'
          AND o.status = 'ACTIVE'
          AND o.type = 'COMPANY'
    ";
    $params = [];

    if ($roleCategory !== null && $roleCategory !== '' && $roleCategory !== 'all') {
        if (!in_array($roleCategory, ['frontend', 'backend', 'fullstack', 'general'], true)) {
            throw new EmploymentError('invalid_role_category', 422);
        }
        $sql .= " AND j.role_category = ?";
        $params[] = $roleCategory;
    }

    if ($search !== null && trim($search) !== '') {
        $term = '%' . addcslashes(trim($search), '%_\\') . '%';
        $sql .= " AND (j.title LIKE ? ESCAPE '\\' OR j.skills LIKE ? ESCAPE '\\' OR o.name LIKE ? ESCAPE '\\')";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY j.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Retrieve a specific job with company details.
 */
function get_job(\PDO $pdo, int $jobId): ?array
{
    $stmt = $pdo->prepare("
        SELECT j.id, j.organization_id, j.created_by_user_id, j.title, j.description,
               j.skills, j.role_category, j.location, j.status, j.created_at, j.updated_at,
               o.name AS company_name, o.slug AS company_slug, o.status AS company_status
        FROM jobs j
        JOIN organizations o ON o.id = j.organization_id
        WHERE j.id = ?
    ");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    return is_array($job) ? $job : null;
}

/**
 * Student applies for a job.
 * Enforces:
 * - Caller is an active student
 * - Job is active and company is verified
 * - Duplicate application prevention (atomic)
 */
function apply_for_job(\PDO $pdo, int $studentId, int $jobId, string $notes = ''): int
{
    // Check student role
    $studentStmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
    $studentStmt->execute([$studentId]);
    $student = $studentStmt->fetch();
    if (!$student || $student['role'] !== 'student') {
        throw new EmploymentError('student_required', 403);
    }

    // Check job validity
    $job = get_job($pdo, $jobId);
    if (!$job || $job['status'] !== 'active' || $job['company_status'] !== 'ACTIVE') {
        throw new EmploymentError('job_not_available', 404);
    }

    // Check for duplicate application
    $dupStmt = $pdo->prepare('SELECT id FROM applications WHERE student_id = ? AND job_id = ?');
    $dupStmt->execute([$studentId, $jobId]);
    if ($dupStmt->fetch()) {
        throw new EmploymentError('duplicate_application', 409);
    }

    if (strlen($notes) > 1000) {
        throw new EmploymentError('notes_too_long', 422);
    }

    $now = time();
    $stmt = $pdo->prepare('
        INSERT INTO applications (student_id, job_id, status, notes, created_at, updated_at)
        VALUES (?, ?, \'applied\', ?, ?, ?)
    ');
    try {
        $stmt->execute([$studentId, $jobId, $notes, $now, $now]);
        return (int) $pdo->lastInsertId();
    } catch (\PDOException $e) {
        // SQLite constraint error code 19
        if (($e->errorInfo[1] ?? null) === 19) {
            throw new EmploymentError('duplicate_application', 409);
        }
        throw $e;
    }
}

/**
 * List applications submitted by a specific student.
 */
function list_student_applications(\PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare("
        SELECT a.id, a.status, a.notes, a.created_at, a.updated_at,
               j.id AS job_id, j.title AS job_title, j.role_category, j.location,
               o.name AS company_name, o.slug AS company_slug
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN organizations o ON o.id = j.organization_id
        WHERE a.student_id = ?
        ORDER BY a.id DESC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

/**
 * Retrieve a specific student's application for a specific job (if exists).
 */
function get_student_application_for_job(\PDO $pdo, int $studentId, int $jobId): ?array
{
    $stmt = $pdo->prepare('
        SELECT id, status, notes, created_at, updated_at
        FROM applications
        WHERE student_id = ? AND job_id = ?
    ');
    $stmt->execute([$studentId, $jobId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * List applications for a company with strict multi-tenant isolation.
 */
function list_company_applications(\PDO $pdo, int $userId, int $organizationId, ?int $jobId = null): array
{
    if (!Organizations\can($pdo, $userId, 'perm.org.view', $organizationId)) {
        throw new EmploymentError('unauthorized_company_action', 403);
    }

    $sql = "
        SELECT a.id AS application_id, a.status, a.notes, a.created_at, a.updated_at,
               u.id AS student_id, u.name AS student_name, u.email AS student_email,
               sp.college, sp.skills AS student_skills, sp.github_username,
               j.id AS job_id, j.title AS job_title, j.role_category
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN users u ON u.id = a.student_id
        LEFT JOIN student_profiles sp ON sp.user_id = u.id
        WHERE j.organization_id = ?
    ";
    $params = [$organizationId];

    if ($jobId !== null) {
        $sql .= " AND j.id = ?";
        $params[] = $jobId;
    }

    $sql .= " ORDER BY a.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Update application status by an authorized company staff member.
 * Enforces ownership: the application must belong to a job posted by the caller's organization.
 */
function update_application_status(
    \PDO $pdo,
    int $userId,
    int $organizationId,
    int $applicationId,
    string $newStatus
): void {
    if (!Organizations\can($pdo, $userId, 'perm.org.mutate', $organizationId)) {
        throw new EmploymentError('unauthorized_company_action', 403);
    }

    $validStatuses = ['applied', 'shortlisted', 'rejected'];
    if (!in_array($newStatus, $validStatuses, true)) {
        throw new EmploymentError('invalid_application_status', 422);
    }

    // Verify application belongs to a job belonging to this organization
    $checkStmt = $pdo->prepare('
        SELECT a.id, a.status, j.organization_id
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        WHERE a.id = ? AND j.organization_id = ?
    ');
    $checkStmt->execute([$applicationId, $organizationId]);
    $app = $checkStmt->fetch();
    if (!$app) {
        throw new EmploymentError('application_not_found', 404);
    }

    $now = time();
    $updateStmt = $pdo->prepare('
        UPDATE applications
        SET status = ?, updated_at = ?
        WHERE id = ?
    ');
    $updateStmt->execute([$newStatus, $now, $applicationId]);

    Organizations\record_audit($pdo, $userId, 'application.status_update', 'application', $applicationId, [
        'organization_id' => $organizationId,
        'old_status' => $app['status'],
        'new_status' => $newStatus,
    ]);
}

/**
 * Normalize resume form / array input into clean structured data.
 */
function normalize_resume_input(array $input): array
{
    $data = [
        'id' => !empty($input['id']) ? (int) $input['id'] : null,
        'title' => trim((string) ($input['title'] ?? 'Software Engineering Resume')),
        'summary' => trim((string) ($input['summary'] ?? '')),
        'assessment_id' => !empty($input['assessment_id']) ? (int) $input['assessment_id'] : null,
        'education' => [],
        'experience' => [],
        'projects' => [],
        'skills' => [],
    ];

    // Skills
    if (isset($input['skills'])) {
        if (is_array($input['skills'])) {
            $data['skills'] = array_values(array_filter(array_map('trim', $input['skills'])));
        } else {
            $rawSkills = (string) $input['skills'];
            $items = preg_split('/[,\n\r]+/', $rawSkills);
            $data['skills'] = array_values(array_filter(array_map('trim', (array) $items)));
        }
    }

    // Education
    if (isset($input['education']) && is_array($input['education'])) {
        $data['education'] = $input['education'];
    } elseif (isset($input['education_institution']) && is_array($input['education_institution'])) {
        $institutions = $input['education_institution'];
        $degrees = (array) ($input['education_degree'] ?? []);
        $years = (array) ($input['education_year'] ?? []);
        $gpas = (array) ($input['education_gpa'] ?? []);
        foreach ($institutions as $i => $inst) {
            $inst = trim((string) $inst);
            if ($inst !== '') {
                $data['education'][] = [
                    'institution' => substr($inst, 0, 150),
                    'degree' => substr(trim((string) ($degrees[$i] ?? '')), 0, 150),
                    'year' => substr(trim((string) ($years[$i] ?? '')), 0, 50),
                    'gpa' => substr(trim((string) ($gpas[$i] ?? '')), 0, 20),
                ];
            }
        }
    }

    // Experience
    if (isset($input['experience']) && is_array($input['experience'])) {
        $data['experience'] = $input['experience'];
    } elseif (isset($input['experience_company']) && is_array($input['experience_company'])) {
        $companies = $input['experience_company'];
        $roles = (array) ($input['experience_role'] ?? []);
        $starts = (array) ($input['experience_start'] ?? []);
        $ends = (array) ($input['experience_end'] ?? []);
        $descs = (array) ($input['experience_desc'] ?? []);
        foreach ($companies as $i => $comp) {
            $comp = trim((string) $comp);
            if ($comp !== '') {
                $data['experience'][] = [
                    'company' => substr($comp, 0, 150),
                    'role' => substr(trim((string) ($roles[$i] ?? '')), 0, 150),
                    'start_date' => substr(trim((string) ($starts[$i] ?? '')), 0, 50),
                    'end_date' => substr(trim((string) ($ends[$i] ?? '')), 0, 50),
                    'description' => substr(trim((string) ($descs[$i] ?? '')), 0, 1000),
                ];
            }
        }
    }

    // Projects
    if (isset($input['projects']) && is_array($input['projects'])) {
        $data['projects'] = $input['projects'];
    } elseif (isset($input['project_name']) && is_array($input['project_name'])) {
        $names = $input['project_name'];
        $techs = (array) ($input['project_tech'] ?? []);
        $descs = (array) ($input['project_desc'] ?? []);
        $links = (array) ($input['project_link'] ?? []);
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $data['projects'][] = [
                    'name' => substr($name, 0, 150),
                    'technologies' => substr(trim((string) ($techs[$i] ?? '')), 0, 200),
                    'description' => substr(trim((string) ($descs[$i] ?? '')), 0, 1000),
                    'link' => substr(trim((string) ($links[$i] ?? '')), 0, 200),
                ];
            }
        }
    }

    return $data;
}

/**
 * Resume persistence and retrieval (Complete ATS Resume Builder).
 */
function save_resume(\PDO $pdo, int $userId, array $data): int
{
    // Verify user exists and is a student
    $userStmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if (!$user || $user['role'] !== 'student') {
        throw new EmploymentError('student_required', 403);
    }

    $title = trim((string) ($data['title'] ?? 'Software Engineering Resume'));
    if (strlen($title) < 1 || strlen($title) > 100) {
        throw new EmploymentError('invalid_resume_title', 422);
    }

    $summary = trim((string) ($data['summary'] ?? ''));
    if (strlen($summary) > 2000) {
        throw new EmploymentError('resume_summary_too_long', 422);
    }

    $educationJson = json_encode($data['education'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    if (strlen($educationJson) > 8192) {
        throw new EmploymentError('education_too_large', 422);
    }

    $experienceJson = json_encode($data['experience'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    if (strlen($experienceJson) > 8192) {
        throw new EmploymentError('experience_too_large', 422);
    }

    $projectsJson = json_encode($data['projects'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    if (strlen($projectsJson) > 8192) {
        throw new EmploymentError('projects_too_large', 422);
    }

    $skillsJson = json_encode($data['skills'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    if (strlen($skillsJson) > 4096) {
        throw new EmploymentError('skills_too_large', 422);
    }

    $assessmentId = null;
    if (!empty($data['assessment_id'])) {
        $aId = (int) $data['assessment_id'];
        $aStmt = $pdo->prepare('SELECT id FROM assessments WHERE id = ? AND user_id = ? AND state = \'completed\'');
        $aStmt->execute([$aId, $userId]);
        if (!$aStmt->fetch()) {
            throw new EmploymentError('invalid_assessment_citation', 422);
        }
        $assessmentId = $aId;
    }

    $now = time();

    if (!empty($data['id'])) {
        $resumeId = (int) $data['id'];
        // Strict ownership check (IDOR prevention)
        $check = $pdo->prepare('SELECT id FROM resumes WHERE id = ? AND user_id = ?');
        $check->execute([$resumeId, $userId]);
        if (!$check->fetch()) {
            throw new EmploymentError('resume_not_found', 404);
        }

        $stmt = $pdo->prepare('
            UPDATE resumes
            SET title = ?, summary = ?, education_json = ?, experience_json = ?, projects_json = ?, skills_json = ?, assessment_id = ?, updated_at = ?
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$title, $summary, $educationJson, $experienceJson, $projectsJson, $skillsJson, $assessmentId, $now, $resumeId, $userId]);
        return $resumeId;
    }

    $stmt = $pdo->prepare('
        INSERT INTO resumes (user_id, title, summary, education_json, experience_json, projects_json, skills_json, assessment_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $title, $summary, $educationJson, $experienceJson, $projectsJson, $skillsJson, $assessmentId, $now, $now]);
    return (int) $pdo->lastInsertId();
}

function get_resume(\PDO $pdo, int $resumeId, int $userId): ?array
{
    $stmt = $pdo->prepare('
        SELECT r.*,
               u.name AS user_name, u.email AS user_email,
               sp.college, sp.bio AS profile_bio, sp.github_username,
               a.repository AS assessment_repo, a.target_role AS assessment_role,
               a.commit_sha AS assessment_sha, a.rubric_version AS assessment_rubric
        FROM resumes r
        JOIN users u ON u.id = r.user_id
        LEFT JOIN student_profiles sp ON sp.user_id = r.user_id
        LEFT JOIN assessments a ON a.id = r.assessment_id AND a.user_id = r.user_id AND a.state = \'completed\'
        WHERE r.id = ? AND r.user_id = ?
    ');
    $stmt->execute([$resumeId, $userId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $row['assessment_tasks'] = [];
    if (!empty($row['assessment_id'])) {
        $taskStmt = $pdo->prepare('
            SELECT priority, finding_id, title, acceptance
            FROM improvement_tasks
            WHERE assessment_id = ?
            ORDER BY priority ASC
        ');
        $taskStmt->execute([$row['assessment_id']]);
        $row['assessment_tasks'] = $taskStmt->fetchAll();
    }

    $row['education'] = json_decode($row['education_json'] ?? '[]', true) ?: [];
    $row['experience'] = json_decode($row['experience_json'] ?? '[]', true) ?: [];
    $row['projects'] = json_decode($row['projects_json'] ?? '[]', true) ?: [];
    $row['skills'] = json_decode($row['skills_json'] ?? '[]', true) ?: [];

    return $row;
}

function list_resumes(\PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, title, assessment_id, created_at, updated_at FROM resumes WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function delete_resume(\PDO $pdo, int $resumeId, int $userId): void
{
    $stmt = $pdo->prepare('DELETE FROM resumes WHERE id = ? AND user_id = ?');
    $stmt->execute([$resumeId, $userId]);
    if ($stmt->rowCount() !== 1) {
        throw new EmploymentError('resume_not_found', 404);
    }
}
