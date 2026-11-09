CREATE TABLE assessments (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    repository TEXT NOT NULL CHECK (length(repository) BETWEEN 3 AND 140),
    target_role TEXT NOT NULL CHECK (target_role IN ('frontend', 'backend', 'fullstack')),
    rubric_version TEXT NOT NULL,
    state TEXT NOT NULL CHECK (state IN ('queued','planning','inspecting','evaluating','prioritizing','persisting','completed','failed')),
    commit_sha TEXT CHECK (commit_sha IS NULL OR length(commit_sha) = 40),
    plan_json TEXT CHECK (plan_json IS NULL OR length(plan_json) <= 32768),
    events_json TEXT NOT NULL DEFAULT '[]' CHECK (length(events_json) <= 16384),
    result_json TEXT CHECK (result_json IS NULL OR length(result_json) <= 65536),
    error_code TEXT,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);
CREATE INDEX assessment_history ON assessments(user_id, id DESC);
CREATE INDEX assessment_queue ON assessments(state, id);
CREATE UNIQUE INDEX one_active_assessment_per_student ON assessments(user_id)
    WHERE state IN ('queued','planning','inspecting','evaluating','prioritizing','persisting');

CREATE TABLE improvement_tasks (
    id INTEGER PRIMARY KEY,
    assessment_id INTEGER NOT NULL REFERENCES assessments(id) ON DELETE RESTRICT,
    priority INTEGER NOT NULL CHECK (priority BETWEEN 1 AND 3),
    finding_id TEXT NOT NULL,
    title TEXT NOT NULL CHECK (length(title) <= 300),
    acceptance TEXT NOT NULL CHECK (length(acceptance) <= 1000),
    UNIQUE(assessment_id, priority)
);

CREATE TABLE evidence_budget (
    singleton INTEGER PRIMARY KEY CHECK (singleton = 1),
    window_started INTEGER NOT NULL,
    requests INTEGER NOT NULL CHECK (requests BETWEEN 0 AND 40),
    blocked_until INTEGER NOT NULL DEFAULT 0
);
INSERT INTO evidence_budget VALUES (1, 0, 0, 0);
