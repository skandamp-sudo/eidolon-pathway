<?php
declare(strict_types=1);

namespace Pathway;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/evidence/agent.php';
require_once __DIR__ . '/employment/jobs.php';
require_once __DIR__ . '/organizations/organization.php';
require_once __DIR__ . '/auth/oauth.php';
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');

$screen = 'error';
$message = '';
$user = null;
$flash = null;
try {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $port = (string) ($_SERVER['SERVER_PORT'] ?? '');
    if (!in_array($host, ['127.0.0.1:' . $port, 'localhost:' . $port], true) ||
        !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        throw new HttpError(421, 'Open this application through its localhost address.');
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strlen($uri) > 2048) {
        throw new HttpError(414, 'Request address is too long.');
    }
    $path = parse_url($uri, PHP_URL_PATH);
    $routes = [
        '/' => ['GET'],
        '/login' => ['GET', 'POST'],
        '/register' => ['GET', 'POST'],
        '/dashboard' => ['GET'],
        '/assess' => ['GET', 'POST'],
        '/profile' => ['GET', 'POST'],
        '/jobs' => ['GET'],
        '/jobs/view' => ['GET'],
        '/jobs/apply' => ['POST'],
        '/applications' => ['GET'],
        '/resume' => ['GET', 'POST'],
        '/resume/preview' => ['GET'],
        '/company/login' => ['GET', 'POST'],
        '/company/dashboard' => ['GET'],
        '/company/jobs/new' => ['GET', 'POST'],
        '/company/jobs/edit' => ['GET', 'POST'],
        '/company/applicants' => ['GET'],
        '/company/applicants/status' => ['POST'],
        '/company/talent' => ['GET'],
        '/company/candidate' => ['GET'],
        '/college/login' => ['GET', 'POST'],
        '/college/dashboard' => ['GET'],
        '/college/students/view' => ['GET'],
        '/college/affiliations' => ['GET'],
        '/college/affiliations/review' => ['POST'],
        '/college/affiliations/revoke' => ['POST'],
        '/affiliation/request' => ['GET', 'POST'],
        '/affiliation/cancel' => ['POST'],
        '/privacy/settings' => ['GET', 'POST'],
        '/privacy/access-log' => ['GET'],
        '/achievements' => ['GET', 'POST'],
        '/achievements/delete' => ['POST'],
        '/admin/login' => ['GET', 'POST'],
        '/admin' => ['GET'],
        '/admin/dashboard' => ['GET'],
        '/admin/organizations/approve' => ['POST'],
        '/admin/organizations/suspend' => ['POST'],
        '/admin/requests/review' => ['POST'],
        '/admin/invitations/revoke' => ['POST'],
        '/admin/invitations/create' => ['POST'],
        '/request-access' => ['GET', 'POST'],
        '/auth/login/google' => ['GET'],
        '/auth/login/github' => ['GET'],
        '/auth/login/linkedin' => ['GET'],
        '/auth/callback/google' => ['GET'],
        '/auth/callback/github' => ['GET'],
        '/auth/callback/linkedin' => ['GET'],
        '/auth/link' => ['POST'],
        '/auth/unlink' => ['POST'],
        '/logout' => ['POST'],
        '/assets/pathway.css' => ['GET'],
        '/assets/theme.js' => ['GET'],
    ];
    if (!is_string($path) || !isset($routes[$path])) {
        throw new HttpError(404, 'This route is not available in the local student slice.');
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, $routes[$path], true)) {
        header('Allow: ' . implode(', ', $routes[$path]));
        throw new HttpError(405, 'Method not allowed.');
    }
    if ($path === '/assets/pathway.css') {
        header('Content-Type: text/css; charset=UTF-8');
        readfile(ROOT . '/public/assets/pathway.css');
        exit;
    }
    if ($path === '/assets/theme.js') {
        header('Content-Type: application/javascript; charset=UTF-8');
        readfile(ROOT . '/public/assets/theme.js');
        exit;
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
        throw new HttpError(413, 'The submitted form is too large.');
    }
    if ($method === 'POST' && strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded') {
        throw new HttpError(415, 'Submit the HTML form to continue.');
    }
    $pdo = database();
    start_session();
    if ($path === '/') {
        $target = '/login';
        if (isset($_SESSION['user_id'])) {
            $role = $_SESSION['role'] ?? 'student';
            $target = match ($role) {
                'company' => '/company/dashboard',
                'college' => '/college/dashboard',
                'admin' => '/admin/dashboard',
                default => '/dashboard',
            };
        }
        header('Location: ' . $target, true, 303);
        exit;
    }
    if ($method === 'POST') {
        verify_csrf($_POST);
        if ($path === '/logout') {
            $id = $_SESSION['user_id'] ?? null;
            if (!is_int($id) || $id < 1) {
                throw new HttpError(401, 'Sign in to log out.');
            }
            $_SESSION = [];
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Strict']);
            session_destroy();
            header('Location: /login', true, 303);
            exit;
        }
        throttle($pdo, $path);
        if ($path === '/assess') {
            $user = current_student($pdo);
            $repo = field($_POST, 'repository', 180);
            $role = field($_POST, 'target_role', 20);
            $assessmentId = null;
            try {
                $assessmentId = Evidence\queue($pdo, (int) $user['id'], $repo, $role);
                Evidence\run($pdo, $assessmentId);
                $_SESSION['flash'] = 'Assessment completed. View your results below.';
            } catch (Evidence\EvidenceError $error) {
                $friendly = match ($error->reason) {
                    'invalid_repository' => 'Enter a valid public GitHub repository (owner/name or full URL).',
                    'invalid_role' => 'Choose frontend, backend or fullstack.',
                    'assessment_already_active' => 'You already have an assessment in progress. Wait for it to complete.',
                    'public_repository_unavailable' => 'That repository was not found or is not public.',
                    'empty_repository' => 'That repository has no commits.',
                    'rate_limited' => 'GitHub rate limit reached. Please wait a few minutes and try again.',
                    'network_unavailable' => 'Could not reach GitHub. Check your connection and try again.',
                    'request_budget_exceeded' => 'Assessment budget exhausted. Try again later.',
                    'deadline_exceeded' => 'The assessment timed out. Try again with a smaller repository.',
                    'tree_limit_exceeded' => 'That repository has too many files to assess.',
                    'blob_integrity_mismatch', 'unsupported_file_encoding' => 'Repository contents could not be verified safely.',
                    default => 'Assessment could not be completed. Please check the repository and try again.',
                };
                $_SESSION['flash_error'] = $friendly;
            } catch (\Throwable $error) {
                error_log('pathway.assessment_failed: ' . get_class($error));
                if ($assessmentId !== null) {
                    Evidence\fail($pdo, $assessmentId, 'internal_error');
                }
                $_SESSION['flash_error'] = 'Assessment could not be completed due to an internal error. Please try again later.';
            }
            header('Location: /dashboard', true, 303);
            exit;
        }
        if ($path === '/resume') {
            $user = current_student($pdo);
            $normalized = Employment\normalize_resume_input($_POST);
            try {
                $resumeId = Employment\save_resume($pdo, (int) $user['id'], $normalized);
                $_SESSION['flash'] = 'Resume saved successfully.';
                header('Location: /resume?id=' . $resumeId, true, 303);
                exit;
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'invalid_resume_title' => 'Resume title must be between 1 and 100 characters.',
                    'resume_summary_too_long' => 'Summary cannot exceed 2000 characters.',
                    'education_too_large' => 'Education entries payload exceeds storage limit.',
                    'experience_too_large' => 'Experience entries payload exceeds storage limit.',
                    'projects_too_large' => 'Projects entries payload exceeds storage limit.',
                    'skills_too_large' => 'Skills payload exceeds storage limit.',
                    'invalid_assessment_citation' => 'Selected assessment is invalid or not owned by you.',
                    'resume_not_found' => 'The resume draft could not be found.',
                    default => 'Resume draft could not be saved. Check input fields.',
                };
                $redirectTarget = !empty($normalized['id']) ? '/resume?id=' . $normalized['id'] : '/resume';
                header('Location: ' . $redirectTarget, true, 303);
                exit;
            }
        }
        if ($path === '/profile') {
            $user = current_student($pdo);
            try {
                Employment\save_profile($pdo, (int) $user['id'], $_POST);
                $_SESSION['flash'] = 'Profile updated successfully.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'college_too_long' => 'College name cannot exceed 200 characters.',
                    'skills_too_long' => 'Skills cannot exceed 500 characters.',
                    'bio_too_long' => 'Bio cannot exceed 2000 characters.',
                    'invalid_github_username' => 'Invalid GitHub username format.',
                    default => 'Profile could not be saved. Check input fields.',
                };
            }
            header('Location: /profile', true, 303);
            exit;
        }
        if ($path === '/jobs/apply') {
            $user = current_student($pdo);
            $jobId = (int) ($_POST['job_id'] ?? 0);
            $notes = trim((string) ($_POST['notes'] ?? ''));
            try {
                Employment\apply_for_job($pdo, (int) $user['id'], $jobId, $notes);
                $_SESSION['flash'] = 'Application submitted successfully! Track your status below.';
                header('Location: /applications', true, 303);
                exit;
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'duplicate_application' => 'You have already applied for this position.',
                    'job_not_available' => 'This job listing is no longer active.',
                    'notes_too_long' => 'Cover note cannot exceed 1000 characters.',
                    default => 'Application could not be submitted.',
                };
                header('Location: /jobs/view?id=' . $jobId, true, 303);
                exit;
            }
        }
        if ($path === '/company/login') {
            $user = authenticate($pdo, $_POST);
            if (!$user) {
                throw new HttpError(401, 'Email or password is incorrect.');
            }
            if ($user['role'] !== 'company') {
                throw new HttpError(403, 'A company staff account is required. Students should use the student portal.');
            }
            $membership = Employment\get_user_company_organization($pdo, (int) $user['id']);
            if (!$membership) {
                throw new HttpError(403, 'No company organization is associated with this account.');
            }
            if ($membership['org_status'] !== 'ACTIVE') {
                throw new HttpError(403, 'Your company is ' . strtolower($membership['org_status']) . '. Awaiting platform administrator approval.');
            }
            if (!session_regenerate_id(true)) {
                throw new \RuntimeException('Session rotation failed.');
            }
            $_SESSION = ['user_id' => (int) $user['id'], 'role' => 'company', 'org_id' => (int) $membership['org_id'], 'last_seen' => time()];
            csrf_token();
            header('Location: /company/dashboard', true, 303);
            exit;
        }
        if ($path === '/college/login') {
            $user = authenticate($pdo, $_POST);
            if (!$user) {
                throw new HttpError(401, 'Email or password is incorrect.');
            }
            if ($user['role'] !== 'college') {
                throw new HttpError(403, 'A college placement officer account is required. Students should use the student portal.');
            }
            $membership = Employment\get_user_college_organization($pdo, (int) $user['id']);
            if (!$membership) {
                throw new HttpError(403, 'No college organization is associated with this account.');
            }
            if ($membership['org_status'] !== 'ACTIVE') {
                throw new HttpError(403, 'Your college is ' . strtolower($membership['org_status']) . '. Awaiting platform administrator approval.');
            }
            if (!session_regenerate_id(true)) {
                throw new \RuntimeException('Session rotation failed.');
            }
            $_SESSION = ['user_id' => (int) $user['id'], 'role' => 'college', 'org_id' => (int) $membership['org_id'], 'last_seen' => time()];
            csrf_token();
            header('Location: /college/dashboard', true, 303);
            exit;
        }
        if ($path === '/admin/login') {
            $user = authenticate($pdo, $_POST);
            if (!$user) {
                throw new HttpError(401, 'Email or password is incorrect.');
            }
            if ($user['role'] !== 'admin') {
                throw new HttpError(403, 'Platform administrator privileges are required.');
            }
            if (!session_regenerate_id(true)) {
                throw new \RuntimeException('Session rotation failed.');
            }
            $_SESSION = ['user_id' => (int) $user['id'], 'role' => 'admin', 'last_seen' => time()];
            csrf_token();
            header('Location: /admin/dashboard', true, 303);
            exit;
        }
        if ($path === '/company/jobs/new') {
            $staff = Employment\current_company_staff($pdo);
            try {
                $jobId = Employment\create_job($pdo, (int) $staff['user']['id'], (int) $staff['org']['org_id'], $_POST);
                $_SESSION['flash'] = 'Job listing published successfully.';
                header('Location: /company/dashboard', true, 303);
                exit;
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'invalid_job_title' => 'Job title must be between 3 and 140 characters.',
                    'invalid_job_description' => 'Job description must be between 10 and 5000 characters.',
                    'invalid_job_skills' => 'Required skills must be provided (up to 500 characters).',
                    'invalid_role_category' => 'Please select a valid role category.',
                    'invalid_job_location' => 'Location must be between 2 and 100 characters.',
                    default => 'Job listing could not be created. Check input fields.',
                };
                header('Location: /company/jobs/new', true, 303);
                exit;
            }
        }
        if ($path === '/company/jobs/edit') {
            $staff = Employment\current_company_staff($pdo);
            $jobId = (int) ($_POST['id'] ?? 0);
            if ($jobId < 1) {
                throw new HttpError(400, 'Job ID is required.');
            }
            try {
                Employment\update_job($pdo, (int) $staff['user']['id'], (int) $staff['org']['org_id'], $jobId, $_POST);
                $_SESSION['flash'] = 'Job listing updated successfully.';
                header('Location: /company/dashboard', true, 303);
                exit;
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'job_not_found' => 'Job listing not found or access denied.',
                    'invalid_job_title' => 'Job title must be between 3 and 140 characters.',
                    'invalid_job_description' => 'Job description must be between 10 and 5000 characters.',
                    'invalid_job_skills' => 'Required skills must be provided (up to 500 characters).',
                    'invalid_role_category' => 'Please select a valid role category.',
                    'invalid_job_location' => 'Location must be between 2 and 100 characters.',
                    default => 'Job listing could not be updated. Check input fields.',
                };
                header('Location: /company/jobs/edit?id=' . $jobId, true, 303);
                exit;
            }
        }
        if ($path === '/company/applicants/status') {
            $staff = Employment\current_company_staff($pdo);
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            $status = trim((string) ($_POST['status'] ?? ''));
            if ($applicationId < 1) {
                throw new HttpError(400, 'Application ID is required.');
            }
            try {
                Employment\update_application_status($pdo, (int) $staff['user']['id'], (int) $staff['org']['org_id'], $applicationId, $status);
                $_SESSION['flash'] = 'Application status updated to ' . ucfirst($status) . '.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'application_not_found' => 'Application not found or access denied.',
                    'invalid_application_status' => 'Invalid status selection.',
                    default => 'Application status could not be updated.',
                };
            }
            $jobIdParam = !empty($_POST['job_id']) ? '?job_id=' . (int) $_POST['job_id'] : '';
            header('Location: /company/applicants' . $jobIdParam, true, 303);
            exit;
        }
        if ($path === '/admin/organizations/approve') {
            $admin = Organizations\current_platform_admin($pdo);
            $orgId = (int) ($_POST['organization_id'] ?? 0);
            if ($orgId < 1) {
                throw new HttpError(400, 'Organization ID is required.');
            }
            try {
                Organizations\approve_organization($pdo, (int) $admin['id'], $orgId);
                $_SESSION['flash'] = 'Organization approved and activated successfully.';
            } catch (Organizations\OrganizationError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'organization_not_found' => 'Organization not found.',
                    'organization_not_pending' => 'Organization is not in pending status.',
                    default => 'Could not approve organization.',
                };
            }
            header('Location: /admin/dashboard', true, 303);
            exit;
        }
        if ($path === '/admin/organizations/suspend') {
            $admin = Organizations\current_platform_admin($pdo);
            $orgId = (int) ($_POST['organization_id'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? 'Administrative action'));
            if ($orgId < 1) {
                throw new HttpError(400, 'Organization ID is required.');
            }
            try {
                Organizations\suspend_organization($pdo, (int) $admin['id'], $orgId, $reason);
                $_SESSION['flash'] = 'Organization suspended.';
            } catch (Organizations\OrganizationError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'organization_not_found' => 'Organization not found.',
                    'organization_already_suspended' => 'Organization is already suspended.',
                    default => 'Could not suspend organization.',
                };
            }
            header('Location: /admin/dashboard', true, 303);
            exit;
        }
        if ($path === '/admin/requests/review') {
            $admin = Organizations\current_platform_admin($pdo);
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            $notes = (string) ($_POST['review_notes'] ?? '');
            if ($requestId < 1) {
                throw new HttpError(400, 'Request ID is required.');
            }
            try {
                $res = Organizations\review_organization_request($pdo, (int) $admin['id'], $requestId, $decision, $notes);
                if (($res['status'] ?? '') === 'APPROVED') {
                    $token = $res['invitation']['raw_token'] ?? '';
                    $_SESSION['flash'] = "Organization approved and activated! Setup invitation token generated: {$token}";
                } else {
                    $_SESSION['flash'] = 'Organization request rejected.';
                }
            } catch (Organizations\OrganizationError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'request_not_found' => 'Request not found.',
                    'request_not_pending' => 'Request is no longer pending.',
                    default => 'Could not process organization request.',
                };
            }
            header('Location: /admin/dashboard?tab=requests', true, 303);
            exit;
        }
        if ($path === '/admin/invitations/revoke') {
            $admin = Organizations\current_platform_admin($pdo);
            $invId = (int) ($_POST['invitation_id'] ?? 0);
            if ($invId < 1) {
                throw new HttpError(400, 'Invitation ID is required.');
            }
            try {
                Organizations\revoke_invitation($pdo, (int) $admin['id'], $invId);
                $_SESSION['flash'] = 'Staff invitation has been revoked.';
            } catch (Organizations\OrganizationError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'invitation_not_found' => 'Invitation not found.',
                    'invitation_already_used' => 'Invitation has already been consumed.',
                    'invitation_already_revoked' => 'Invitation has already been revoked.',
                    default => 'Could not revoke invitation.',
                };
            }
            header('Location: /admin/dashboard?tab=invitations', true, 303);
            exit;
        }
        if ($path === '/admin/invitations/create') {
            $admin = Organizations\current_platform_admin($pdo);
            $orgId = (int) ($_POST['organization_id'] ?? 0);
            $email = (string) ($_POST['email'] ?? '');
            $role = (string) ($_POST['role'] ?? 'STAFF');
            if ($orgId < 1) {
                throw new HttpError(400, 'Organization ID is required.');
            }
            try {
                $inv = Organizations\create_invitation($pdo, (int) $admin['id'], $orgId, $email, $role);
                $_SESSION['flash'] = "Staff invitation issued successfully! Invitation Token: {$inv['raw_token']}";
            } catch (Organizations\OrganizationError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'invalid_email' => 'Please provide a valid email address.',
                    'invalid_role' => 'Invalid staff role specified.',
                    'user_already_member' => 'This user is already an active member of this organization.',
                    default => 'Could not create invitation.',
                };
            }
            header('Location: /admin/dashboard?tab=invitations', true, 303);
            exit;
        }
        if ($path === '/request-access') {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            try {
                Organizations\submit_organization_request($pdo, $_POST, $clientIp);
                $_SESSION['flash'] = 'Your institutional access request has been submitted. Our compliance team will review your organization credentials.';
                header('Location: /request-access?submitted=1', true, 303);
                exit;
            } catch (Organizations\OrganizationError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'invalid_organization_name' => 'Organization name must be between 2 and 120 characters.',
                    'invalid_organization_type' => 'Please select COMPANY or COLLEGE.',
                    'invalid_website_url' => 'Please enter a valid official website URL.',
                    'invalid_contact_name' => 'Contact name must be between 2 and 100 characters.',
                    'invalid_contact_email' => 'Please enter a valid institutional contact email.',
                    'invalid_designation' => 'Designation/title must be between 2 and 100 characters.',
                    'invalid_verification_notes' => 'Please provide verification details (between 5 and 2000 characters).',
                    'request_already_pending' => 'A pending access request from this email address is already under review.',
                    default => 'Could not submit request. Check input fields.',
                };
                header('Location: /request-access', true, 303);
                exit;
            }
        }
        if ($path === '/affiliation/request') {
            $user = current_student($pdo);
            $collegeOrgId = (int) ($_POST['college_org_id'] ?? 0);
            try {
                Employment\submit_affiliation_request($pdo, (int) $user['id'], $collegeOrgId, $_POST);
                $_SESSION['flash'] = 'Affiliation request submitted successfully. Awaiting placement officer review.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'active_college_required' => 'Please select a verified, active college.',
                    'invalid_enrollment_number' => 'University enrollment / USN must be between 2 and 50 characters.',
                    'invalid_degree' => 'Degree must be between 2 and 100 characters.',
                    'invalid_department' => 'Department must be between 2 and 100 characters.',
                    'invalid_graduation_year' => 'Graduation year must be between 2000 and 2100.',
                    'verification_note_too_long' => 'Verification note cannot exceed 1000 characters.',
                    'pending_affiliation_exists' => 'You already have a pending affiliation request for this institution.',
                    default => 'Affiliation request could not be submitted. Check input fields.',
                };
            }
            header('Location: /affiliation/request', true, 303);
            exit;
        }
        if ($path === '/affiliation/cancel') {
            $user = current_student($pdo);
            $requestId = (int) ($_POST['request_id'] ?? 0);
            try {
                Employment\cancel_affiliation_request($pdo, (int) $user['id'], $requestId);
                $_SESSION['flash'] = 'Affiliation request cancelled.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'cannot_cancel_non_pending_request' => 'Only pending affiliation requests can be cancelled.',
                    default => 'Affiliation request could not be cancelled.',
                };
            }
            header('Location: /affiliation/request', true, 303);
            exit;
        }
        if ($path === '/college/affiliations/review') {
            $staff = Employment\current_college_staff($pdo);
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            $reason = (string) ($_POST['reason'] ?? '');
            try {
                Employment\review_affiliation_request($pdo, (int) $staff['org']['org_id'], (int) $staff['user']['id'], $requestId, $decision, $reason);
                $_SESSION['flash'] = 'Affiliation request ' . strtolower($decision) . ' successfully.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'affiliation_already_reviewed' => 'This affiliation request has already been reviewed.',
                    'decision_reason_required' => 'A review rationale (at least 3 characters) is required by audit policy.',
                    'invalid_review_decision' => 'Decision must be APPROVED or REJECTED.',
                    default => 'Could not review affiliation request.',
                };
            }
            header('Location: /college/affiliations', true, 303);
            exit;
        }
        if ($path === '/college/affiliations/revoke') {
            $staff = Employment\current_college_staff($pdo);
            $studentId = (int) ($_POST['student_id'] ?? 0);
            $reason = (string) ($_POST['reason'] ?? '');
            try {
                Employment\revoke_affiliation($pdo, (int) $staff['org']['org_id'], (int) $staff['user']['id'], $studentId, $reason);
                $_SESSION['flash'] = 'Student affiliation revoked successfully.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'revocation_reason_required' => 'A revocation reason (at least 3 characters) is required for audit logs.',
                    'student_not_affiliated_with_college' => 'Student is not affiliated with this college.',
                    default => 'Could not revoke student affiliation.',
                };
            }
            header('Location: /college/dashboard', true, 303);
            exit;
        }
        if ($path === '/privacy/settings') {
            $user = current_student($pdo);
            try {
                Employment\save_profile($pdo, (int) $user['id'], $_POST);
                $_SESSION['flash'] = 'Sharing and privacy settings updated.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = 'Could not update privacy settings.';
            }
            header('Location: /privacy/settings', true, 303);
            exit;
        }
        if ($path === '/achievements') {
            $user = current_student($pdo);
            try {
                Employment\create_achievement($pdo, (int) $user['id'], $_POST);
                $_SESSION['flash'] = 'Achievement added successfully.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'invalid_achievement_category' => 'Select a valid achievement category.',
                    'invalid_achievement_title' => 'Title must be between 2 and 150 characters.',
                    'issuer_too_long' => 'Issuer cannot exceed 150 characters.',
                    'invalid_credential_url' => 'Please provide a valid URL for credential verification.',
                    'description_too_long' => 'Description cannot exceed 1000 characters.',
                    'invalid_evidence_assessment' => 'Selected GitHub assessment is invalid or not owned by you.',
                    default => 'Achievement could not be saved. Check input fields.',
                };
            }
            header('Location: /achievements', true, 303);
            exit;
        }
        if ($path === '/achievements/delete') {
            $user = current_student($pdo);
            $achievementId = (int) ($_POST['id'] ?? 0);
            try {
                Employment\delete_achievement($pdo, (int) $user['id'], $achievementId);
                $_SESSION['flash'] = 'Achievement removed.';
            } catch (Employment\EmploymentError $e) {
                $_SESSION['flash_error'] = 'Could not remove achievement.';
            }
            header('Location: /achievements', true, 303);
            exit;
        }
        if ($path === '/auth/link') {
            $userId = $_SESSION['user_id'] ?? null;
            if (!is_int($userId) || $userId < 1) {
                throw new HttpError(401, 'Sign in required to link an identity.');
            }
            $provider = field($_POST, 'provider', 32);
            if (!in_array($provider, ['google', 'github', 'linkedin'], true)) {
                throw new HttpError(400, 'Invalid provider.');
            }
            $_SESSION['oauth_linking_user_id'] = $userId;
            header('Location: /auth/login/' . $provider . '?link=1', true, 303);
            exit;
        }
        if ($path === '/auth/unlink') {
            $userId = $_SESSION['user_id'] ?? null;
            if (!is_int($userId) || $userId < 1) {
                throw new HttpError(401, 'Sign in required.');
            }
            $provider = field($_POST, 'provider', 32);
            try {
                Auth\unlink_identity($pdo, $userId, $provider);
                $_SESSION['flash'] = ucfirst($provider) . ' identity unlinked successfully.';
            } catch (Auth\OAuthError $e) {
                $_SESSION['flash_error'] = match ($e->reason) {
                    'cannot_unlink_sole_authentication_method' => 'Cannot unlink your sole sign-in method. Set a password or link another provider first.',
                    default => 'Failed to unlink identity: ' . $e->reason,
                };
            }
            header('Location: /privacy/settings', true, 303);
            exit;
        }
        if ($path === '/register') {
            register_student($pdo, $_POST);
            $_SESSION['flash'] = 'Your local account is ready. Sign in to continue.';
            header('Location: /login', true, 303);
            exit;
        }
        $user = authenticate($pdo, $_POST);
        if (!$user) {
            throw new HttpError(401, 'Email or password is incorrect.');
        }
        if ($user['role'] !== 'student') {
            throw new HttpError(403, 'Only the student workflow is available in this local slice.');
        }
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Session rotation failed.');
        }
        $_SESSION = ['user_id' => (int) $user['id'], 'role' => 'student', 'last_seen' => time()];
        csrf_token();
        header('Location: /dashboard', true, 303);
        exit;
    }
    if ($path === '/dashboard') {
        $user = current_student($pdo);
        $assessments = Evidence\history($pdo, (int) $user['id']);
        $applications = Employment\list_student_applications($pdo, (int) $user['id']);
        $allResumes = Employment\list_resumes($pdo, (int) $user['id']);
        $profile = Employment\get_profile($pdo, (int) $user['id']);
        $achievements = Employment\list_student_achievements($pdo, (int) $user['id']);
        $screen = 'dashboard';
    } elseif ($path === '/profile') {
        $user = current_student($pdo);
        $profile = Employment\get_profile($pdo, (int) $user['id']);
        $approvedColleges = Employment\list_approved_colleges($pdo);
        $screen = 'profile';
    } elseif ($path === '/jobs') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $category = isset($query['category']) ? trim((string) $query['category']) : null;
        $search = isset($query['q']) ? trim((string) $query['q']) : null;
        $jobs = Employment\list_active_jobs($pdo, $category, $search);
        $selectedCategory = $category ?? 'all';
        $searchQuery = $search ?? '';
        $screen = 'jobs';
    } elseif ($path === '/jobs/view') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $jobId = (int) ($query['id'] ?? 0);
        if ($jobId < 1) {
            throw new HttpError(400, 'Job ID is required.');
        }
        $job = Employment\get_job($pdo, $jobId);
        if (!$job || $job['status'] !== 'active' || $job['company_status'] !== 'ACTIVE') {
            throw new HttpError(404, 'Job not found or no longer active.');
        }
        $existingApplication = Employment\get_student_application_for_job($pdo, (int) $user['id'], $jobId);
        $screen = 'job_detail';
    } elseif ($path === '/applications') {
        $user = current_student($pdo);
        $applications = Employment\list_student_applications($pdo, (int) $user['id']);
        $screen = 'applications';
    } elseif ($path === '/resume') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $resumeId = (int) ($query['id'] ?? 0);
        $resume = null;
        if ($resumeId > 0) {
            $resume = Employment\get_resume($pdo, $resumeId, (int) $user['id']);
            if (!$resume) {
                throw new HttpError(404, 'Resume not found or access denied.');
            }
        } else {
            $allResumes = Employment\list_resumes($pdo, (int) $user['id']);
            if (!empty($allResumes)) {
                $resume = Employment\get_resume($pdo, (int) $allResumes[0]['id'], (int) $user['id']);
            }
        }
        $completedAssessments = array_values(array_filter(
            Evidence\history($pdo, (int) $user['id']),
            fn($a) => ($a['state'] ?? '') === 'completed'
        ));
        $allResumes = Employment\list_resumes($pdo, (int) $user['id']);
        $profile = Employment\get_profile($pdo, (int) $user['id']);
        $atsAnalysis = $resume !== null ? Employment\analyze_resume_for_ats($resume) : null;
        $screen = 'resume';
    } elseif ($path === '/resume/preview') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $resumeId = (int) ($query['id'] ?? 0);
        if ($resumeId < 1) {
            throw new HttpError(400, 'Resume ID is required.');
        }
        $resume = Employment\get_resume($pdo, $resumeId, (int) $user['id']);
        if (!$resume) {
            throw new HttpError(404, 'Resume not found or access denied.');
        }
        $targetJobId = (int) ($query['job_id'] ?? 0);
        $targetJob = $targetJobId > 0 ? Employment\get_job($pdo, $targetJobId) : null;
        $activeJobs = Employment\list_active_jobs($pdo);
        $atsAnalysis = Employment\analyze_resume_for_ats($resume, $targetJob);
        $screen = 'resume_preview';
    } elseif ($path === '/assess') {
        $user = current_student($pdo);
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $assessmentId = (int) ($query['id'] ?? 0);
        if ($assessmentId < 1) {
            throw new HttpError(400, 'Assessment ID is required.');
        }
        $assessment = Evidence\detail($pdo, $assessmentId, (int) $user['id']);
        if (!$assessment) {
            throw new HttpError(404, 'Assessment not found or access denied.');
        }
        $screen = 'assessment';
    } elseif ($path === '/company/dashboard') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $jobs = Employment\list_company_jobs($pdo, (int) $user['id'], (int) $org['org_id']);
        $allApplicants = Employment\list_company_applications($pdo, (int) $user['id'], (int) $org['org_id']);
        $screen = 'company_dashboard';
    } elseif ($path === '/company/jobs/new') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $job = null;
        $screen = 'company_job_form';
    } elseif ($path === '/company/jobs/edit') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $jobId = (int) ($query['id'] ?? 0);
        if ($jobId < 1) {
            throw new HttpError(400, 'Job ID is required.');
        }
        $job = Employment\get_job($pdo, $jobId);
        if (!$job || (int) $job['organization_id'] !== (int) $org['org_id']) {
            throw new HttpError(404, 'Job listing not found or access denied.');
        }
        $screen = 'company_job_form';
    } elseif ($path === '/company/applicants') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $jobId = !empty($query['job_id']) ? (int) $query['job_id'] : null;
        if ($jobId !== null) {
            $checkJob = Employment\get_job($pdo, $jobId);
            if (!$checkJob || (int) $checkJob['organization_id'] !== (int) $org['org_id']) {
                throw new HttpError(404, 'Job not found.');
            }
        }
        $applicants = Employment\list_company_applications($pdo, (int) $user['id'], (int) $org['org_id'], $jobId);
        $jobs = Employment\list_company_jobs($pdo, (int) $user['id'], (int) $org['org_id']);
        $selectedJobId = $jobId;
        $screen = 'company_applicants';
    } elseif ($path === '/company/talent') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $skillsQuery = !empty($query['q']) ? trim((string) $query['q']) : null;
        $talentList = Employment\list_opted_in_talent($pdo, (int) $user['id'], (int) $org['org_id'], $skillsQuery);
        $searchQuery = $skillsQuery ?? '';
        $screen = 'company_talent';
    } elseif ($path === '/company/candidate') {
        $staff = Employment\current_company_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $appId = (int) ($query['id'] ?? 0);
        if ($appId < 1) {
            throw new HttpError(400, 'Application ID is required.');
        }
        try {
            $candidateBundle = Employment\get_candidate_for_recruiter($pdo, $appId, (int) $user['id'], (int) $org['org_id']);
        } catch (Employment\EmploymentError $e) {
            throw new HttpError(404, 'Candidate application not found or access denied.');
        }
        $screen = 'company_candidate';
    } elseif ($path === '/college/dashboard') {
        $staff = Employment\current_college_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $students = Employment\list_college_students($pdo, (int) $org['org_id'], (int) $user['id']);
        $pendingAffiliations = Employment\list_college_affiliation_requests($pdo, (int) $org['org_id'], (int) $user['id'], 'PENDING');
        $screen = 'college_dashboard';
    } elseif ($path === '/college/students/view') {
        $staff = Employment\current_college_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $studentId = (int) ($query['id'] ?? 0);
        if ($studentId < 1) {
            throw new HttpError(400, 'Student ID is required.');
        }
        try {
            $placement = Employment\get_college_student_placement_details($pdo, (int) $org['org_id'], $studentId, (int) $user['id']);
        } catch (Employment\EmploymentError $e) {
            if ($e->statusCode === 403) {
                throw new HttpError(403, 'Student has not consented to share placement records with the college.');
            }
            throw new HttpError(404, 'Student not found in your college roster.');
        }
        $screen = 'college_student_detail';
    } elseif ($path === '/college/affiliations') {
        $staff = Employment\current_college_staff($pdo);
        $user = $staff['user'];
        $org = $staff['org'];
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        $statusFilter = !empty($query['status']) ? (string) $query['status'] : null;
        $requests = Employment\list_college_affiliation_requests($pdo, (int) $org['org_id'], (int) $user['id'], $statusFilter);
        $selectedStatus = $statusFilter ?? 'all';
        $screen = 'college_affiliations';
    } elseif ($path === '/affiliation/request') {
        $user = current_student($pdo);
        $approvedColleges = Employment\list_approved_colleges($pdo);
        $affiliationRequests = Employment\list_student_affiliation_requests($pdo, (int) $user['id']);
        $profile = Employment\get_profile($pdo, (int) $user['id']);
        $screen = 'affiliation_request';
    } elseif ($path === '/privacy/settings') {
        $user = current_student($pdo);
        $profile = Employment\get_profile($pdo, (int) $user['id']);
        $recentLogs = Employment\list_student_access_logs($pdo, (int) $user['id'], 15);
        $linkedIdentities = Auth\list_user_identities($pdo, (int) $user['id']);
        $configuredProviders = Auth\get_configured_providers();
        $screen = 'privacy_settings';
    } elseif ($path === '/privacy/access-log') {
        $user = current_student($pdo);
        $accessLogs = Employment\list_student_access_logs($pdo, (int) $user['id'], 100);
        $screen = 'privacy_access_log';
    } elseif ($path === '/achievements') {
        $user = current_student($pdo);
        $achievements = Employment\list_student_achievements($pdo, (int) $user['id']);
        $assessments = array_values(array_filter(
            Evidence\history($pdo, (int) $user['id']),
            fn($a) => ($a['state'] ?? '') === 'completed'
        ));
        $screen = 'achievements';
    } elseif ($path === '/admin') {
        header('Location: /admin/dashboard', true, 303);
        exit;
    } elseif ($path === '/admin/dashboard') {
        $admin = Organizations\current_platform_admin($pdo);
        $user = $admin;
        $activeTab = (string) ($_GET['tab'] ?? 'overview');
        $validTabs = ['overview', 'requests', 'orgs', 'users', 'invitations', 'access_reviews', 'audit', 'security'];
        if (!in_array($activeTab, $validTabs, true)) {
            $activeTab = 'overview';
        }
        $metrics = Organizations\get_admin_system_metrics($pdo);
        $pendingRequests = Organizations\list_organization_requests($pdo, 'PENDING');
        $allRequests = Organizations\list_organization_requests($pdo, null, 50);
        $pendingOrgs = Organizations\list_organizations($pdo, 'PENDING');
        $activeOrgs = Organizations\list_organizations($pdo, 'ACTIVE');
        $suspendedOrgs = Organizations\list_organizations($pdo, 'SUSPENDED');
        $allOrgs = Organizations\list_organizations($pdo, null, 100);
        $invitations = Organizations\list_all_invitations($pdo, null, 100);
        $usersList = Organizations\list_all_users($pdo, $_GET['user_role'] ?? null, $_GET['user_search'] ?? null, 100);
        $accessReviews = Organizations\list_all_access_logs($pdo, 50);
        $auditLogs = Organizations\list_audit_logs($pdo, 50);
        $screen = 'admin_dashboard';
    } elseif (str_starts_with($path, '/auth/login/')) {
        $provider = substr($path, strlen('/auth/login/'));
        if (!in_array($provider, ['google', 'github', 'linkedin'], true)) {
            throw new HttpError(404, 'Provider not supported.');
        }
        if (!Auth\is_provider_configured($provider)) {
            $_SESSION['flash_error'] = ucfirst($provider) . ' sign-in is not configured. Set ' . strtoupper($provider) . '_CLIENT_ID in the environment to enable.';
            header('Location: /login', true, 303);
            exit;
        }
        $role = $_GET['role'] ?? 'student';
        if (!in_array($role, ['student', 'company', 'college'], true)) {
            $role = 'student';
        }
        $linkingUserId = null;
        if (!empty($_GET['link']) && isset($_SESSION['user_id'])) {
            $linkingUserId = (int) $_SESSION['user_id'];
            if (isset($_SESSION['oauth_linking_user_id']) && (int) $_SESSION['oauth_linking_user_id'] !== $linkingUserId) {
                throw new HttpError(403, 'Unauthorized identity linking attempt.');
            }
            unset($_SESSION['oauth_linking_user_id']);
        }
        try {
            $authUrl = Auth\initiate_oauth_flow($pdo, $provider, $role, $linkingUserId);
            parse_str(parse_url($authUrl, PHP_URL_QUERY) ?? '', $queryParams);
            if (!empty($queryParams['state'])) {
                $_SESSION['oauth_state_' . $provider] = $queryParams['state'];
            }
            header('Location: ' . $authUrl, true, 303);
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to initiate social sign-in: ' . $e->getMessage();
            header('Location: /login', true, 303);
            exit;
        }
    } elseif (str_starts_with($path, '/auth/callback/')) {
        $provider = substr($path, strlen('/auth/callback/'));
        if (!in_array($provider, ['google', 'github', 'linkedin'], true)) {
            throw new HttpError(404, 'Provider not supported.');
        }
        $state = $_GET['state'] ?? null;
        $code = $_GET['code'] ?? null;
        $oauthError = $_GET['error'] ?? null;

        if ($oauthError !== null) {
            $_SESSION['flash_error'] = 'Social sign-in was cancelled or denied: ' . htmlspecialchars((string) $oauthError, ENT_QUOTES, 'UTF-8');
            header('Location: /login', true, 303);
            exit;
        }

        if (!is_string($state) || !is_string($code) || trim($state) === '' || trim($code) === '') {
            $_SESSION['flash_error'] = 'Invalid OAuth callback parameters.';
            header('Location: /login', true, 303);
            exit;
        }

        // BINDING VERIFICATION: Check session-bound state to block Login CSRF attacks
        $sessionState = $_SESSION['oauth_state_' . $provider] ?? null;
        unset($_SESSION['oauth_state_' . $provider]);
        if (!is_string($sessionState) || !hash_equals($sessionState, $state)) {
            $_SESSION['flash_error'] = 'Login CSRF detected: authentication session mismatch. Please try again.';
            header('Location: /login', true, 303);
            exit;
        }

        try {
            $authResult = Auth\handle_oauth_callback($pdo, $provider, $state, $code, null, $sessionState);
            if (!session_regenerate_id(true)) {
                throw new \RuntimeException('Session rotation failed.');
            }
            $_SESSION['user_id'] = (int) $authResult['user']['id'];
            $_SESSION['role'] = $authResult['user']['role'];
            $_SESSION['last_seen'] = time();

            if (!empty($authResult['linked'])) {
                $_SESSION['flash'] = 'Successfully linked your ' . ucfirst($provider) . ' account.';
                header('Location: /privacy/settings', true, 303);
                exit;
            }

            if (!empty($authResult['is_new'])) {
                $_SESSION['flash'] = 'Welcome to EIDOLON PATHWAY! Your account was securely provisioned via ' . ucfirst($provider) . '.';
            } else {
                $_SESSION['flash'] = 'Signed in successfully via ' . ucfirst($provider) . '.';
            }

            $target = match ($_SESSION['role']) {
                'company' => '/company/dashboard',
                'college' => '/college/dashboard',
                'admin' => '/admin/dashboard',
                default => '/dashboard',
            };
            header('Location: ' . $target, true, 303);
            exit;
        } catch (Auth\OAuthError $e) {
            if ($e->reason === 'account_linking_required') {
                $_SESSION['flash_error'] = 'An account with this email address already exists. Sign in with your password and link your ' . ucfirst($provider) . ' identity in settings.';
            } else {
                $_SESSION['flash_error'] = 'Authentication failed: ' . $e->reason;
            }
            header('Location: /login', true, 303);
            exit;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Social sign-in could not be completed.';
            header('Location: /login', true, 303);
            exit;
        }
    } else {
        $screen = match ($path) {
            '/register' => 'register',
            '/request-access' => 'request_access',
            '/company/login' => 'company_login',
            '/college/login' => 'college_login',
            '/admin/login' => 'admin_login',
            default => 'login',
        };
        $flash = $_SESSION['flash'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        $configuredProviders = Auth\get_configured_providers();
    }
} catch (HttpError $error) {
    http_response_code($error->status);
    if ($error->status === 429) {
        header('Retry-After: 300');
    }
    $message = $error->getMessage();
    $screen = 'error';
} catch (\Throwable $error) {
    http_response_code(503);
    error_log('pathway.request_failed');
    $message = 'Local storage is unavailable or incompatible. Check initialization and the server terminal.';
    $screen = 'error';
}
header('Content-Type: text/html; charset=UTF-8');
require __DIR__ . '/views/page.php';
