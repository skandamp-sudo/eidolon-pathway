CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL CHECK (length(name) BETWEEN 1 AND 100),
    email TEXT NOT NULL COLLATE NOCASE UNIQUE CHECK (length(email) BETWEEN 3 AND 254),
    password_hash TEXT NOT NULL CHECK (length(password_hash) BETWEEN 60 AND 255),
    role TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('student', 'company', 'admin')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE student_profiles (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE RESTRICT,
    college TEXT NOT NULL DEFAULT '' CHECK (length(college) <= 200)
);

CREATE TABLE auth_throttle (
    bucket TEXT PRIMARY KEY CHECK (length(bucket) = 64),
    attempts INTEGER NOT NULL CHECK (attempts BETWEEN 1 AND 20),
    expires_at INTEGER NOT NULL
);
CREATE INDEX auth_throttle_expiry ON auth_throttle(expires_at);

CREATE TABLE schema_migrations (
    version INTEGER PRIMARY KEY,
    checksum TEXT NOT NULL CHECK (length(checksum) = 64),
    applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
