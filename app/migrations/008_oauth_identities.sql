-- Social Authentication & Identity Federation (Google OIDC, GitHub OAuth, LinkedIn OIDC)
CREATE TABLE oauth_identities (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    provider TEXT NOT NULL CHECK (provider IN ('google', 'github', 'linkedin')),
    provider_subject_id TEXT NOT NULL CHECK (length(provider_subject_id) BETWEEN 1 AND 255),
    provider_email TEXT COLLATE NOCASE CHECK (provider_email IS NULL OR length(provider_email) BETWEEN 3 AND 254),
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE(provider, provider_subject_id)
);

CREATE INDEX idx_oauth_user ON oauth_identities(user_id);
CREATE INDEX idx_oauth_provider_sub ON oauth_identities(provider, provider_subject_id);

-- Cryptographic OAuth state & PKCE verification storage
CREATE TABLE oauth_states (
    state TEXT PRIMARY KEY CHECK (length(state) = 64),
    nonce TEXT NOT NULL CHECK (length(nonce) >= 32),
    code_verifier TEXT CHECK (code_verifier IS NULL OR length(code_verifier) BETWEEN 43 AND 128),
    provider TEXT NOT NULL CHECK (provider IN ('google', 'github', 'linkedin')),
    intended_role TEXT NOT NULL DEFAULT 'student' CHECK (intended_role IN ('student', 'company', 'college')),
    linking_user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    expires_at INTEGER NOT NULL,
    used_at INTEGER CHECK (used_at IS NULL OR used_at > 0),
    created_at INTEGER NOT NULL
);

CREATE INDEX idx_oauth_states_exp ON oauth_states(expires_at);
