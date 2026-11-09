-- Migration 006: Student-College Affiliation Request Workflow, Granular Privacy Consent, Achievements, and Access Audit Logging

-- 1. College Affiliation Requests
CREATE TABLE college_affiliation_requests (
    id INTEGER PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    college_org_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    enrollment_number TEXT NOT NULL CHECK (length(enrollment_number) BETWEEN 2 AND 50),
    degree TEXT NOT NULL CHECK (length(degree) BETWEEN 2 AND 100),
    department TEXT NOT NULL CHECK (length(department) BETWEEN 2 AND 100),
    graduation_year INTEGER NOT NULL CHECK (graduation_year BETWEEN 2000 AND 2100),
    verification_note TEXT NOT NULL DEFAULT '' CHECK (length(verification_note) <= 1000),
    status TEXT NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED', 'REVOKED')),
    reviewed_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    review_decision_reason TEXT NOT NULL DEFAULT '' CHECK (length(review_decision_reason) <= 1000),
    reviewed_at INTEGER,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE INDEX idx_affiliation_requests_college ON college_affiliation_requests(college_org_id, status);
CREATE INDEX idx_affiliation_requests_student ON college_affiliation_requests(student_id, status);
CREATE UNIQUE INDEX idx_affiliation_unique_pending ON college_affiliation_requests(student_id, college_org_id)
    WHERE status = 'PENDING';

-- 2. Extend student_profiles with granular sharing permissions and talent discovery
ALTER TABLE student_profiles ADD COLUMN share_contact_details INTEGER NOT NULL DEFAULT 1 CHECK (share_contact_details IN (0, 1));
ALTER TABLE student_profiles ADD COLUMN share_resume INTEGER NOT NULL DEFAULT 1 CHECK (share_resume IN (0, 1));
ALTER TABLE student_profiles ADD COLUMN share_achievements INTEGER NOT NULL DEFAULT 1 CHECK (share_achievements IN (0, 1));
ALTER TABLE student_profiles ADD COLUMN share_applications INTEGER NOT NULL DEFAULT 1 CHECK (share_applications IN (0, 1));
ALTER TABLE student_profiles ADD COLUMN talent_discovery_opt_in INTEGER NOT NULL DEFAULT 0 CHECK (talent_discovery_opt_in IN (0, 1));
ALTER TABLE student_profiles ADD COLUMN enrollment_number TEXT NOT NULL DEFAULT '' CHECK (length(enrollment_number) <= 50);
ALTER TABLE student_profiles ADD COLUMN degree TEXT NOT NULL DEFAULT '' CHECK (length(degree) <= 100);
ALTER TABLE student_profiles ADD COLUMN department TEXT NOT NULL DEFAULT '' CHECK (length(department) <= 100);
ALTER TABLE student_profiles ADD COLUMN graduation_year INTEGER CHECK (graduation_year IS NULL OR graduation_year BETWEEN 2000 AND 2100);
ALTER TABLE student_profiles ADD COLUMN affiliation_status TEXT NOT NULL DEFAULT 'NONE' CHECK (affiliation_status IN ('NONE', 'PENDING', 'AFFILIATED', 'REVOKED'));

-- 3. Structured Student Achievements & Credentials
CREATE TABLE student_achievements (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category TEXT NOT NULL CHECK (category IN ('certification', 'hackathon', 'publication', 'award', 'project', 'academic')),
    title TEXT NOT NULL CHECK (length(title) BETWEEN 2 AND 150),
    issuer TEXT NOT NULL DEFAULT '' CHECK (length(issuer) <= 150),
    issue_date TEXT NOT NULL DEFAULT '' CHECK (length(issue_date) <= 30),
    credential_url TEXT NOT NULL DEFAULT '' CHECK (length(credential_url) <= 500),
    description TEXT NOT NULL DEFAULT '' CHECK (length(description) <= 1000),
    evidence_assessment_id INTEGER REFERENCES assessments(id) ON DELETE SET NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE INDEX idx_student_achievements_user ON student_achievements(user_id, category);

-- 4. Sensitive Record Access Audit Logs
CREATE TABLE sensitive_record_access_logs (
    id INTEGER PRIMARY KEY,
    accessor_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    accessor_role TEXT NOT NULL CHECK (accessor_role IN ('college', 'company', 'admin')),
    accessor_org_id INTEGER REFERENCES organizations(id) ON DELETE SET NULL,
    student_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    record_type TEXT NOT NULL CHECK (record_type IN ('profile', 'resume', 'achievement', 'application', 'placement_history')),
    record_id INTEGER,
    access_context TEXT NOT NULL DEFAULT '' CHECK (length(access_context) <= 255),
    granted INTEGER NOT NULL CHECK (granted IN (0, 1)),
    accessed_at INTEGER NOT NULL
);

CREATE INDEX idx_access_logs_student ON sensitive_record_access_logs(student_id, accessed_at DESC);
CREATE INDEX idx_access_logs_accessor ON sensitive_record_access_logs(accessor_user_id, accessed_at DESC);
