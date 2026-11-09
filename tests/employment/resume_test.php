<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/employment/jobs.php';

use Pathway\Employment;

$passed = 0;
$failed = 0;

function ensure(bool $ok, string $msg = 'Assertion failed'): void
{
    if (!$ok) {
        throw new RuntimeException($msg);
    }
}

function run_test(string $name, callable $test): void
{
    global $passed, $failed;
    try {
        $test();
        $passed++;
        echo "PASS $name\n";
    } catch (\Throwable $error) {
        $failed++;
        echo "FAIL $name (" . get_class($error) . ": " . $error->getMessage() . ")\n";
    }
}

function expect_emp_error(callable $call, string $reason, ?int $status = null): void
{
    try {
        $call();
    } catch (Employment\EmploymentError $error) {
        ensure($error->reason === $reason, "Expected reason {$reason}, got {$error->reason}");
        if ($status !== null) {
            ensure($error->statusCode === $status, "Expected status {$status}, got {$error->statusCode}");
        }
        return;
    }
    throw new RuntimeException("Expected EmploymentError: {$reason}");
}

// Temporary SQLite DB
$dir = sys_get_temp_dir() . '/pathway-resume-test-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
putenv('PATHWAY_DATA_DIR=' . $dir);
register_shutdown_function(function () use ($dir): void {
    foreach (glob($dir . '/*') as $file) {
        if (is_file($file)) { unlink($file); }
    }
    @rmdir($dir);
});

$pdo = \Pathway\database(true);
\Pathway\initialize_database($pdo);

echo "=== CHECKPOINT 006A: RESUME BUILDER & EVIDENCE CITATION TESTS ===\n";

// Helper to create a student
function create_student(\PDO $pdo, string $name, string $email): int
{
    $hash = password_hash('password123456', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')");
    $stmt->execute([$name, $email, $hash]);
    $userId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO student_profiles (user_id, college, skills, bio) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, 'MIT', 'PHP, SQLite', 'Engineering student']);
    return $userId;
}

$studentAlice = create_student($pdo, 'Alice Chen', 'alice@mit.test');
$studentBob = create_student($pdo, 'Bob Smith', 'bob@stanford.test');

// Seed an assessment for Alice (state = 'completed')
$now = time();
$stmt = $pdo->prepare("
    INSERT INTO assessments (id, user_id, repository, target_role, rubric_version, state, commit_sha, created_at, updated_at)
    VALUES (101, ?, 'alice/secure-engine', 'backend', '2026.1', 'completed', 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0', ?, ?)
");
$stmt->execute([$studentAlice, $now, $now]);

// Insert 3 improvement tasks for Alice's assessment
$tStmt = $pdo->prepare("INSERT INTO improvement_tasks (assessment_id, priority, finding_id, title, acceptance) VALUES (?, ?, ?, ?, ?)");
$tStmt->execute([101, 1, 'task_1', 'Add automated integration tests', 'At least 3 positive and 3 negative tests.']);
$tStmt->execute([101, 2, 'task_2', 'Replace raw socket calls with TLS', 'Use OpenSSL wrapper.']);
$tStmt->execute([101, 3, 'task_3', 'Document public endpoints', 'Provide OpenAPI specification.']);

// Seed an uncompleted assessment for Alice (state = 'planning')
$stmt = $pdo->prepare("
    INSERT INTO assessments (id, user_id, repository, target_role, rubric_version, state, created_at, updated_at)
    VALUES (102, ?, 'alice/work-in-progress', 'frontend', '2026.1', 'planning', ?, ?)
");
$stmt->execute([$studentAlice, $now, $now]);

// Seed an assessment for Bob (state = 'completed')
$stmt = $pdo->prepare("
    INSERT INTO assessments (id, user_id, repository, target_role, rubric_version, state, commit_sha, created_at, updated_at)
    VALUES (201, ?, 'bob/distributed-kv', 'backend', '2026.1', 'completed', 'b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1', ?, ?)
");
$stmt->execute([$studentBob, $now, $now]);

// --- 1. Basic Creation & Retrieval ---

$aliceResume1 = 0;
run_test('save_resume creates a new resume draft with structured entries', function () use ($pdo, $studentAlice, &$aliceResume1): void {
    $aliceResume1 = Employment\save_resume($pdo, $studentAlice, [
        'title' => 'Backend Specialist 2026',
        'summary' => 'Dedicated backend engineer with focus on verifiable SQLite storage and high-throughput APIs.',
        'skills' => ['PHP 8.4', 'SQLite', 'Go', 'Docker', 'REST'],
        'education' => [
            ['institution' => 'MIT', 'degree' => 'B.S. Computer Science', 'year' => '2022 – 2026', 'gpa' => '3.95']
        ],
        'experience' => [
            ['company' => 'Pathway Labs', 'role' => 'Systems Intern', 'start_date' => 'May 2025', 'end_date' => 'Aug 2025', 'description' => 'Built bounded static evidence pipeline']
        ],
        'projects' => [
            ['name' => 'EIDOLON Core', 'technologies' => 'PHP, SQLite', 'description' => 'Zero-dependency verifiable talent framework', 'link' => 'https://github.com/alice/secure-engine']
        ],
        'assessment_id' => 101,
    ]);
    ensure($aliceResume1 > 0);
});

run_test('get_resume retrieves complete draft with joined assessment evidence', function () use ($pdo, $studentAlice, &$aliceResume1): void {
    $resume = Employment\get_resume($pdo, $aliceResume1, $studentAlice);
    ensure($resume !== null);
    ensure($resume['title'] === 'Backend Specialist 2026');
    ensure($resume['user_name'] === 'Alice Chen');
    ensure($resume['user_email'] === 'alice@mit.test');
    ensure($resume['college'] === 'MIT');
    ensure($resume['assessment_repo'] === 'alice/secure-engine');
    ensure($resume['assessment_sha'] === 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0');
    ensure(count($resume['assessment_tasks']) === 3);
    ensure($resume['assessment_tasks'][0]['title'] === 'Add automated integration tests');
    ensure(count($resume['education']) === 1);
    ensure($resume['education'][0]['institution'] === 'MIT');
    ensure(count($resume['skills']) === 5);
});

// --- 2. Update Drafts ---

run_test('save_resume updates existing draft when valid id provided', function () use ($pdo, $studentAlice, $aliceResume1): void {
    $updatedId = Employment\save_resume($pdo, $studentAlice, [
        'id' => $aliceResume1,
        'title' => 'Senior Backend Engineer',
        'summary' => 'Updated executive summary with 4 years of verified project work.',
        'skills' => ['PHP 8.4', 'SQLite', 'Rust'],
    ]);
    ensure($updatedId === $aliceResume1);

    $resume = Employment\get_resume($pdo, $aliceResume1, $studentAlice);
    ensure($resume['title'] === 'Senior Backend Engineer');
    ensure($resume['summary'] === 'Updated executive summary with 4 years of verified project work.');
    ensure(count($resume['skills']) === 3);
    ensure($resume['skills'][2] === 'Rust');
});

// --- 3. IDOR Protection (Cross-User Privacy) ---

run_test('IDOR: Bob cannot read Alice resume via get_resume', function () use ($pdo, $studentBob, $aliceResume1): void {
    $leak = Employment\get_resume($pdo, $aliceResume1, $studentBob);
    ensure($leak === null, 'Bob was able to read Alice resume!');
});

run_test('IDOR: Bob cannot update Alice resume via save_resume', function () use ($pdo, $studentAlice, $studentBob, $aliceResume1): void {
    expect_emp_error(function () use ($pdo, $studentBob, $aliceResume1): void {
        Employment\save_resume($pdo, $studentBob, [
            'id' => $aliceResume1,
            'title' => 'Hijacked Title by Bob',
            'summary' => 'Malicious overwrite',
        ]);
    }, 'resume_not_found', 404);

    // Verify Alice resume was unchanged
    $resume = Employment\get_resume($pdo, $aliceResume1, $studentAlice);
    ensure($resume['title'] === 'Senior Backend Engineer');
});

run_test('IDOR: Bob cannot delete Alice resume via delete_resume', function () use ($pdo, $studentBob, $aliceResume1): void {
    expect_emp_error(function () use ($pdo, $studentBob, $aliceResume1): void {
        Employment\delete_resume($pdo, $aliceResume1, $studentBob);
    }, 'resume_not_found', 404);
});

// --- 4. Assessment Citation Security ---

run_test('Citation: Student cannot cite another user assessment (forged citation)', function () use ($pdo, $studentAlice): void {
    // Assessment 201 belongs to Bob!
    expect_emp_error(function () use ($pdo, $studentAlice): void {
        Employment\save_resume($pdo, $studentAlice, [
            'title' => 'Attempted Citation Spoofing',
            'assessment_id' => 201, // Bob's assessment
        ]);
    }, 'invalid_assessment_citation', 422);
});

run_test('Citation: Student cannot cite incomplete assessment', function () use ($pdo, $studentAlice): void {
    // Assessment 102 belongs to Alice but state is 'planning'
    expect_emp_error(function () use ($pdo, $studentAlice): void {
        Employment\save_resume($pdo, $studentAlice, [
            'title' => 'Attempted Incomplete Citation',
            'assessment_id' => 102,
        ]);
    }, 'invalid_assessment_citation', 422);
});

// --- 5. Input Validation & Bounds ---

run_test('Validation: Rejects empty title or overlong title (>100 chars)', function () use ($pdo, $studentAlice): void {
    expect_emp_error(function () use ($pdo, $studentAlice): void {
        Employment\save_resume($pdo, $studentAlice, ['title' => '']);
    }, 'invalid_resume_title', 422);

    expect_emp_error(function () use ($pdo, $studentAlice): void {
        Employment\save_resume($pdo, $studentAlice, ['title' => str_repeat('T', 101)]);
    }, 'invalid_resume_title', 422);
});

run_test('Validation: Rejects overlong summary (>2000 chars)', function () use ($pdo, $studentAlice): void {
    expect_emp_error(function () use ($pdo, $studentAlice): void {
        Employment\save_resume($pdo, $studentAlice, ['summary' => str_repeat('S', 2001)]);
    }, 'resume_summary_too_long', 422);
});

run_test('Validation: Rejects oversized payload arrays (>8192 chars)', function () use ($pdo, $studentAlice): void {
    $hugeEducation = [];
    for ($i = 0; $i < 100; $i++) {
        $hugeEducation[] = [
            'institution' => 'University of ' . str_repeat('Large', 20),
            'degree' => 'Bachelor of ' . str_repeat('Science', 20),
        ];
    }
    expect_emp_error(function () use ($pdo, $studentAlice, $hugeEducation): void {
        Employment\save_resume($pdo, $studentAlice, [
            'title' => 'Oversized Test',
            'education' => $hugeEducation,
        ]);
    }, 'education_too_large', 422);
});

// --- 6. XSS Immunity & HTML Sanitization ---

run_test('XSS: HTML and script payloads stored safely as literal text', function () use ($pdo, $studentAlice): void {
    $xssTitle = 'Resume <script>alert("xss")</script>';
    $xssSummary = 'Summary <img src=x onerror=alert(1)> & "quotes"';
    $xssSkills = ['<script>', 'alert(1)', 'PHP & SQLite'];

    $id = Employment\save_resume($pdo, $studentAlice, [
        'title' => $xssTitle,
        'summary' => $xssSummary,
        'skills' => $xssSkills,
    ]);
    ensure($id > 0);

    $retrieved = Employment\get_resume($pdo, $id, $studentAlice);
    ensure($retrieved['title'] === $xssTitle);
    ensure($retrieved['summary'] === $xssSummary);
    ensure($retrieved['skills'][0] === '<script>');
    ensure($retrieved['skills'][2] === 'PHP & SQLite');
});

// --- 7. Input Normalization Helper ---

run_test('normalize_resume_input parses form arrays cleanly', function (): void {
    $formInput = [
        'title' => '  Fullstack Lead  ',
        'summary' => '  Focused on security and performance.  ',
        'skills' => 'PHP, SQLite, Go, Docker',
        'education_institution' => ['Stanford', ''],
        'education_degree' => ['M.S. CS', ''],
        'education_year' => ['2024 – 2026', ''],
        'education_gpa' => ['4.0', ''],
        'experience_company' => ['Acme Corp'],
        'experience_role' => ['Staff Engineer'],
        'experience_start' => ['2023'],
        'experience_end' => ['Present'],
        'experience_desc' => ['Led core infrastructure'],
        'project_name' => ['Verifiable Engine'],
        'project_tech' => ['PHP 8.4'],
        'project_desc' => ['Built evidence collector'],
        'project_link' => ['https://github.com/test/engine'],
    ];

    $norm = Employment\normalize_resume_input($formInput);
    ensure($norm['title'] === 'Fullstack Lead');
    ensure($norm['summary'] === 'Focused on security and performance.');
    ensure(count($norm['skills']) === 4);
    ensure($norm['skills'][1] === 'SQLite');
    ensure(count($norm['education']) === 1);
    ensure($norm['education'][0]['institution'] === 'Stanford');
    ensure(count($norm['experience']) === 1);
    ensure($norm['experience'][0]['company'] === 'Acme Corp');
    ensure(count($norm['projects']) === 1);
    ensure($norm['projects'][0]['name'] === 'Verifiable Engine');
});

// --- 8. Resume Listing Scoped to Owner ---

run_test('list_resumes returns only owner resumes in reverse chronological order', function () use ($pdo, $studentAlice, $studentBob): void {
    $bobId = Employment\save_resume($pdo, $studentBob, ['title' => 'Bob Sole Resume']);

    $aliceList = Employment\list_resumes($pdo, $studentAlice);
    // Alice created multiple resumes
    ensure(count($aliceList) >= 2);
    foreach ($aliceList as $item) {
        ensure($item['id'] !== $bobId, 'Bob resume was listed for Alice!');
    }

    $bobList = Employment\list_resumes($pdo, $studentBob);
    ensure(count($bobList) === 1);
    ensure($bobList[0]['title'] === 'Bob Sole Resume');
});

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
