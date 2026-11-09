<?php
declare(strict_types=1);

namespace Pathway\Employment;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../organizations/organization.php';

use Pathway\HttpError;
use Pathway\Organizations;

/**
 * Submit an institutional college affiliation request.
 * Enforces:
 * - Caller is an active student
 * - Target college is an approved, active COLLEGE organization
 * - Proper enrollment verification data (enrollment_number, degree, department, graduation_year)
 * - Duplicate pending prevention
 */
function submit_affiliation_request(\PDO $pdo, int $studentId, int $collegeOrgId, array $data): int
{
    $studentStmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
    $studentStmt->execute([$studentId]);
    $student = $studentStmt->fetch();
    if (!$student || $student['role'] !== 'student') {
        throw new EmploymentError('student_required', 403);
    }

    $collegeStmt = $pdo->prepare("SELECT id, name, type, status FROM organizations WHERE id = ? AND type = 'COLLEGE'");
    $collegeStmt->execute([$collegeOrgId]);
    $college = $collegeStmt->fetch();
    if (!$college || $college['status'] !== 'ACTIVE') {
        throw new EmploymentError('active_college_required', 404);
    }

    $enrollmentNo = trim((string) ($data['enrollment_number'] ?? ''));
    if (strlen($enrollmentNo) < 2 || strlen($enrollmentNo) > 50) {
        throw new EmploymentError('invalid_enrollment_number', 422);
    }

    $degree = trim((string) ($data['degree'] ?? ''));
    if (strlen($degree) < 2 || strlen($degree) > 100) {
        throw new EmploymentError('invalid_degree', 422);
    }

    $department = trim((string) ($data['department'] ?? ''));
    if (strlen($department) < 2 || strlen($department) > 100) {
        throw new EmploymentError('invalid_department', 422);
    }

    $gradYear = (int) ($data['graduation_year'] ?? 0);
    if ($gradYear < 2000 || $gradYear > 2100) {
        throw new EmploymentError('invalid_graduation_year', 422);
    }

    $note = trim((string) ($data['verification_note'] ?? ''));
    if (strlen($note) > 1000) {
        throw new EmploymentError('verification_note_too_long', 422);
    }

    // Check for existing pending request to this college
    $checkStmt = $pdo->prepare("
        SELECT id FROM college_affiliation_requests
        WHERE student_id = ? AND college_org_id = ? AND status = 'PENDING'
    ");
    $checkStmt->execute([$studentId, $collegeOrgId]);
    if ($checkStmt->fetch()) {
        throw new EmploymentError('pending_affiliation_exists', 409);
    }

    $now = time();
    $stmt = $pdo->prepare("
        INSERT INTO college_affiliation_requests (
            student_id, college_org_id, enrollment_number, degree, department,
            graduation_year, verification_note, status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?)
    ");
    $stmt->execute([
        $studentId,
        $collegeOrgId,
        $enrollmentNo,
        $degree,
        $department,
        $gradYear,
        $note,
        $now,
        $now
    ]);
    $requestId = (int) $pdo->lastInsertId();

    // Update student profile affiliation status to PENDING
    $profileStmt = $pdo->prepare("UPDATE student_profiles SET affiliation_status = 'PENDING', updated_at = ? WHERE user_id = ?");
    $profileStmt->execute([$now, $studentId]);

    Organizations\record_audit($pdo, $studentId, 'affiliation.request', 'affiliation_request', $requestId, [
        'college_org_id' => $collegeOrgId,
        'enrollment_number' => $enrollmentNo,
        'degree' => $degree,
        'department' => $department,
        'graduation_year' => $gradYear,
    ]);

    return $requestId;
}

/**
 * Retrieve an affiliation request by ID with optional scoping to prevent IDOR.
 */
function get_affiliation_request(\PDO $pdo, int $requestId, ?int $scopedStudentId = null, ?int $scopedCollegeOrgId = null): ?array
{
    $sql = "
        SELECT r.*,
               u.name AS student_name, u.email AS student_email,
               o.name AS college_name, o.slug AS college_slug,
               reviewer.name AS reviewer_name
        FROM college_affiliation_requests r
        JOIN users u ON u.id = r.student_id
        JOIN organizations o ON o.id = r.college_org_id
        LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by_user_id
        WHERE r.id = ?
    ";
    $params = [$requestId];

    if ($scopedStudentId !== null) {
        $sql .= " AND r.student_id = ?";
        $params[] = $scopedStudentId;
    }
    if ($scopedCollegeOrgId !== null) {
        $sql .= " AND r.college_org_id = ?";
        $params[] = $scopedCollegeOrgId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * List all affiliation requests submitted by a specific student.
 */
function list_student_affiliation_requests(\PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare("
        SELECT r.*, o.name AS college_name, o.slug AS college_slug
        FROM college_affiliation_requests r
        JOIN organizations o ON o.id = r.college_org_id
        WHERE r.student_id = ?
        ORDER BY r.id DESC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

/**
 * Student cancels their own pending affiliation request.
 */
function cancel_affiliation_request(\PDO $pdo, int $studentId, int $requestId): void
{
    $req = get_affiliation_request($pdo, $requestId, $studentId);
    if (!$req) {
        throw new EmploymentError('affiliation_request_not_found', 404);
    }
    if ($req['status'] !== 'PENDING') {
        throw new EmploymentError('cannot_cancel_non_pending_request', 400);
    }

    $now = time();
    $stmt = $pdo->prepare("UPDATE college_affiliation_requests SET status = 'CANCELLED', updated_at = ? WHERE id = ?");
    $stmt->execute([$now, $requestId]);

    // Check if student has any other pending or active affiliations
    $checkStmt = $pdo->prepare("
        SELECT count(*) FROM college_affiliation_requests
        WHERE student_id = ? AND status IN ('PENDING', 'APPROVED')
    ");
    $checkStmt->execute([$studentId]);
    if ((int) $checkStmt->fetchColumn() === 0) {
        $pdo->prepare("UPDATE student_profiles SET affiliation_status = 'NONE', updated_at = ? WHERE user_id = ?")
            ->execute([$now, $studentId]);
    }

    Organizations\record_audit($pdo, $studentId, 'affiliation.cancel', 'affiliation_request', $requestId, [
        'college_org_id' => $req['college_org_id'],
    ]);
}

/**
 * Placement officers list affiliation requests for their college.
 */
function list_college_affiliation_requests(\PDO $pdo, int $collegeOrgId, int $officerUserId, ?string $status = null): array
{
    if (!Organizations\can($pdo, $officerUserId, 'perm.org.view', $collegeOrgId)) {
        throw new EmploymentError('unauthorized_college_action', 403);
    }

    $sql = "
        SELECT r.*,
               u.name AS student_name, u.email AS student_email,
               reviewer.name AS reviewer_name
        FROM college_affiliation_requests r
        JOIN users u ON u.id = r.student_id
        LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by_user_id
        WHERE r.college_org_id = ?
    ";
    $params = [$collegeOrgId];

    if ($status !== null) {
        $sql .= " AND r.status = ?";
        $params[] = strtoupper($status);
    }

    $sql .= " ORDER BY CASE WHEN r.status = 'PENDING' THEN 0 ELSE 1 END, r.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Placement officer reviews (approves or rejects) an affiliation request.
 * Enforces:
 * - Proper college staff permissions
 * - Auditable decision and reason
 * - On approval: updates profile affiliation without automatically exposing private student data
 */
function review_affiliation_request(\PDO $pdo, int $collegeOrgId, int $officerUserId, int $requestId, string $decision, string $reason): void
{
    if (!Organizations\can($pdo, $officerUserId, 'perm.org.mutate', $collegeOrgId)) {
        throw new EmploymentError('unauthorized_college_action', 403);
    }

    $decisionUpper = strtoupper(trim($decision));
    if (!in_array($decisionUpper, ['APPROVED', 'REJECTED'], true)) {
        throw new EmploymentError('invalid_review_decision', 422);
    }

    $trimmedReason = trim($reason);
    if (strlen($trimmedReason) < 3 || strlen($trimmedReason) > 1000) {
        throw new EmploymentError('decision_reason_required', 422);
    }

    $req = get_affiliation_request($pdo, $requestId, null, $collegeOrgId);
    if (!$req) {
        throw new EmploymentError('affiliation_request_not_found', 404);
    }
    if ($req['status'] !== 'PENDING') {
        throw new EmploymentError('affiliation_already_reviewed', 409);
    }

    $now = time();
    $studentId = (int) $req['student_id'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE college_affiliation_requests
            SET status = ?, reviewed_by_user_id = ?, review_decision_reason = ?, reviewed_at = ?, updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$decisionUpper, $officerUserId, $trimmedReason, $now, $now, $requestId]);

        if ($decisionUpper === 'APPROVED') {
            // Associate student with college while strictly maintaining their chosen privacy/consent state
            $profStmt = $pdo->prepare("
                UPDATE student_profiles
                SET college_org_id = ?,
                    enrollment_number = ?,
                    degree = ?,
                    department = ?,
                    graduation_year = ?,
                    affiliation_status = 'AFFILIATED',
                    updated_at = ?
                WHERE user_id = ?
            ");
            $profStmt->execute([
                $collegeOrgId,
                $req['enrollment_number'],
                $req['degree'],
                $req['department'],
                $req['graduation_year'],
                $now,
                $studentId
            ]);
        } else {
            // Rejected
            $checkStmt = $pdo->prepare("
                SELECT count(*) FROM college_affiliation_requests
                WHERE student_id = ? AND status = 'APPROVED'
            ");
            $checkStmt->execute([$studentId]);
            if ((int) $checkStmt->fetchColumn() === 0) {
                $pdo->prepare("UPDATE student_profiles SET affiliation_status = 'NONE', updated_at = ? WHERE user_id = ?")
                    ->execute([$now, $studentId]);
            }
        }

        Organizations\record_audit($pdo, $officerUserId, 'affiliation.review', 'affiliation_request', $requestId, [
            'decision' => $decisionUpper,
            'reason' => $trimmedReason,
            'student_id' => $studentId,
            'college_org_id' => $collegeOrgId,
        ]);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Placement officer revokes an existing approved affiliation.
 * Immediate cutoff of institutional access.
 */
function revoke_affiliation(\PDO $pdo, int $collegeOrgId, int $officerUserId, int $studentId, string $reason): void
{
    if (!Organizations\can($pdo, $officerUserId, 'perm.org.mutate', $collegeOrgId)) {
        throw new EmploymentError('unauthorized_college_action', 403);
    }

    $trimmedReason = trim($reason);
    if (strlen($trimmedReason) < 3 || strlen($trimmedReason) > 1000) {
        throw new EmploymentError('revocation_reason_required', 422);
    }

    $profile = get_profile($pdo, $studentId);
    if (!$profile || (int) ($profile['college_org_id'] ?? 0) !== $collegeOrgId) {
        throw new EmploymentError('student_not_affiliated_with_college', 404);
    }

    $now = time();
    $pdo->beginTransaction();
    try {
        // Mark latest approved request as REVOKED
        $stmt = $pdo->prepare("
            UPDATE college_affiliation_requests
            SET status = 'REVOKED', review_decision_reason = ?, reviewed_by_user_id = ?, updated_at = ?
            WHERE student_id = ? AND college_org_id = ? AND status = 'APPROVED'
        ");
        $stmt->execute([$trimmedReason, $officerUserId, $now, $studentId, $collegeOrgId]);

        // Sever the college affiliation in student_profile immediately
        $profStmt = $pdo->prepare("
            UPDATE student_profiles
            SET college_org_id = NULL,
                affiliation_status = 'REVOKED',
                updated_at = ?
            WHERE user_id = ?
        ");
        $profStmt->execute([$now, $studentId]);

        Organizations\record_audit($pdo, $officerUserId, 'affiliation.revoke', 'student_profile', $studentId, [
            'reason' => $trimmedReason,
            'college_org_id' => $collegeOrgId,
        ]);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Record an access event into the immutable sensitive record access audit log.
 */
function record_sensitive_access(
    \PDO $pdo,
    int $accessorUserId,
    string $role,
    ?int $orgId,
    ?int $studentId,
    string $recordType,
    ?int $recordId,
    string $context,
    bool $granted
): void {
    $now = time();
    $stmt = $pdo->prepare("
        INSERT INTO sensitive_record_access_logs (
            accessor_user_id, accessor_role, accessor_org_id, student_id,
            record_type, record_id, access_context, granted, accessed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $accessorUserId,
        $role,
        $orgId,
        $studentId,
        $recordType,
        $recordId,
        $context,
        $granted ? 1 : 0,
        $now
    ]);
}

/**
 * List access logs for a student so they have full visibility into who inspected their records.
 */
function list_student_access_logs(\PDO $pdo, int $studentId, int $limit = 50): array
{
    $stmt = $pdo->prepare("
        SELECT l.*,
               accessor.name AS accessor_name,
               o.name AS organization_name, o.type AS organization_type
        FROM sensitive_record_access_logs l
        JOIN users accessor ON accessor.id = l.accessor_user_id
        LEFT JOIN organizations o ON o.id = l.accessor_org_id
        WHERE l.student_id = ?
        ORDER BY l.accessed_at DESC, l.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $studentId, \PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Structured Student Achievements & Credentials.
 */
function create_achievement(\PDO $pdo, int $studentId, array $data): int
{
    $userStmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
    $userStmt->execute([$studentId]);
    $user = $userStmt->fetch();
    if (!$user || $user['role'] !== 'student') {
        throw new EmploymentError('student_required', 403);
    }

    $category = strtolower(trim((string) ($data['category'] ?? 'project')));
    if (!in_array($category, ['certification', 'hackathon', 'publication', 'award', 'project', 'academic'], true)) {
        throw new EmploymentError('invalid_achievement_category', 422);
    }

    $title = trim((string) ($data['title'] ?? ''));
    if (strlen($title) < 2 || strlen($title) > 150) {
        throw new EmploymentError('invalid_achievement_title', 422);
    }

    $issuer = trim((string) ($data['issuer'] ?? ''));
    if (strlen($issuer) > 150) {
        throw new EmploymentError('issuer_too_long', 422);
    }

    $issueDate = trim((string) ($data['issue_date'] ?? ''));
    if (strlen($issueDate) > 30) {
        throw new EmploymentError('issue_date_too_long', 422);
    }

    $credUrl = trim((string) ($data['credential_url'] ?? ''));
    if ($credUrl !== '' && (strlen($credUrl) > 500 || !filter_var($credUrl, FILTER_VALIDATE_URL))) {
        throw new EmploymentError('invalid_credential_url', 422);
    }

    $desc = trim((string) ($data['description'] ?? ''));
    if (strlen($desc) > 1000) {
        throw new EmploymentError('description_too_long', 422);
    }

    $assessmentId = null;
    if (!empty($data['evidence_assessment_id'])) {
        $aId = (int) $data['evidence_assessment_id'];
        $aStmt = $pdo->prepare("SELECT id FROM assessments WHERE id = ? AND user_id = ? AND state = 'completed'");
        $aStmt->execute([$aId, $studentId]);
        if (!$aStmt->fetch()) {
            throw new EmploymentError('invalid_evidence_assessment', 422);
        }
        $assessmentId = $aId;
    }

    $now = time();
    $stmt = $pdo->prepare("
        INSERT INTO student_achievements (
            user_id, category, title, issuer, issue_date, credential_url,
            description, evidence_assessment_id, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $studentId,
        $category,
        $title,
        $issuer,
        $issueDate,
        $credUrl,
        $desc,
        $assessmentId,
        $now,
        $now
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Delete an achievement strictly bounded by owner user ID (IDOR protection).
 */
function delete_achievement(\PDO $pdo, int $studentId, int $achievementId): void
{
    $stmt = $pdo->prepare("DELETE FROM student_achievements WHERE id = ? AND user_id = ?");
    $stmt->execute([$achievementId, $studentId]);
    if ($stmt->rowCount() !== 1) {
        throw new EmploymentError('achievement_not_found', 404);
    }
}

/**
 * List achievements for a specific student.
 */
function list_student_achievements(\PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare("
        SELECT a.*,
               ev.repository AS assessment_repo, ev.commit_sha AS assessment_sha
        FROM student_achievements a
        LEFT JOIN assessments ev ON ev.id = a.evidence_assessment_id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

/**
 * Optional Talent Discovery: allows verified employers to search only for students who
 * have explicitly enabled `talent_discovery_opt_in = 1`.
 * Never returns students who have not opted in.
 */
function list_opted_in_talent(\PDO $pdo, int $recruiterUserId, int $companyOrgId, ?string $skillsQuery = null): array
{
    if (!Organizations\can($pdo, $recruiterUserId, 'perm.org.view', $companyOrgId)) {
        throw new EmploymentError('unauthorized_company_action', 403);
    }

    $sql = "
        SELECT u.id AS student_id, u.name AS student_name,
               sp.college, sp.skills, sp.bio, sp.github_username,
               sp.degree, sp.department, sp.graduation_year,
               (SELECT count(*) FROM student_achievements ach WHERE ach.user_id = u.id) AS achievements_count,
               (SELECT count(*) FROM resumes r WHERE r.user_id = u.id) AS resumes_count
        FROM student_profiles sp
        JOIN users u ON u.id = sp.user_id
        WHERE sp.talent_discovery_opt_in = 1
    ";
    $params = [];

    if ($skillsQuery !== null && trim($skillsQuery) !== '') {
        $term = '%' . addcslashes(trim($skillsQuery), '%_\\') . '%';
        $sql .= " AND (sp.skills LIKE ? ESCAPE '\\' OR sp.degree LIKE ? ESCAPE '\\')";
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY sp.updated_at DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    record_sensitive_access(
        $pdo,
        $recruiterUserId,
        'company',
        $companyOrgId,
        null, // Aggregated query
        'profile',
        null,
        'talent_discovery_query',
        true
    );

    return $results;
}
