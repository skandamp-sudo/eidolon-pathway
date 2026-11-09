<?php
declare(strict_types=1);

namespace Pathway\Tests\Auth;

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth/oauth.php';
require_once __DIR__ . '/../../app/organizations/organization.php';

use Pathway\Auth;
use Pathway\Organizations;

final class MockOAuthTransport implements Auth\OAuthTransport
{
    public array $postRequests = [];
    public array $getRequests = [];
    public array $tokenResponses = [];
    public array $userInfoResponses = [];

    public function post(string $url, array $params, array $headers = []): array
    {
        $this->postRequests[] = ['url' => $url, 'params' => $params, 'headers' => $headers];
        return $this->tokenResponses[$url] ?? ['access_token' => 'mock_access_token_xyz123'];
    }

    public function get(string $url, array $headers = []): array
    {
        $this->getRequests[] = ['url' => $url, 'headers' => $headers];
        return $this->userInfoResponses[$url] ?? [];
    }
}

function test_oauth_workflow(): void
{
    $passed = 0;
    $failed = 0;

    $assert = function (bool $condition, string $description) use (&$passed, &$failed): void {
        if ($condition) {
            echo "PASS {$description}\n";
            $passed++;
        } else {
            echo "FAIL {$description}\n";
            $failed++;
        }
    };

    echo "====================================================================\n";
    echo "  CHECKPOINT 009D: SECURE SOCIAL AUTHENTICATION & OIDC TESTS\n";
    echo "====================================================================\n";

    // Setup isolated test database
    $dbPath = sys_get_temp_dir() . '/pathway_oauth_test_' . bin2hex(random_bytes(8)) . '.sqlite';
    putenv('PATHWAY_DATABASE_PATH=' . $dbPath);
    $pdo = \Pathway\database(true, $dbPath);
    \Pathway\initialize_database($pdo);

    // 1. Provider configuration fail-closed
    putenv('GOOGLE_CLIENT_ID');
    putenv('GOOGLE_CLIENT_SECRET');
    $assert(Auth\get_provider_config('google') === null, 'unconfigured provider fails closed (returns null)');
    $assert(!Auth\is_provider_configured('google'), 'is_provider_configured returns false when credentials missing');

    // Configure test environment credentials
    putenv('GOOGLE_CLIENT_ID=test-google-client-id.apps.googleusercontent.com');
    putenv('GOOGLE_CLIENT_SECRET=test-google-client-secret-999');
    putenv('GOOGLE_REDIRECT_URI=http://127.0.0.1:8080/auth/callback/google');

    putenv('GITHUB_CLIENT_ID=test-github-client-id-abc');
    putenv('GITHUB_CLIENT_SECRET=test-github-client-secret-xyz');
    putenv('GITHUB_REDIRECT_URI=http://127.0.0.1:8080/auth/callback/github');

    $googleCfg = Auth\get_provider_config('google');
    $assert($googleCfg !== null && $googleCfg['client_id'] === 'test-google-client-id.apps.googleusercontent.com', 'configured provider returns validated credentials');
    $assert(Auth\is_provider_configured('google'), 'is_provider_configured returns true when configured');

    $configuredList = Auth\get_configured_providers();
    $assert(isset($configuredList['google']) && isset($configuredList['github']), 'get_configured_providers discovers active providers');

    // 2. Flow Initiation & PKCE parameters
    $redirectUrl = Auth\initiate_oauth_flow($pdo, 'google', 'student');
    $parsedUrl = parse_url($redirectUrl);
    parse_str($parsedUrl['query'] ?? '', $queryParams);

    $assert(!empty($queryParams['state']) && strlen($queryParams['state']) === 64, 'initiate flow generates 256-bit CSPRNG state');
    $assert(!empty($queryParams['code_challenge']), 'initiate flow generates PKCE code_challenge');
    $assert(($queryParams['code_challenge_method'] ?? '') === 'S256', 'PKCE method is strictly S256');

    // Verify persisted state record
    $stmt = $pdo->prepare('SELECT * FROM oauth_states WHERE state = ?');
    $stmt->execute([$queryParams['state']]);
    $stateRow = $stmt->fetch();
    $assert($stateRow !== false, 'state record persisted in database');
    $assert($stateRow['provider'] === 'google' && $stateRow['used_at'] === null, 'state marked unused with correct provider');
    $assert(!empty($stateRow['code_verifier']), 'PKCE code_verifier stored securely server-side');

    // 3. New User Auto-Provisioning with Locked Password
    $transport = new MockOAuthTransport();
    $transport->tokenResponses['https://oauth2.googleapis.com/token'] = ['access_token' => 'tok_valid_user1'];
    $transport->userInfoResponses['https://openidconnect.googleapis.com/v1/userinfo'] = [
        'sub' => 'google-sub-10001',
        'email' => 'social.student1@example.test',
        'email_verified' => true,
        'name' => 'Maya Lin',
    ];

    $authResult = Auth\handle_oauth_callback($pdo, 'google', $queryParams['state'], 'auth_code_123', $transport);
    $assert($authResult['is_new'] === true, 'new OAuth identity reports is_new=true');
    $assert($authResult['user']['email'] === 'social.student1@example.test', 'provisioned user has verified email');
    $userId1 = (int) $authResult['user']['id'];

    // Verify user in DB has locked password preventing password login before explicitly set
    $stmt = $pdo->prepare('SELECT password_hash, role FROM users WHERE id = ?');
    $stmt->execute([$userId1]);
    $userRow = $stmt->fetch();
    $assert(str_starts_with($userRow['password_hash'], '*LOCKED_OAUTH_'), 'provisioned user account has cryptographically locked password');
    $assert($userRow['role'] === 'student', 'provisioned role matches student');

    // Verify oauth_identities binding
    $identities = Auth\list_user_identities($pdo, $userId1);
    $assert(count($identities) === 1, 'user has exactly 1 bound OAuth identity');
    $assert($identities[0]['provider'] === 'google' && $identities[0]['provider_subject_id'] === 'google-sub-10001', 'bound identity has correct provider subject ID');

    // 4. Anti-Replay Defense: State already consumed
    $replayBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $queryParams['state'], 'auth_code_123', $transport);
    } catch (Auth\OAuthError $e) {
        $replayBlocked = ($e->reason === 'state_already_used');
    }
    $assert($replayBlocked, 'replay attack blocked: previously consumed state cannot be reused');

    // 5. Subsequent Login with existing identity
    $loginRedirectUrl = Auth\initiate_oauth_flow($pdo, 'google', 'student');
    parse_str(parse_url($loginRedirectUrl)['query'] ?? '', $loginParams);

    $loginResult = Auth\handle_oauth_callback($pdo, 'google', $loginParams['state'], 'auth_code_456', $transport);
    $assert($loginResult['is_new'] === false, 'existing OAuth identity reports is_new=false');
    $assert((int) $loginResult['user']['id'] === $userId1, 'existing identity resolves to the exact same user ID');

    // 6. Anti-Account-Takeover Defense:
    // Attacker or unlinked social account returning email of existing password user must NOT auto-link!
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute([
        'Existing User',
        'victim@university.test',
        password_hash('StrongSecretPass123!', PASSWORD_BCRYPT),
        'student',
    ]);
    $victimId = (int) $pdo->lastInsertId();

    $takeoverUrl = Auth\initiate_oauth_flow($pdo, 'google', 'student');
    parse_str(parse_url($takeoverUrl)['query'] ?? '', $takeoverParams);

    $transport->userInfoResponses['https://openidconnect.googleapis.com/v1/userinfo'] = [
        'sub' => 'google-sub-attacker-999',
        'email' => 'victim@university.test',
        'email_verified' => true,
        'name' => 'Attempted Takeover',
    ];

    $takeoverPrevented = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $takeoverParams['state'], 'code_takeover', $transport);
    } catch (Auth\OAuthError $e) {
        $takeoverPrevented = ($e->reason === 'account_linking_required' && $e->statusCode === 409);
    }
    $assert($takeoverPrevented, 'anti-takeover defense: social email matching password user rejected without prior linking');

    // 7. Explicit Authenticated Account Linking
    $linkUrl = Auth\initiate_oauth_flow($pdo, 'github', 'student', $userId1);
    parse_str(parse_url($linkUrl)['query'] ?? '', $linkParams);

    $transport->tokenResponses['https://github.com/login/oauth/access_token'] = ['access_token' => 'gh_tok_777'];
    $transport->userInfoResponses['https://api.github.com/user'] = [
        'id' => 8881234,
        'login' => 'mayalin-dev',
        'name' => 'Maya Lin Dev',
        'email' => 'maya.github@example.test',
    ];

    $linkResult = Auth\handle_oauth_callback($pdo, 'github', $linkParams['state'], 'code_github_link', $transport);
    $assert(!empty($linkResult['linked']), 'explicit account linking reports linked=true');
    $assert((int) $linkResult['user']['id'] === $userId1, 'linked identity bound to the authenticated user');

    $allIdentities = Auth\list_user_identities($pdo, $userId1);
    $assert(count($allIdentities) === 2, 'user now has two federated identities (Google and GitHub)');

    // 8. Unlink Identity with Multiple Providers (Allowed)
    Auth\unlink_identity($pdo, $userId1, 'github');
    $remainingIdentities = Auth\list_user_identities($pdo, $userId1);
    $assert(count($remainingIdentities) === 1 && $remainingIdentities[0]['provider'] === 'google', 'unlinking one of two identities succeeds');

    // 9. Unlink Identity Lockout Prevention (Denied for sole authentication method)
    $lockoutBlocked = false;
    try {
        Auth\unlink_identity($pdo, $userId1, 'google');
    } catch (Auth\OAuthError $e) {
        $lockoutBlocked = ($e->reason === 'cannot_unlink_sole_authentication_method');
    }
    $assert($lockoutBlocked, 'account lockout prevention: unlinking sole authentication method blocked');

    // If user sets a password, unlinking becomes allowed:
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([
        password_hash('NewUsablePassword456!', PASSWORD_BCRYPT),
        $userId1,
    ]);
    Auth\unlink_identity($pdo, $userId1, 'google');
    $finalIdentities = Auth\list_user_identities($pdo, $userId1);
    $assert(count($finalIdentities) === 0, 'unlinking last identity allowed once user has a valid password');

    // 10. Provider Email Verification Requirement
    $unverifiedUrl = Auth\initiate_oauth_flow($pdo, 'google', 'student');
    parse_str(parse_url($unverifiedUrl)['query'] ?? '', $unverifiedParams);

    $transport->userInfoResponses['https://openidconnect.googleapis.com/v1/userinfo'] = [
        'sub' => 'google-sub-unverified',
        'email' => 'unverified@example.test',
        'email_verified' => false,
        'name' => 'Unverified User',
    ];

    $unverifiedRejected = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $unverifiedParams['state'], 'code_unverified', $transport);
    } catch (Auth\OAuthError $e) {
        $unverifiedRejected = ($e->reason === 'email_not_verified_by_provider' && $e->statusCode === 403);
    }
    $assert($unverifiedRejected, 'unverified email claims rejected with 403');

    // Cleanup temp DB
    @unlink($dbPath);

    echo "====================================================================\n";
    echo "RESULT: {$passed} passed, {$failed} failed\n";
    echo "====================================================================\n";

    if ($failed > 0) {
        exit(1);
    }
}

test_oauth_workflow();
