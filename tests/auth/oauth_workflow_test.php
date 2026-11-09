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
    public array $jwksResponses = [];

    public function post(string $url, array $params, array $headers = []): array
    {
        $this->postRequests[] = ['url' => $url, 'params' => $params, 'headers' => $headers];
        return $this->tokenResponses[$url] ?? [
            'access_token' => 'mock_access_token_xyz123',
            'token_type' => 'Bearer',
        ];
    }

    public function get(string $url, array $headers = []): array
    {
        $this->getRequests[] = ['url' => $url, 'headers' => $headers];
        if (isset($this->jwksResponses[$url])) {
            return $this->jwksResponses[$url];
        }
        return $this->userInfoResponses[$url] ?? [];
    }
}

function make_test_id_token(
    string $privKeyPath,
    string $kid,
    array $claims,
    string $alg = 'RS256'
): string {
    $header = ['alg' => $alg, 'kid' => $kid, 'typ' => 'JWT'];
    $hB64 = Auth\base64url_encode((string) json_encode($header));
    $pB64 = Auth\base64url_encode((string) json_encode($claims));
    $data = $hB64 . '.' . $pB64;

    $dataPath = tempnam(sys_get_temp_dir(), 'jwt_d_');
    $sigPath = tempnam(sys_get_temp_dir(), 'jwt_s_');
    if ($dataPath === false || $sigPath === false) {
        throw new \RuntimeException('Failed to allocate temp files');
    }

    try {
        file_put_contents($dataPath, $data);
        exec('openssl dgst -sha256 -sign ' . escapeshellarg($privKeyPath) . ' -out ' . escapeshellarg($sigPath) . ' ' . escapeshellarg($dataPath) . ' 2>/dev/null');
        $rawSig = file_get_contents($sigPath);
        return $data . '.' . Auth\base64url_encode($rawSig ?: '');
    } finally {
        @unlink($dataPath);
        @unlink($sigPath);
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
    echo "  MISSION 009.1: OIDC VERIFICATION & ADVERSARIAL OAUTH SECURITY TESTS\n";
    echo "====================================================================\n";

    // Setup isolated test database
    $dbPath = sys_get_temp_dir() . '/pathway_oauth_test_' . bin2hex(random_bytes(8)) . '.sqlite';
    putenv('PATHWAY_DATABASE_PATH=' . $dbPath);
    $pdo = \Pathway\database(true, $dbPath);
    \Pathway\initialize_database($pdo);

    // 1. Generate RSA keypair for testing OIDC JWKS signatures
    $testPrivKey = tempnam(sys_get_temp_dir(), 'oidc_priv_');
    exec('openssl genrsa -out ' . escapeshellarg($testPrivKey) . ' 2048 2>/dev/null');
    $modHex = trim(str_replace('Modulus=', '', (string) shell_exec('openssl rsa -in ' . escapeshellarg($testPrivKey) . ' -noout -modulus 2>/dev/null')));
    $jwk = [
        'kty' => 'RSA',
        'kid' => 'key-google-2026',
        'n' => Auth\base64url_encode(hex2bin($modHex) ?: ''),
        'e' => Auth\base64url_encode("\x01\x00\x01"),
    ];

    // 2. Provider configuration fail-closed
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

    // 3. Flow Initiation & PKCE parameters
    $redirectUrl = Auth\initiate_oauth_flow($pdo, 'google', 'student');
    $parsedUrl = parse_url($redirectUrl);
    parse_str($parsedUrl['query'] ?? '', $queryParams);

    $state = $queryParams['state'];
    $nonce = $queryParams['nonce'];
    $assert(!empty($state) && strlen($state) === 64, 'initiate flow generates 256-bit CSPRNG state');
    $assert(!empty($queryParams['code_challenge']), 'initiate flow generates PKCE code_challenge');
    $assert(($queryParams['code_challenge_method'] ?? '') === 'S256', 'PKCE method is strictly S256');

    // Verify persisted state record
    $stmt = $pdo->prepare('SELECT * FROM oauth_states WHERE state = ?');
    $stmt->execute([$state]);
    $stateRow = $stmt->fetch();
    $assert($stateRow !== false, 'state record persisted in database');
    $assert($stateRow['provider'] === 'google' && $stateRow['used_at'] === null, 'state marked unused with correct provider');

    // 4. Adversarial Test: Login CSRF Defense (Session state mismatch)
    $transport = new MockOAuthTransport();
    $transport->jwksResponses['https://www.googleapis.com/oauth2/v3/certs'] = ['keys' => [$jwk]];

    $loginCsrfBlocked = false;
    try {
        // Attacker passes a valid DB state, but session State belongs to another session
        Auth\handle_oauth_callback($pdo, 'google', $state, 'auth_code_123', $transport, 'different_session_state_value_attacker');
    } catch (Auth\OAuthError $e) {
        $loginCsrfBlocked = ($e->reason === 'login_csrf_detected' && $e->statusCode === 403);
    }
    $assert($loginCsrfBlocked, 'adversarial: login CSRF session mismatch rejected with 403');

    // 5. Adversarial Test: OIDC Missing ID Token
    $transport->tokenResponses['https://oauth2.googleapis.com/token'] = [
        'access_token' => 'tok_valid_user1',
        // id_token intentionally missing
    ];
    $missingIdTokenBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $state, 'auth_code_123', $transport, $state);
    } catch (Auth\OAuthError $e) {
        $missingIdTokenBlocked = ($e->reason === 'id_token_missing' && $e->statusCode === 400);
    }
    $assert($missingIdTokenBlocked, 'adversarial: missing OIDC id_token on Google callback rejected with 400');

    // Need a new state since previous failed after state consumption check or let us reset state
    $pdo->prepare('UPDATE oauth_states SET used_at = NULL WHERE state = ?')->execute([$state]);

    // 6. Adversarial Test: OIDC Forged Signature
    $forgedClaims = [
        'sub' => 'google-attacker-999',
        'email' => 'victim@example.test',
        'email_verified' => true,
        'aud' => 'test-google-client-id.apps.googleusercontent.com',
        'iss' => 'https://accounts.google.com',
        'exp' => time() + 3600,
        'iat' => time(),
        'nonce' => $nonce,
    ];
    $fakePrivKey = tempnam(sys_get_temp_dir(), 'fake_priv_');
    exec('openssl genrsa -out ' . escapeshellarg($fakePrivKey) . ' 2048 2>/dev/null');
    $forgedJwt = make_test_id_token($fakePrivKey, 'key-google-2026', $forgedClaims);
    @unlink($fakePrivKey);

    $transport->tokenResponses['https://oauth2.googleapis.com/token'] = [
        'access_token' => 'tok_attacker',
        'id_token' => $forgedJwt,
    ];

    $forgedSigBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $state, 'code_attacker', $transport, $state);
    } catch (Auth\OAuthError $e) {
        $forgedSigBlocked = ($e->reason === 'invalid_id_token_signature' && $e->statusCode === 403);
    }
    $assert($forgedSigBlocked, 'adversarial: forged ID-token signature rejected with 403');
    $pdo->prepare('UPDATE oauth_states SET used_at = NULL WHERE state = ?')->execute([$state]);

    // 7. Adversarial Test: OIDC Invalid Issuer
    $badIssuerClaims = $forgedClaims;
    $badIssuerClaims['iss'] = 'https://rogue-identity-provider.test';
    $badIssuerJwt = make_test_id_token($testPrivKey, 'key-google-2026', $badIssuerClaims);
    $transport->tokenResponses['https://oauth2.googleapis.com/token']['id_token'] = $badIssuerJwt;

    $badIssuerBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $state, 'code_bad_issuer', $transport, $state);
    } catch (Auth\OAuthError $e) {
        $badIssuerBlocked = ($e->reason === 'invalid_id_token_issuer' && $e->statusCode === 403);
    }
    $assert($badIssuerBlocked, 'adversarial: untrusted ID-token issuer rejected with 403');
    $pdo->prepare('UPDATE oauth_states SET used_at = NULL WHERE state = ?')->execute([$state]);

    // 8. Adversarial Test: OIDC Audience Mismatch
    $badAudClaims = $forgedClaims;
    $badAudClaims['aud'] = 'different-app-client-id-xyz';
    $badAudJwt = make_test_id_token($testPrivKey, 'key-google-2026', $badAudClaims);
    $transport->tokenResponses['https://oauth2.googleapis.com/token']['id_token'] = $badAudJwt;

    $badAudBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $state, 'code_bad_aud', $transport, $state);
    } catch (Auth\OAuthError $e) {
        $badAudBlocked = ($e->reason === 'invalid_id_token_audience' && $e->statusCode === 403);
    }
    $assert($badAudBlocked, 'adversarial: ID-token audience mismatch rejected with 403');
    $pdo->prepare('UPDATE oauth_states SET used_at = NULL WHERE state = ?')->execute([$state]);

    // 9. Adversarial Test: OIDC Expired Token
    $expiredClaims = $forgedClaims;
    $expiredClaims['exp'] = time() - 300; // 5 minutes ago
    $expiredJwt = make_test_id_token($testPrivKey, 'key-google-2026', $expiredClaims);
    $transport->tokenResponses['https://oauth2.googleapis.com/token']['id_token'] = $expiredJwt;

    $expiredBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $state, 'code_expired', $transport, $state);
    } catch (Auth\OAuthError $e) {
        $expiredBlocked = ($e->reason === 'id_token_expired' && $e->statusCode === 401);
    }
    $assert($expiredBlocked, 'adversarial: expired ID-token rejected with 401');
    $pdo->prepare('UPDATE oauth_states SET used_at = NULL WHERE state = ?')->execute([$state]);

    // 10. Adversarial Test: OIDC Nonce Mismatch
    $badNonceClaims = $forgedClaims;
    $badNonceClaims['nonce'] = 'wrong_nonce_value_1234';
    $badNonceJwt = make_test_id_token($testPrivKey, 'key-google-2026', $badNonceClaims);
    $transport->tokenResponses['https://oauth2.googleapis.com/token']['id_token'] = $badNonceJwt;

    $badNonceBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $state, 'code_bad_nonce', $transport, $state);
    } catch (Auth\OAuthError $e) {
        $badNonceBlocked = ($e->reason === 'invalid_id_token_nonce' && $e->statusCode === 403);
    }
    $assert($badNonceBlocked, 'adversarial: ID-token nonce mismatch rejected with 403');
    $pdo->prepare('UPDATE oauth_states SET used_at = NULL WHERE state = ?')->execute([$state]);

    // 11. Valid OIDC Registration & Student Role Enforcement (Privilege Escalation Gate)
    // Even if an attacker requested intended_role='company', OAuth MUST provision strictly as 'student'
    $pdo->prepare("UPDATE oauth_states SET intended_role = 'company' WHERE state = ?")->execute([$state]);

    $validClaims = [
        'sub' => 'google-sub-maya-1001',
        'email' => 'maya.lin@example.test',
        'email_verified' => true,
        'name' => 'Maya Lin',
        'aud' => 'test-google-client-id.apps.googleusercontent.com',
        'iss' => 'https://accounts.google.com',
        'exp' => time() + 3600,
        'iat' => time(),
        'nonce' => $nonce,
    ];
    $validJwt = make_test_id_token($testPrivKey, 'key-google-2026', $validClaims);
    $transport->tokenResponses['https://oauth2.googleapis.com/token']['id_token'] = $validJwt;

    $authResult = Auth\handle_oauth_callback($pdo, 'google', $state, 'code_valid_123', $transport, $state);
    $assert($authResult['is_new'] === true, 'valid OIDC ID-token successfully registers user');
    $assert($authResult['user']['email'] === 'maya.lin@example.test', 'provisioned email matches verified ID-token claim');
    $assert($authResult['user']['role'] === 'student', 'privilege escalation blocked: OAuth registration assigned strictly student role');
    $userId1 = (int) $authResult['user']['id'];

    // Verify locked password in DB
    $stmt = $pdo->prepare('SELECT password_hash, role FROM users WHERE id = ?');
    $stmt->execute([$userId1]);
    $uRow = $stmt->fetch();
    $assert(str_starts_with($uRow['password_hash'], '*LOCKED_OAUTH_'), 'provisioned user account has cryptographically locked password');
    $assert($uRow['role'] === 'student', 'database row enforces student role');

    // 12. Replay Protection: Consumed State Cannot Be Reused
    $replayBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $state, 'code_valid_123', $transport, $state);
    } catch (Auth\OAuthError $e) {
        $replayBlocked = ($e->reason === 'state_already_used' && $e->statusCode === 400);
    }
    $assert($replayBlocked, 'replay protection: previously consumed OAuth state rejected');

    // 13. Subsequent Login with Existing Bound Identity
    $loginRedirectUrl = Auth\initiate_oauth_flow($pdo, 'google', 'student');
    parse_str(parse_url($loginRedirectUrl)['query'] ?? '', $loginParams);

    $loginClaims = $validClaims;
    $loginClaims['nonce'] = $loginParams['nonce'];
    $loginJwt = make_test_id_token($testPrivKey, 'key-google-2026', $loginClaims);
    $transport->tokenResponses['https://oauth2.googleapis.com/token']['id_token'] = $loginJwt;

    $loginResult = Auth\handle_oauth_callback($pdo, 'google', $loginParams['state'], 'code_subsequent', $transport, $loginParams['state']);
    $assert($loginResult['is_new'] === false, 'subsequent login reports is_new=false');
    $assert((int) $loginResult['user']['id'] === $userId1, 'resolves strictly to the existing bound account ID');

    // 14. Anti-Account-Takeover Defense: Email Collision without Linking Verification
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute([
        'Existing Victim',
        'victim@university.test',
        password_hash('VictimPass123!@#', PASSWORD_BCRYPT),
        'student',
    ]);

    $takeoverUrl = Auth\initiate_oauth_flow($pdo, 'google', 'student');
    parse_str(parse_url($takeoverUrl)['query'] ?? '', $takeoverParams);

    $takeoverClaims = [
        'sub' => 'google-sub-attacker-666',
        'email' => 'victim@university.test',
        'email_verified' => true,
        'name' => 'Attacker Identity',
        'aud' => 'test-google-client-id.apps.googleusercontent.com',
        'iss' => 'https://accounts.google.com',
        'exp' => time() + 3600,
        'iat' => time(),
        'nonce' => $takeoverParams['nonce'],
    ];
    $takeoverJwt = make_test_id_token($testPrivKey, 'key-google-2026', $takeoverClaims);
    $transport->tokenResponses['https://oauth2.googleapis.com/token']['id_token'] = $takeoverJwt;

    $takeoverBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'google', $takeoverParams['state'], 'code_takeover', $transport, $takeoverParams['state']);
    } catch (Auth\OAuthError $e) {
        $takeoverBlocked = ($e->reason === 'account_linking_required' && $e->statusCode === 409);
    }
    $assert($takeoverBlocked, 'anti-takeover: social identity matching existing password account rejected with 409');

    // 15. GitHub OAuth: Verified Primary Email Required
    $ghUrl = Auth\initiate_oauth_flow($pdo, 'github', 'student', $userId1);
    parse_str(parse_url($ghUrl)['query'] ?? '', $ghParams);

    $transport->tokenResponses['https://github.com/login/oauth/access_token'] = ['access_token' => 'gh_tok_abc'];
    $transport->userInfoResponses['https://api.github.com/user'] = [
        'id' => 9912837,
        'login' => 'mayalin-gh',
        'name' => 'Maya Lin GitHub',
    ];

    // Adversarial: GitHub returns only unverified emails
    $transport->userInfoResponses['https://api.github.com/user/emails'] = [
        ['email' => 'unverified@attacker.test', 'primary' => true, 'verified' => false],
    ];

    $ghUnverifiedBlocked = false;
    try {
        Auth\handle_oauth_callback($pdo, 'github', $ghParams['state'], 'code_gh_unverified', $transport, $ghParams['state']);
    } catch (Auth\OAuthError $e) {
        $ghUnverifiedBlocked = ($e->reason === 'github_verified_email_required' && $e->statusCode === 403);
    }
    $assert($ghUnverifiedBlocked, 'adversarial: GitHub account with unverified email rejected with 403');

    // Legitimate GitHub with verified primary email
    $pdo->prepare('UPDATE oauth_states SET used_at = NULL WHERE state = ?')->execute([$ghParams['state']]);
    $transport->userInfoResponses['https://api.github.com/user/emails'] = [
        ['email' => 'maya.lin.verified@github.test', 'primary' => true, 'verified' => true],
    ];

    $ghLinkResult = Auth\handle_oauth_callback($pdo, 'github', $ghParams['state'], 'code_gh_verified', $transport, $ghParams['state']);
    $assert(!empty($ghLinkResult['linked']), 'authenticated GitHub linking succeeds with verified primary email');
    $assert((int) $ghLinkResult['user']['id'] === $userId1, 'bound to the authenticated user ID');

    // 16. Multiple Identities & Unlink Lockout Safety
    $identities = Auth\list_user_identities($pdo, $userId1);
    $assert(count($identities) === 2, 'user now has 2 bound identities (Google and GitHub)');

    // Unlinking 1 of 2 identities is allowed
    Auth\unlink_identity($pdo, $userId1, 'github');
    $remaining = Auth\list_user_identities($pdo, $userId1);
    $assert(count($remaining) === 1 && $remaining[0]['provider'] === 'google', 'unlinking secondary identity succeeds');

    // Unlinking sole authentication method blocked to prevent account lockout
    $lockoutBlocked = false;
    try {
        Auth\unlink_identity($pdo, $userId1, 'google');
    } catch (Auth\OAuthError $e) {
        $lockoutBlocked = ($e->reason === 'cannot_unlink_sole_authentication_method' && $e->statusCode === 400);
    }
    $assert($lockoutBlocked, 'lockout prevention: unlinking sole authentication method blocked');

    // User sets password, then unlinking is permitted
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([
        password_hash('NewUsablePassword456!@#', PASSWORD_BCRYPT),
        $userId1,
    ]);
    Auth\unlink_identity($pdo, $userId1, 'google');
    $finalIdentities = Auth\list_user_identities($pdo, $userId1);
    $assert(count($finalIdentities) === 0, 'unlinking last identity allowed once user has a valid password');

    // Cleanup temp resources
    @unlink($testPrivKey);
    @unlink($dbPath);

    echo "====================================================================\n";
    echo "RESULT: {$passed} passed, {$failed} failed\n";
    echo "====================================================================\n";

    if ($failed > 0) {
        exit(1);
    }
}

test_oauth_workflow();
