-- Organizations: companies and colleges
CREATE TABLE organizations (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL CHECK (length(name) BETWEEN 2 AND 120),
    slug TEXT NOT NULL COLLATE NOCASE UNIQUE CHECK (length(slug) BETWEEN 2 AND 60),
    type TEXT NOT NULL CHECK (type IN ('COMPANY', 'COLLEGE')),
    status TEXT NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'ACTIVE', 'SUSPENDED')),
    created_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE INDEX idx_org_status ON organizations(status);
CREATE INDEX idx_org_type ON organizations(type);

-- Organization Memberships: explicit roles and permissions per organization
CREATE TABLE organization_memberships (
    id INTEGER PRIMARY KEY,
    organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role TEXT NOT NULL CHECK (role IN ('OWNER', 'ADMIN', 'STAFF', 'REVIEWER')),
    status TEXT NOT NULL DEFAULT 'ACTIVE' CHECK (status IN ('ACTIVE', 'SUSPENDED')),
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE(organization_id, user_id)
);

CREATE INDEX idx_membership_user ON organization_memberships(user_id);
CREATE INDEX idx_membership_org ON organization_memberships(organization_id);

-- Organization Invitations: single-use, expiring, stored as SHA-256 hashes
CREATE TABLE organization_invitations (
    id INTEGER PRIMARY KEY,
    organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    email TEXT NOT NULL COLLATE NOCASE CHECK (length(email) BETWEEN 3 AND 254),
    role TEXT NOT NULL CHECK (role IN ('OWNER', 'ADMIN', 'STAFF', 'REVIEWER')),
    token_hash TEXT NOT NULL UNIQUE CHECK (length(token_hash) = 64),
    invited_by_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    expires_at INTEGER NOT NULL,
    used_at INTEGER CHECK (used_at IS NULL OR used_at > 0),
    created_at INTEGER NOT NULL
);

CREATE INDEX idx_invitations_token ON organization_invitations(token_hash);
CREATE INDEX idx_invitations_org ON organization_invitations(organization_id);
CREATE INDEX idx_invitations_pending ON organization_invitations(email, organization_id) WHERE used_at IS NULL;

-- Admin Audit Events: immutable log of all administrative and security actions
CREATE TABLE admin_audit_log (
    id INTEGER PRIMARY KEY,
    actor_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL CHECK (length(action) BETWEEN 3 AND 80),
    target_type TEXT NOT NULL CHECK (length(target_type) <= 40),
    target_id INTEGER,
    metadata_json TEXT NOT NULL DEFAULT '{}' CHECK (length(metadata_json) <= 4096),
    ip_address TEXT NOT NULL DEFAULT '127.0.0.1',
    created_at INTEGER NOT NULL
);

CREATE INDEX idx_audit_created ON admin_audit_log(created_at DESC);
CREATE INDEX idx_audit_actor ON admin_audit_log(actor_user_id);
