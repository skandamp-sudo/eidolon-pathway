-- Organization Access Requests: self-service registration for companies and colleges
CREATE TABLE organization_requests (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL CHECK (length(name) BETWEEN 2 AND 120),
    type TEXT NOT NULL CHECK (type IN ('COMPANY', 'COLLEGE')),
    website TEXT NOT NULL CHECK (length(website) BETWEEN 4 AND 200),
    contact_name TEXT NOT NULL CHECK (length(contact_name) BETWEEN 2 AND 100),
    contact_email TEXT NOT NULL COLLATE NOCASE CHECK (length(contact_email) BETWEEN 3 AND 254),
    designation TEXT NOT NULL CHECK (length(designation) BETWEEN 2 AND 100),
    verification_notes TEXT NOT NULL CHECK (length(verification_notes) BETWEEN 5 AND 2000),
    status TEXT NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED')),
    organization_id INTEGER REFERENCES organizations(id) ON DELETE SET NULL,
    reviewed_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    review_notes TEXT CHECK (review_notes IS NULL OR length(review_notes) <= 2000),
    ip_address TEXT NOT NULL DEFAULT '127.0.0.1',
    created_at INTEGER NOT NULL,
    reviewed_at INTEGER
);

CREATE INDEX idx_org_req_status ON organization_requests(status);
CREATE INDEX idx_org_req_email ON organization_requests(contact_email);
CREATE INDEX idx_org_req_created ON organization_requests(created_at DESC);

-- Extend organization_invitations with explicit revocation timestamp
ALTER TABLE organization_invitations ADD COLUMN revoked_at INTEGER;
