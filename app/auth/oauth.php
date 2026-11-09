<?php
declare(strict_types=1);

namespace Pathway\Auth;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../organizations/organization.php';

use Pathway\HttpError;
use Pathway\Organizations;

final class OAuthError extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $statusCode = 400)
    {
        parent::__construct($reason, $statusCode);
    }
}

interface OAuthTransport
{
    public function post(string $url, array $params, array $headers = []): array;
    public function get(string $url, array $headers = []): array;
}

final class CurlOAuthTransport implements OAuthTransport
{
    public function post(string $url, array $params, array $headers = []): array
    {
        $ch = curl_init($url);
        $payload = http_build_query($params);
        $reqHeaders = array_merge(['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'], $headers);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $reqHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            throw new OAuthError('token_exchange_failed', 502);
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            parse_str((string) $raw, $json);
        }
        return is_array($json) ? $json : [];
    }

    public function get(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        $reqHeaders = array_merge(['Accept: application/json', 'User-Agent: EidolonPathway/1.0'], $headers);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $reqHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            throw new OAuthError('endpoint_fetch_failed', 502);
        }

        $json = json_decode((string) $raw, true);
        return is_array($json) ? $json : [];
    }
}

/**
 * Base64URL encoding without padding according to RFC 7636 (PKCE).
 */
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64URL decoding with padding restoration.
 */
function base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return (string) base64_decode(strtr($data, '-_', '+/'));
}

/**
 * ASN.1 DER length encoding helper.
 */
function der_length(int $len): string
{
    if ($len < 128) {
        return chr($len);
    }
    $bytes = '';
    while ($len > 0) {
        $bytes = chr($len & 0xFF) . $bytes;
        $len >>= 8;
    }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

/**
 * ASN.1 DER integer encoding helper.
 */
function der_integer(string $bytes): string
{
    if ($bytes === '') {
        return "\x02\x01\x00";
    }
    if (ord($bytes[0]) > 0x7F) {
        $bytes = "\x00" . $bytes;
    }
    return "\x02" . der_length(strlen($bytes)) . $bytes;
}

/**
 * ASN.1 DER sequence encoding helper.
 */
function der_sequence(string $contents): string
{
    return "\x30" . der_length(strlen($contents)) . $contents;
}

/**
 * Convert an RSA JWK public key (modulus 'n', exponent 'e') to standard SubjectPublicKeyInfo X.509 PEM.
 */
function jwk_to_pem(string $n_b64u, string $e_b64u): ?string
{
    $n = base64url_decode($n_b64u);
    $e = base64url_decode($e_b64u);
    if ($n === '' || $e === '') {
        return null;
    }

    $rsaPub = der_sequence(der_integer($n) . der_integer($e));
    $rsaOid = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // rsaEncryption, NULL
    $bitString = "\x03" . der_length(strlen($rsaPub) + 1) . "\x00" . $rsaPub;
    $spki = der_sequence($rsaOid . $bitString);
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * Verify RS256 signature using OpenSSL CLI without requiring custom PHP openssl extensions.
 */
function verify_rsa_sha256_signature(string $data, string $rawSignature, array $jwk): bool
{
    $n = $jwk['n'] ?? null;
    $e = $jwk['e'] ?? null;
    if (!is_string($n) || !is_string($e)) {
        return false;
    }
    $pem = jwk_to_pem($n, $e);
    if (!$pem) {
        return false;
    }

    $pubPath = tempnam(sys_get_temp_dir(), 'pub_');
    $dataPath = tempnam(sys_get_temp_dir(), 'data_');
    $sigPath = tempnam(sys_get_temp_dir(), 'sig_');
    if ($pubPath === false || $dataPath === false || $sigPath === false) {
        return false;
    }

    try {
        file_put_contents($pubPath, $pem);
        file_put_contents($dataPath, $data);
        file_put_contents($sigPath, $rawSignature);

        $cmd = 'openssl dgst -sha256 -verify ' . escapeshellarg($pubPath) . ' -signature ' . escapeshellarg($sigPath) . ' ' . escapeshellarg($dataPath) . ' 2>/dev/null';
        $out = [];
        $exitCode = 1;
        exec($cmd, $out, $exitCode);
        return $exitCode === 0;
    } finally {
        @unlink($pubPath);
        @unlink($dataPath);
        @unlink($sigPath);
    }
}

/**
 * Retrieve configuration for specified provider from environment.
 * Fails closed (returns null) if mandatory credentials are not present.
 */
function get_provider_config(string $provider): ?array
{
    $providerLower = strtolower(trim($provider));
    return match ($providerLower) {
        'google' => (function() {
            $clientId = getenv('GOOGLE_CLIENT_ID');
            $clientSecret = getenv('GOOGLE_CLIENT_SECRET');
            if (!$clientId || !$clientSecret) return null;
            return [
                'provider' => 'google',
                'name' => 'Google',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: 'http://127.0.0.1:8080/auth/callback/google',
                'auth_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_endpoint' => 'https://oauth2.googleapis.com/token',
                'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
                'jwks_endpoint' => getenv('GOOGLE_JWKS_ENDPOINT') ?: 'https://www.googleapis.com/oauth2/v3/certs',
                'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
                'scopes' => ['openid', 'email', 'profile'],
            ];
        })(),
        'github' => (function() {
            $clientId = getenv('GITHUB_CLIENT_ID');
            $clientSecret = getenv('GITHUB_CLIENT_SECRET');
            if (!$clientId || !$clientSecret) return null;
            return [
                'provider' => 'github',
                'name' => 'GitHub',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => getenv('GITHUB_REDIRECT_URI') ?: 'http://127.0.0.1:8080/auth/callback/github',
                'auth_endpoint' => 'https://github.com/login/oauth/authorize',
                'token_endpoint' => 'https://github.com/login/oauth/access_token',
                'userinfo_endpoint' => 'https://api.github.com/user',
                'userinfo_emails_endpoint' => 'https://api.github.com/user/emails',
                'scopes' => ['read:user', 'user:email'],
            ];
        })(),
        'linkedin' => (function() {
            $clientId = getenv('LINKEDIN_CLIENT_ID');
            $clientSecret = getenv('LINKEDIN_CLIENT_SECRET');
            if (!$clientId || !$clientSecret) return null;
            return [
                'provider' => 'linkedin',
                'name' => 'LinkedIn',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => getenv('LINKEDIN_REDIRECT_URI') ?: 'http://127.0.0.1:8080/auth/callback/linkedin',
                'auth_endpoint' => 'https://www.linkedin.com/oauth/v2/authorization',
                'token_endpoint' => 'https://www.linkedin.com/oauth/v2/accessToken',
                'userinfo_endpoint' => 'https://api.linkedin.com/v2/userinfo',
                'jwks_endpoint' => getenv('LINKEDIN_JWKS_ENDPOINT') ?: 'https://www.linkedin.com/oauth/openid/jwks',
                'issuers' => ['https://www.linkedin.com/oauth', 'https://www.linkedin.com'],
                'scopes' => ['openid', 'profile', 'email'],
            ];
        })(),
        default => null,
    };
}

/**
 * Check if a provider has active credentials configured in environment.
 */
function is_provider_configured(string $provider): bool
{
    return get_provider_config($provider) !== null;
}

/**
 * List all configured, active OAuth providers.
 */
function get_configured_providers(): array
{
    $list = [];
    foreach (['google', 'github', 'linkedin'] as $p) {
        $cfg = get_provider_config($p);
        if ($cfg !== null) {
            $list[$p] = $cfg['name'];
        }
    }
    return $list;
}

/**
 * Parse and cryptographically validate an OpenID Connect ID Token JWT.
 * Verifies algorithm, trusted issuer, audience, expiration, issued-at, nonce, and JWKS signature.
 */
function validate_oidc_id_token(
    string $idToken,
    array $config,
    string $expectedNonce,
    OAuthTransport $transport
): array {
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        throw new OAuthError('malformed_id_token', 400);
    }

    // 1. Validate Header
    $headerJson = base64url_decode($parts[0]);
    $header = json_decode($headerJson, true);
    if (!is_array($header) || ($header['alg'] ?? '') !== 'RS256') {
        throw new OAuthError('unsupported_id_token_algorithm', 400);
    }

    // 2. Validate Payload
    $payloadJson = base64url_decode($parts[1]);
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        throw new OAuthError('malformed_id_token_payload', 400);
    }

    // 3. Verify Issuer
    $iss = (string) ($payload['iss'] ?? '');
    $allowedIssuers = $config['issuers'] ?? [];
    if (!in_array($iss, $allowedIssuers, true)) {
        throw new OAuthError('invalid_id_token_issuer', 403);
    }

    // 4. Verify Audience
    $aud = $payload['aud'] ?? '';
    $validAud = is_array($aud) ? in_array($config['client_id'], $aud, true) : ($aud === $config['client_id']);
    if (!$validAud) {
        throw new OAuthError('invalid_id_token_audience', 403);
    }

    // 5. Verify Expiration & Issued At
    $now = time();
    $exp = (int) ($payload['exp'] ?? 0);
    if ($exp < ($now - 60)) {
        throw new OAuthError('id_token_expired', 401);
    }

    $iat = (int) ($payload['iat'] ?? 0);
    if ($iat > ($now + 60)) {
        throw new OAuthError('id_token_issued_in_future', 401);
    }

    // 6. Verify Nonce
    $tokenNonce = (string) ($payload['nonce'] ?? '');
    if ($tokenNonce === '' || !hash_equals($expectedNonce, $tokenNonce)) {
        throw new OAuthError('invalid_id_token_nonce', 403);
    }

    // 7. Verify Signature via Provider JWKS
    $jwksUrl = $config['jwks_endpoint'] ?? null;
    if (!$jwksUrl) {
        throw new OAuthError('jwks_endpoint_not_configured', 500);
    }

    $jwks = $transport->get($jwksUrl);
    $keys = $jwks['keys'] ?? [];
    if (!is_array($keys) || empty($keys)) {
        throw new OAuthError('jwks_fetch_failed', 502);
    }

    $kid = $header['kid'] ?? null;
    $matchingKey = null;
    if ($kid !== null) {
        foreach ($keys as $k) {
            if (($k['kid'] ?? '') === $kid) {
                $matchingKey = $k;
                break;
            }
        }
    }
    if (!$matchingKey) {
        foreach ($keys as $k) {
            if (($k['kty'] ?? '') === 'RSA') {
                $matchingKey = $k;
                break;
            }
        }
    }

    if (!$matchingKey) {
        throw new OAuthError('jwk_key_not_found', 403);
    }

    $rawSignature = base64url_decode($parts[2]);
    $dataToVerify = $parts[0] . '.' . $parts[1];

    if (!verify_rsa_sha256_signature($dataToVerify, $rawSignature, $matchingKey)) {
        throw new OAuthError('invalid_id_token_signature', 403);
    }

    return $payload;
}

/**
 * Initiate an OAuth 2.0 / OIDC authorization flow with PKCE and state protection.
 *
 * @return string Authorization redirect URL
 */
function initiate_oauth_flow(
    \PDO $pdo,
    string $provider,
    string $intendedRole = 'student',
    ?int $linkingUserId = null
): string {
    $config = get_provider_config($provider);
    if (!$config) {
        throw new OAuthError('provider_not_configured', 400);
    }

    if (!in_array($intendedRole, ['student', 'company', 'college'], true)) {
        throw new OAuthError('invalid_role', 400);
    }

    // High-entropy 256-bit CSPRNG state and nonce
    $state = bin2hex(random_bytes(32)); // 64 hex characters
    $nonce = bin2hex(random_bytes(16)); // 32 hex characters

    // RFC 7636 PKCE: 32 random bytes -> 43 base64url characters
    $verifierBytes = random_bytes(32);
    $codeVerifier = base64url_encode($verifierBytes);
    $codeChallenge = base64url_encode(hash('sha256', $codeVerifier, true));

    $now = time();
    $expiresAt = $now + 600; // 10 minutes

    $stmt = $pdo->prepare('
        INSERT INTO oauth_states (
            state, nonce, code_verifier, provider, intended_role, linking_user_id,
            expires_at, used_at, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)
    ');
    $stmt->execute([
        $state, $nonce, $codeVerifier, $provider, $intendedRole, $linkingUserId,
        $expiresAt, $now
    ]);

    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => implode(' ', $config['scopes']),
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
    ];

    if ($provider === 'google') {
        $params['access_type'] = 'online';
        $params['prompt'] = 'select_account';
    }

    return $config['auth_endpoint'] . '?' . http_build_query($params);
}

/**
 * Exchange code and authenticate user via verified identity claims.
 * Enforces session binding, OIDC ID-token cryptographic verification,
 * verified email verification, anti-account-takeover defense, and student-only auto-provisioning.
 */
function handle_oauth_callback(
    \PDO $pdo,
    string $provider,
    string $state,
    string $code,
    ?OAuthTransport $transport = null,
    ?string $sessionState = null
): array {
    $config = get_provider_config($provider);
    if (!$config) {
        throw new OAuthError('provider_not_configured', 400);
    }

    // 0. Session Binding Defense against Login CSRF:
    if ($sessionState !== null && !hash_equals($sessionState, $state)) {
        throw new OAuthError('login_csrf_detected', 403);
    }

    $now = time();
    $transport = $transport ?: new CurlOAuthTransport();

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        // 1. Validate state parameter
        $stmt = $pdo->prepare('SELECT * FROM oauth_states WHERE state = ? AND provider = ?');
        $stmt->execute([$state, $provider]);
        $oauthState = $stmt->fetch();

        if (!$oauthState) {
            throw new OAuthError('invalid_state', 400);
        }
        if ($oauthState['used_at'] !== null) {
            throw new OAuthError('state_already_used', 400);
        }
        if ($oauthState['expires_at'] < $now) {
            throw new OAuthError('state_expired', 400);
        }

        // Consume state immediately (replay protection)
        $stmt = $pdo->prepare('UPDATE oauth_states SET used_at = ? WHERE state = ?');
        $stmt->execute([$now, $state]);

        // 2. Exchange authorization code for tokens with PKCE verification
        $tokenParams = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'redirect_uri' => $config['redirect_uri'],
            'grant_type' => 'authorization_code',
        ];
        if (!empty($oauthState['code_verifier'])) {
            $tokenParams['code_verifier'] = $oauthState['code_verifier'];
        }

        $tokenResponse = $transport->post($config['token_endpoint'], $tokenParams);
        $accessToken = $tokenResponse['access_token'] ?? null;
        if (!is_string($accessToken) || trim($accessToken) === '') {
            throw new OAuthError('token_exchange_failed', 502);
        }

        // 3. Provider Identity Claims Extraction & Cryptographic Verification
        $subjectId = null;
        $email = null;
        $name = null;

        if ($provider === 'google' || $provider === 'linkedin') {
            // Enforce OIDC ID-Token Verification: signature, issuer, audience, expiration, nonce
            $idToken = $tokenResponse['id_token'] ?? null;
            if (!is_string($idToken) || trim($idToken) === '') {
                throw new OAuthError('id_token_missing', 400);
            }

            $idClaims = validate_oidc_id_token($idToken, $config, (string) $oauthState['nonce'], $transport);

            $subjectId = (string) ($idClaims['sub'] ?? '');
            $email = isset($idClaims['email']) ? strtolower(trim((string) $idClaims['email'])) : null;
            $name = trim((string) ($idClaims['name'] ?? $idClaims['given_name'] ?? 'Social User'));
            $emailVerified = (bool) ($idClaims['email_verified'] ?? false);

            if (!$emailVerified || $email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new OAuthError('email_not_verified_by_provider', 403);
            }
        } elseif ($provider === 'github') {
            // GitHub OAuth 2.0: query /user and enforce verified primary email from /user/emails
            $authHeaders = ['Authorization: Bearer ' . $accessToken];
            $userInfo = $transport->get($config['userinfo_endpoint'], $authHeaders);

            $subjectId = (string) ($userInfo['id'] ?? '');
            $name = trim((string) ($userInfo['name'] ?? $userInfo['login'] ?? 'GitHub Developer'));

            // Query /user/emails strictly requiring primary verified email
            if (!empty($config['userinfo_emails_endpoint'])) {
                $emailsList = $transport->get($config['userinfo_emails_endpoint'], $authHeaders);
                if (is_array($emailsList)) {
                    foreach ($emailsList as $em) {
                        if (!empty($em['verified']) && !empty($em['primary']) && !empty($em['email'])) {
                            $email = strtolower(trim((string) $em['email']));
                            break;
                        }
                    }
                }
            }

            if ($subjectId === '' || $email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new OAuthError('github_verified_email_required', 403);
            }
        }

        if ($subjectId === '' || $email === null) {
            throw new OAuthError('invalid_provider_identity_claims', 422);
        }

        // 4. Identity Resolution: Check existing bound identity
        $stmt = $pdo->prepare('
            SELECT i.user_id, u.id, u.name, u.email, u.role
            FROM oauth_identities i
            JOIN users u ON u.id = i.user_id
            WHERE i.provider = ? AND i.provider_subject_id = ?
        ');
        $stmt->execute([$provider, $subjectId]);
        $existingIdentity = $stmt->fetch();

        if ($existingIdentity) {
            // Found bound identity: update provider email and timestamp
            $stmt = $pdo->prepare('UPDATE oauth_identities SET provider_email = ?, updated_at = ? WHERE provider = ? AND provider_subject_id = ?');
            $stmt->execute([$email, $now, $provider, $subjectId]);

            Organizations\record_audit($pdo, (int) $existingIdentity['id'], 'oauth.login', 'user', (int) $existingIdentity['id'], [
                'provider' => $provider,
                'subject_id' => $subjectId,
            ]);

            $pdo->exec('COMMIT');
            return [
                'user' => [
                    'id' => (int) $existingIdentity['id'],
                    'name' => $existingIdentity['name'],
                    'email' => $existingIdentity['email'],
                    'role' => $existingIdentity['role'],
                ],
                'is_new' => false,
            ];
        }

        // 5. Account Linking: session user explicitly linking an identity
        $linkingUserId = $oauthState['linking_user_id'] ? (int) $oauthState['linking_user_id'] : null;
        if ($linkingUserId !== null) {
            $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
            $stmt->execute([$linkingUserId]);
            $linkingUser = $stmt->fetch();
            if (!$linkingUser) {
                throw new OAuthError('user_not_found', 404);
            }

            $stmt = $pdo->prepare('
                INSERT INTO oauth_identities (user_id, provider, provider_subject_id, provider_email, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$linkingUserId, $provider, $subjectId, $email, $now, $now]);

            Organizations\record_audit($pdo, $linkingUserId, 'oauth.link_identity', 'user', $linkingUserId, [
                'provider' => $provider,
                'subject_id' => $subjectId,
                'email' => $email,
            ]);

            $pdo->exec('COMMIT');
            return [
                'user' => $linkingUser,
                'is_new' => false,
                'linked' => true,
            ];
        }

        // 6. Anti-Account-Takeover Defense:
        // NEVER auto-link accounts by email collision without prior authenticated session verification!
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new OAuthError('account_linking_required', 409);
        }

        // 7. Auto-Provisioning New Student Account:
        // SECURITY RELEASE GATE: Self-service OAuth signup CANNOT grant privileged company or college roles.
        // Privileged roles require explicit institutional approval and controlled invitations.
        $role = 'student';
        $safeName = $name !== '' ? substr($name, 0, 100) : 'Verified Student';
        $lockedHash = '*LOCKED_OAUTH_' . bin2hex(random_bytes(32));

        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$safeName, $email, $lockedHash, $role]);
        $newUserId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('
            INSERT INTO oauth_identities (user_id, provider, provider_subject_id, provider_email, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$newUserId, $provider, $subjectId, $email, $now, $now]);

        Organizations\record_audit($pdo, $newUserId, 'oauth.register', 'user', $newUserId, [
            'provider' => $provider,
            'subject_id' => $subjectId,
            'email' => $email,
            'role' => $role,
        ]);

        $pdo->exec('COMMIT');

        return [
            'user' => [
                'id' => $newUserId,
                'name' => $safeName,
                'email' => $email,
                'role' => $role,
            ],
            'is_new' => true,
        ];
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

/**
 * List all federated OAuth identities bound to a user account.
 */
function list_user_identities(\PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT id, provider, provider_subject_id, provider_email, created_at, updated_at
        FROM oauth_identities
        WHERE user_id = ?
        ORDER BY id ASC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Unlink a federated OAuth identity with safety check preventing account lockout.
 */
function unlink_identity(\PDO $pdo, int $userId, string $provider): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new OAuthError('user_not_found', 404);
        }

        $identities = list_user_identities($pdo, $userId);
        $hasUsablePassword = !str_starts_with((string) $user['password_hash'], '*LOCKED_OAUTH_');
        $hasOtherOAuth = count($identities) > 1;

        if (!$hasUsablePassword && !$hasOtherOAuth) {
            throw new OAuthError('cannot_unlink_sole_authentication_method', 400);
        }

        $stmt = $pdo->prepare('DELETE FROM oauth_identities WHERE user_id = ? AND provider = ?');
        $stmt->execute([$userId, $provider]);

        Organizations\record_audit($pdo, $userId, 'oauth.unlink_identity', 'user', $userId, [
            'provider' => $provider,
        ]);

        $pdo->exec('COMMIT');
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}
