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
            // Some providers (older GitHub responses) might format as urlencoded
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
            throw new OAuthError('userinfo_fetch_failed', 502);
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
 */
function handle_oauth_callback(
    \PDO $pdo,
    string $provider,
    string $state,
    string $code,
    ?OAuthTransport $transport = null
): array {
    $config = get_provider_config($provider);
    if (!$config) {
        throw new OAuthError('provider_not_configured', 400);
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

        // Consume state immediately
        $stmt = $pdo->prepare('UPDATE oauth_states SET used_at = ? WHERE state = ?');
        $stmt->execute([$now, $state]);

        // 2. Exchange authorization code for access token with PKCE verification
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

        // 3. Fetch verified userinfo from provider endpoint
        $authHeaders = ['Authorization: Bearer ' . $accessToken];
        $userInfo = $transport->get($config['userinfo_endpoint'], $authHeaders);

        // Normalize subject identifier and verified email across providers
        $subjectId = null;
        $email = null;
        $name = null;

        if ($provider === 'google' || $provider === 'linkedin') {
            $subjectId = (string) ($userInfo['sub'] ?? '');
            $email = isset($userInfo['email']) ? strtolower(trim((string) $userInfo['email'])) : null;
            $name = trim((string) ($userInfo['name'] ?? $userInfo['given_name'] ?? 'Social User'));
            $emailVerified = (bool) ($userInfo['email_verified'] ?? false);
            if (!$emailVerified && $email !== null) {
                throw new OAuthError('email_not_verified_by_provider', 403);
            }
        } elseif ($provider === 'github') {
            $subjectId = (string) ($userInfo['id'] ?? '');
            $name = trim((string) ($userInfo['name'] ?? $userInfo['login'] ?? 'GitHub Developer'));
            $email = isset($userInfo['email']) ? strtolower(trim((string) $userInfo['email'])) : null;

            // If GitHub email is private on user endpoint, fetch verified emails from emails endpoint
            if (!$email && !empty($config['userinfo_emails_endpoint'])) {
                try {
                    $emailsList = $transport->get($config['userinfo_emails_endpoint'], $authHeaders);
                    if (is_array($emailsList)) {
                        foreach ($emailsList as $em) {
                            if (!empty($em['verified']) && !empty($em['primary']) && !empty($em['email'])) {
                                $email = strtolower(trim((string) $em['email']));
                                break;
                            }
                        }
                    }
                } catch (\Throwable) {
                    // Fall back to public profile email if emails list fails
                }
            }
        }

        if ($subjectId === '' || $email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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

        // 5. Account Linking: if session user was explicitly linking an account
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

        // 6. Account Linking Protection:
        // NEVER link accounts purely by email similarity without credential confirmation!
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new OAuthError('account_linking_required', 409);
        }

        // 7. Auto-Provisioning New Student Account
        $safeName = $name !== '' ? substr($name, 0, 100) : 'Verified Student';
        $lockedHash = '*LOCKED_OAUTH_' . bin2hex(random_bytes(32));
        $role = $oauthState['intended_role'] ?: 'student';

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
