-- Migration 005: College Portal Support, Placement Staff Roles, and Student Consent

-- Recreate users table to allow 'college' in the role CHECK constraint
PRAGMA foreign_keys = OFF;

CREATE TABLE users_v5 (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL CHECK (length(name) BETWEEN 1 AND 100),
    email TEXT NOT NULL COLLATE NOCASE UNIQUE CHECK (length(email) BETWEEN 3 AND 254),
    password_hash TEXT NOT NULL CHECK (length(password_hash) BETWEEN 60 AND 255),
    role TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('student', 'company', 'college', 'admin')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users_v5 (id, name, email, password_hash, role, created_at)
SELECT id, name, email, password_hash, role, created_at FROM users;

DROP TABLE users;
ALTER TABLE users_v5 RENAME TO users;

CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

PRAGMA foreign_keys = ON;

-- Explicit student consent for sharing placement records & applications with associated college
ALTER TABLE student_profiles ADD COLUMN college_share_consent INTEGER NOT NULL DEFAULT 0 CHECK (college_share_consent IN (0, 1));
