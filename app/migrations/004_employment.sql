-- Migration 004: Employment, Student Profiles, Job Postings, Applications, Resumes

-- Extend student_profiles with detailed career information
ALTER TABLE student_profiles ADD COLUMN skills TEXT NOT NULL DEFAULT '' CHECK (length(skills) <= 500);
ALTER TABLE student_profiles ADD COLUMN bio TEXT NOT NULL DEFAULT '' CHECK (length(bio) <= 2000);
ALTER TABLE student_profiles ADD COLUMN github_username TEXT NOT NULL DEFAULT '' CHECK (length(github_username) <= 39);
ALTER TABLE student_profiles ADD COLUMN college_org_id INTEGER REFERENCES organizations(id) ON DELETE SET NULL;
ALTER TABLE student_profiles ADD COLUMN updated_at INTEGER NOT NULL DEFAULT 0;

CREATE INDEX idx_student_profiles_college_org ON student_profiles(college_org_id);

-- Verified Company Jobs
CREATE TABLE jobs (
    id INTEGER PRIMARY KEY,
    organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE RESTRICT,
    created_by_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    title TEXT NOT NULL CHECK (length(title) BETWEEN 3 AND 140),
    description TEXT NOT NULL CHECK (length(description) BETWEEN 10 AND 5000),
    skills TEXT NOT NULL CHECK (length(skills) BETWEEN 1 AND 500),
    role_category TEXT NOT NULL CHECK (role_category IN ('frontend', 'backend', 'fullstack', 'general')),
    location TEXT NOT NULL DEFAULT 'Remote' CHECK (length(location) BETWEEN 2 AND 100),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('draft', 'active', 'closed')),
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE INDEX idx_jobs_org ON jobs(organization_id, status);
CREATE INDEX idx_jobs_status_id ON jobs(status, id DESC);
CREATE INDEX idx_jobs_role ON jobs(role_category, status);

-- Student Applications with Duplicate Prevention
CREATE TABLE applications (
    id INTEGER PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    job_id INTEGER NOT NULL REFERENCES jobs(id) ON DELETE RESTRICT,
    status TEXT NOT NULL DEFAULT 'applied' CHECK (status IN ('applied', 'shortlisted', 'rejected')),
    notes TEXT NOT NULL DEFAULT '' CHECK (length(notes) <= 1000),
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE(student_id, job_id)
);

CREATE INDEX idx_applications_student ON applications(student_id, id DESC);
CREATE INDEX idx_applications_job ON applications(job_id, status);

-- Resumes for Students with Evidence Integration
CREATE TABLE resumes (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title TEXT NOT NULL CHECK (length(title) BETWEEN 1 AND 100),
    summary TEXT NOT NULL DEFAULT '' CHECK (length(summary) <= 2000),
    education_json TEXT NOT NULL DEFAULT '[]' CHECK (length(education_json) <= 8192),
    experience_json TEXT NOT NULL DEFAULT '[]' CHECK (length(experience_json) <= 8192),
    projects_json TEXT NOT NULL DEFAULT '[]' CHECK (length(projects_json) <= 8192),
    skills_json TEXT NOT NULL DEFAULT '[]' CHECK (length(skills_json) <= 4096),
    assessment_id INTEGER REFERENCES assessments(id) ON DELETE SET NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE INDEX idx_resumes_user ON resumes(user_id);
