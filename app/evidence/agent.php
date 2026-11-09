<?php
declare(strict_types=1);
namespace Pathway\Evidence;

require_once __DIR__ . '/github.php';
require_once __DIR__ . '/rubric.php';

/**
 * Bounded GitHub evidence agent.
 *
 * Orchestrates: submit → pin → plan → inspect → evaluate → prioritize → persist.
 * Never executes downloaded code. All citations are commit-pinned.
 */

function reserve_budget(\PDO $pdo): void
{
    $now = time();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $row = $pdo->query('SELECT window_started, requests, blocked_until FROM evidence_budget WHERE singleton = 1')->fetch();
        if (!$row) {
            throw new EvidenceError('budget_unavailable');
        }
        if ($row['blocked_until'] > $now) {
            throw new EvidenceError('rate_limited');
        }
        // 40 requests per rolling 15-minute window.
        if ($now - $row['window_started'] > 900) {
            $pdo->prepare('UPDATE evidence_budget SET window_started = ?, requests = 1, blocked_until = 0 WHERE singleton = 1')
                ->execute([$now]);
        } elseif ($row['requests'] >= 40) {
            throw new EvidenceError('rate_limited');
        } else {
            $pdo->exec('UPDATE evidence_budget SET requests = requests + 1 WHERE singleton = 1');
        }
        $pdo->exec('COMMIT');
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

function set_cooldown(\PDO $pdo, int $until): void
{
    $pdo->prepare('UPDATE evidence_budget SET blocked_until = MAX(blocked_until, ?) WHERE singleton = 1')
        ->execute([$until]);
}

function transition(\PDO $pdo, int $assessmentId, string $from, string $to, ?string $eventDetail = null): void
{
    $now = time();
    // Append event to the JSON log.
    $statement = $pdo->prepare(
        "UPDATE assessments SET state = ?, updated_at = ?,
         events_json = json_insert(events_json, '$[#]', json_object('state', ?, 'at', ?, 'detail', ?))
         WHERE id = ? AND state = ?"
    );
    $statement->execute([$to, $now, $to, $now, $eventDetail, $assessmentId, $from]);
    if ($statement->rowCount() !== 1) {
        throw new EvidenceError('assessment_state_conflict');
    }
}

function fail(\PDO $pdo, int $assessmentId, string $errorCode): void
{
    $now = time();
    $pdo->prepare(
        "UPDATE assessments SET state = 'failed', error_code = ?, updated_at = ?,
         events_json = json_insert(events_json, '$[#]', json_object('state', 'failed', 'at', ?, 'detail', ?))
         WHERE id = ? AND state NOT IN ('completed', 'failed')"
    )->execute([$errorCode, $now, $now, $errorCode, $assessmentId]);
}

/**
 * Create a queued assessment for an authenticated student.
 */
function queue(\PDO $pdo, int $userId, string $repositoryInput, string $roleInput): int
{
    $repository = repository($repositoryInput);
    $role = role($roleInput);

    // Enforce one active assessment per student (unique partial index).
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $existing = $pdo->prepare(
            "SELECT id FROM assessments WHERE user_id = ? AND state NOT IN ('completed', 'failed')"
        );
        $existing->execute([$userId]);
        if ($existing->fetch()) {
            throw new EvidenceError('assessment_already_active');
        }

        $now = time();
        $statement = $pdo->prepare(
            "INSERT INTO assessments (user_id, repository, target_role, rubric_version, state, events_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'queued', '[]', ?, ?)"
        );
        $statement->execute([$userId, $repository, $role, RUBRIC, $now, $now]);
        $id = (int) $pdo->lastInsertId();
        $pdo->exec('COMMIT');
        return $id;
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        if ($error instanceof \PDOException && ($error->errorInfo[1] ?? null) === 19) {
            throw new EvidenceError('assessment_already_active');
        }
        throw $error;
    }
}

/**
 * Run the full assessment pipeline synchronously.
 *
 * This is a bounded, deterministic workflow:
 * 1. Pin the repository's HEAD commit.
 * 2. Fetch and plan the file tree.
 * 3. Inspect selected files (read-only, bounded).
 * 4. Evaluate evidence using deterministic rules.
 * 5. Generate prioritized improvement tasks.
 * 6. Persist everything to SQLite.
 */
function run(\PDO $pdo, int $assessmentId, ?Transport $transport = null): array
{
    $row = $pdo->prepare('SELECT id, user_id, repository, target_role, state FROM assessments WHERE id = ?');
    $row->execute([$assessmentId]);
    $assessment = $row->fetch();
    if (!$assessment || $assessment['state'] !== 'queued') {
        throw new EvidenceError('assessment_state_conflict');
    }

    $repository = $assessment['repository'];
    $role = $assessment['target_role'];

    $github = new GitHub(
        $transport ?? new CurlTransport(),
        static function () use ($pdo): void { reserve_budget($pdo); },
        static function (int $until) use ($pdo): void { set_cooldown($pdo, $until); }
    );

    try {
        // Step 1: Pin the commit.
        transition($pdo, $assessmentId, 'queued', 'planning');
        $pin = $github->pin($repository);
        $commit = $pin['commit'];
        $tree = $pin['tree'];

        // Update commit SHA.
        $pdo->prepare('UPDATE assessments SET commit_sha = ? WHERE id = ?')
            ->execute([$commit, $assessmentId]);

        // Step 2: Plan which files to inspect.
        $treeEntries = $github->tree($repository, $tree);
        $inspectionPlan = plan($treeEntries, $role);

        // Persist the plan.
        $planJson = json_encode($inspectionPlan, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (strlen($planJson) > 32768) {
            throw new EvidenceError('plan_too_large');
        }
        $pdo->prepare('UPDATE assessments SET plan_json = ? WHERE id = ?')
            ->execute([$planJson, $assessmentId]);

        // Step 3: Inspect files (bounded content collection).
        transition($pdo, $assessmentId, 'planning', 'inspecting');
        $contents = [];
        foreach ($inspectionPlan['files'] as $file) {
            try {
                $contents[$file['path']] = $github->file($repository, $file);
            } catch (EvidenceError $error) {
                if (in_array($error->reason, ['unsupported_file_encoding', 'response_too_large'], true)) {
                    $contents[$file['path']] = ''; // Treat as empty for evaluation safety.
                    continue;
                }
                throw $error;
            }
        }

        // Step 4: Evaluate evidence.
        transition($pdo, $assessmentId, 'inspecting', 'evaluating');
        $result = evaluate($repository, $commit, $role, $inspectionPlan, $contents);

        // Step 5: Generate prioritized improvement tasks.
        transition($pdo, $assessmentId, 'evaluating', 'prioritizing');
        $tasks = prioritize($result);
        $result['tasks'] = $tasks;

        // Step 6: Persist everything.
        transition($pdo, $assessmentId, 'prioritizing', 'persisting');
        $resultJson = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (strlen($resultJson) > 65536) {
            throw new EvidenceError('result_too_large');
        }

        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $pdo->prepare('UPDATE assessments SET result_json = ? WHERE id = ?')
                ->execute([$resultJson, $assessmentId]);

            foreach ($tasks as $task) {
                $pdo->prepare(
                    'INSERT INTO improvement_tasks (assessment_id, priority, finding_id, title, acceptance) VALUES (?, ?, ?, ?, ?)'
                )->execute([$assessmentId, $task['priority'], $task['finding_id'], $task['title'], $task['acceptance']]);
            }

            $pdo->exec('COMMIT');
        } catch (\Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }

        transition($pdo, $assessmentId, 'persisting', 'completed');

        return $result;

    } catch (EvidenceError $error) {
        fail($pdo, $assessmentId, $error->reason);
        throw $error;
    } catch (\Throwable $error) {
        fail($pdo, $assessmentId, 'internal_error');
        throw $error;
    }
}

/**
 * Retrieve assessment history for a student.
 */
function history(\PDO $pdo, int $userId, int $limit = 10): array
{
    $statement = $pdo->prepare(
        'SELECT id, user_id, repository, target_role, rubric_version, state, commit_sha, error_code, created_at, updated_at
         FROM assessments WHERE user_id = ? ORDER BY id DESC LIMIT ?'
    );
    $statement->execute([$userId, $limit]);
    return $statement->fetchAll();
}

/**
 * Retrieve a single assessment with full results, scoped to the owning student.
 */
function detail(\PDO $pdo, int $assessmentId, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT a.*, GROUP_CONCAT(t.priority || ":" || t.finding_id || ":" || t.title, "||") AS task_summary
         FROM assessments a
         LEFT JOIN improvement_tasks t ON t.assessment_id = a.id
         WHERE a.id = ? AND a.user_id = ?
         GROUP BY a.id'
    );
    $statement->execute([$assessmentId, $userId]);
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }

    // Decode stored JSON for the caller.
    if ($row['result_json']) {
        $row['result'] = json_decode($row['result_json'], true, 32, JSON_THROW_ON_ERROR);
    }
    if ($row['plan_json']) {
        $row['plan'] = json_decode($row['plan_json'], true, 32, JSON_THROW_ON_ERROR);
    }
    if ($row['events_json']) {
        $row['events'] = json_decode($row['events_json'], true, 32, JSON_THROW_ON_ERROR);
    }

    // Fetch improvement tasks separately for structured access.
    $tasks = $pdo->prepare('SELECT priority, finding_id, title, acceptance FROM improvement_tasks WHERE assessment_id = ? ORDER BY priority');
    $tasks->execute([$assessmentId]);
    $row['tasks'] = $tasks->fetchAll();

    return $row;
}
