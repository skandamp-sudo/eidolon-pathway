<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/evidence/agent.php';

$passed = 0; $failed = 0;
function ensure(bool $ok): void { if (!$ok) { throw new RuntimeException('Assertion failed.'); } }
function run_test(string $name, callable $test): void {
    global $passed, $failed;
    try { $test(); $passed++; echo "PASS $name\n"; }
    catch (Throwable $error) { $failed++; echo "FAIL $name (" . get_class($error) . ": " . $error->getMessage() . ")\n"; }
}
function rejects(callable $call, string $reason): void {
    try { $call(); } catch (Pathway\Evidence\EvidenceError $error) {
        ensure($error->reason === $reason);
        return;
    }
    throw new RuntimeException('Expected EvidenceError: ' . $reason);
}

// --- Test database setup ---
$directory = sys_get_temp_dir() . '/pathway-evidence-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
putenv('PATHWAY_DATA_DIR=' . $directory);
register_shutdown_function(function () use ($directory): void {
    foreach (glob($directory . '/*') as $file) { if (is_file($file)) { unlink($file); } }
    if (is_dir($directory . '/sessions')) {
        foreach (glob($directory . '/sessions/*') as $f) { unlink($f); }
        rmdir($directory . '/sessions');
    }
    rmdir($directory);
});
$pdo = \Pathway\database(true);
\Pathway\initialize_database($pdo);

// Register a test student.
$password = bin2hex(random_bytes(16));
$userId = \Pathway\register_student($pdo, ['name' => 'Evidence Tester', 'email' => 'evidence@example.test', 'password' => $password]);

// === Repository validation ===
run_test('valid repository owner/name', function (): void {
    ensure(\Pathway\Evidence\repository('octocat/hello-world') === 'octocat/hello-world');
});
run_test('valid repository with URL prefix', function (): void {
    ensure(\Pathway\Evidence\repository('https://github.com/octocat/hello-world') === 'octocat/hello-world');
});
run_test('repository lowercased', function (): void {
    ensure(\Pathway\Evidence\repository('Octocat/Hello-World') === 'octocat/hello-world');
});
run_test('trailing slash stripped', function (): void {
    ensure(\Pathway\Evidence\repository('octocat/hello-world/') === 'octocat/hello-world');
});
run_test('empty repository rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\repository(''), 'invalid_repository');
});
run_test('dotdot repository rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\repository('owner/..'), 'invalid_repository');
});
run_test('.git suffix rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\repository('owner/repo.git'), 'invalid_repository');
});
run_test('overlong repository rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\repository(str_repeat('x', 200)), 'invalid_repository');
});
run_test('path traversal rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\repository('../../etc/passwd'), 'invalid_repository');
});
run_test('SSRF URL injection rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\repository('https://evil.com/foo'), 'invalid_repository');
});

// === Role validation ===
run_test('valid role frontend', function (): void { ensure(\Pathway\Evidence\role('frontend') === 'frontend'); });
run_test('valid role backend', function (): void { ensure(\Pathway\Evidence\role('backend') === 'backend'); });
run_test('valid role fullstack', function (): void { ensure(\Pathway\Evidence\role('fullstack') === 'fullstack'); });
run_test('invalid role rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\role('admin'), 'invalid_role');
});

// === SHA validation ===
run_test('valid SHA accepted', function (): void {
    ensure(\Pathway\Evidence\sha(str_repeat('a', 40)) === str_repeat('a', 40));
});
run_test('short SHA rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\sha('abc'), 'invalid_github_response');
});
run_test('null SHA rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\sha(null), 'invalid_github_response');
});

// === File categorization ===
run_test('readme categorized', function (): void {
    ensure(\Pathway\Evidence\category('README.md') === 'readme');
    ensure(\Pathway\Evidence\category('docs/README.rst') === 'readme');
});
run_test('CI categorized', function (): void {
    ensure(\Pathway\Evidence\category('.github/workflows/test.yml') === 'ci');
});
run_test('source categorized', function (): void {
    ensure(\Pathway\Evidence\category('src/main.php') === 'source');
    ensure(\Pathway\Evidence\category('app.js') === 'source');
});
run_test('test categorized', function (): void {
    ensure(\Pathway\Evidence\category('tests/unit_test.php') === 'test');
    ensure(\Pathway\Evidence\category('src/App.test.js') === 'test');
});
run_test('vendor excluded', function (): void {
    ensure(\Pathway\Evidence\category('vendor/autoload.php') === null);
});
run_test('dotenv excluded', function (): void {
    ensure(\Pathway\Evidence\category('.env.local') === null);
});
run_test('credentials excluded', function (): void {
    ensure(\Pathway\Evidence\category('config.php') === null);
    ensure(\Pathway\Evidence\category('keys/api.pem') === null);
});

// === Plan generation ===
run_test('plan generates file list with limits', function (): void {
    $tree = [
        ['path' => 'README.md', 'type' => 'blob', 'mode' => '100644', 'sha' => str_repeat('a', 40), 'size' => 500],
        ['path' => 'src/app.php', 'type' => 'blob', 'mode' => '100644', 'sha' => str_repeat('b', 40), 'size' => 1200],
        ['path' => 'tests/test.php', 'type' => 'blob', 'mode' => '100644', 'sha' => str_repeat('c', 40), 'size' => 800],
        ['path' => '.github/workflows/ci.yml', 'type' => 'blob', 'mode' => '100644', 'sha' => str_repeat('d', 40), 'size' => 300],
    ];
    $plan = \Pathway\Evidence\plan($tree, 'backend');
    ensure($plan['role'] === 'backend');
    ensure(count($plan['files']) === 4);
    ensure($plan['limits']['files'] === 6);
    ensure($plan['limits']['seconds'] === 45);
});
run_test('plan skips oversized files', function (): void {
    $tree = [
        ['path' => 'README.md', 'type' => 'blob', 'mode' => '100644', 'sha' => str_repeat('a', 40), 'size' => 50000],
    ];
    $plan = \Pathway\Evidence\plan($tree, 'frontend');
    ensure(count($plan['files']) === 0);
    ensure($plan['skipped_counts']['readme'] === 1);
});
run_test('plan rejects path traversal', function (): void {
    $tree = [['path' => '../etc/passwd', 'type' => 'blob', 'mode' => '100644', 'sha' => str_repeat('a', 40), 'size' => 100]];
    rejects(fn() => \Pathway\Evidence\plan($tree, 'backend'), 'invalid_tree_path');
});
run_test('plan rejects absolute path', function (): void {
    $tree = [['path' => '/etc/passwd', 'type' => 'blob', 'mode' => '100644', 'sha' => str_repeat('a', 40), 'size' => 100]];
    rejects(fn() => \Pathway\Evidence\plan($tree, 'backend'), 'invalid_tree_path');
});
run_test('plan rejects duplicate paths', function (): void {
    $tree = [
        ['path' => 'src/a.php', 'type' => 'blob', 'mode' => '100644', 'sha' => str_repeat('a', 40), 'size' => 100],
        ['path' => 'src/a.php', 'type' => 'blob', 'mode' => '100644', 'sha' => str_repeat('b', 40), 'size' => 100],
    ];
    rejects(fn() => \Pathway\Evidence\plan($tree, 'backend'), 'invalid_tree_path');
});

// === Citation generation ===
run_test('citation generates correct URL', function (): void {
    $commit = str_repeat('a', 40);
    $url = \Pathway\Evidence\citation('octocat/hello-world', $commit, 'src/main.php', 42);
    ensure($url === 'https://github.com/octocat/hello-world/blob/' . $commit . '/src/main.php#L42');
});
run_test('citation URL-encodes special characters', function (): void {
    $commit = str_repeat('a', 40);
    $url = \Pathway\Evidence\citation('octocat/hello-world', $commit, 'src/file (1).php', 1);
    ensure(str_contains($url, 'file%20%281%29.php'));
});

// === Evaluation ===
run_test('evaluate detects backend source pattern', function (): void {
    $plan = ['files' => [
        ['path' => 'src/app.php', 'sha' => str_repeat('a', 40), 'size' => 100, 'category' => 'source'],
    ], 'skipped_counts' => ['readme' => 0, 'source' => 0, 'test' => 0, 'ci' => 0]];
    $contents = ['src/app.php' => '<?php function handle() { $pdo = new PDO("sqlite::memory:"); }'];
    $commit = str_repeat('a', 40);
    $result = \Pathway\Evidence\evaluate('octocat/hello-world', $commit, 'backend', $plan, $contents);
    $source = array_values(array_filter($result['findings'], fn($f) => $f['id'] === 'role-source'));
    ensure(count($source) === 1 && $source[0]['state'] === 'observed');
    ensure(!empty($source[0]['citations']));
});
run_test('evaluate marks missing evidence', function (): void {
    $plan = ['files' => [], 'skipped_counts' => ['readme' => 0, 'source' => 0, 'test' => 0, 'ci' => 0]];
    $contents = [];
    $commit = str_repeat('a', 40);
    $result = \Pathway\Evidence\evaluate('octocat/hello-world', $commit, 'backend', $plan, $contents);
    $source = array_values(array_filter($result['findings'], fn($f) => $f['id'] === 'role-source'));
    ensure($source[0]['state'] === 'missing');
});
run_test('evaluate includes authorship unknown finding', function (): void {
    $plan = ['files' => [], 'skipped_counts' => ['readme' => 0, 'source' => 0, 'test' => 0, 'ci' => 0]];
    $result = \Pathway\Evidence\evaluate('octocat/hello-world', str_repeat('a', 40), 'backend', $plan, []);
    $authorship = array_values(array_filter($result['findings'], fn($f) => $f['id'] === 'authorship'));
    ensure(count($authorship) === 1 && $authorship[0]['state'] === 'unknown');
});
run_test('evaluate includes rubric version', function (): void {
    $plan = ['files' => [], 'skipped_counts' => ['readme' => 0, 'source' => 0, 'test' => 0, 'ci' => 0]];
    $result = \Pathway\Evidence\evaluate('octocat/hello-world', str_repeat('a', 40), 'backend', $plan, []);
    ensure($result['rubric_version'] === \Pathway\Evidence\RUBRIC);
});
run_test('evaluate includes limitations', function (): void {
    $plan = ['files' => [], 'skipped_counts' => ['readme' => 0, 'source' => 0, 'test' => 0, 'ci' => 0]];
    $result = \Pathway\Evidence\evaluate('octocat/hello-world', str_repeat('a', 40), 'backend', $plan, []);
    ensure(count($result['limitations']) >= 4);
});

// === Prioritization ===
run_test('prioritize generates 3 tasks from findings', function (): void {
    $result = ['target_role' => 'backend', 'findings' => [
        ['id' => 'role-source', 'state' => 'missing', 'label' => 'Role-related source pattern', 'explanation' => 'Missing', 'citations' => []],
        ['id' => 'tests', 'state' => 'observed', 'label' => 'Test evidence', 'explanation' => 'Found', 'citations' => [['path' => 't.php', 'line' => 1, 'blob_sha' => str_repeat('a', 40), 'url' => 'http://example.com']]],
        ['id' => 'ci', 'state' => 'missing', 'label' => 'CI configuration', 'explanation' => 'Missing', 'citations' => []],
        ['id' => 'readme', 'state' => 'claimed', 'label' => 'Documented claims', 'explanation' => 'Claimed', 'citations' => [['path' => 'README.md', 'line' => 1, 'blob_sha' => str_repeat('b', 40), 'url' => 'http://example.com']]],
    ]];
    $tasks = \Pathway\Evidence\prioritize($result);
    ensure(count($tasks) === 3);
    ensure($tasks[0]['priority'] === 1);
    ensure($tasks[1]['priority'] === 2);
    ensure($tasks[2]['priority'] === 3);
    // Missing findings should be highest priority.
    ensure($tasks[0]['finding_id'] === 'role-source' || $tasks[0]['finding_id'] === 'ci');
});

// === Queue ===
run_test('queue creates assessment with valid state', function () use ($pdo, $userId): void {
    $id = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    ensure($id > 0);
    $row = $pdo->prepare('SELECT * FROM assessments WHERE id = ?');
    $row->execute([$id]);
    $assessment = $row->fetch();
    ensure($assessment['state'] === 'queued');
    ensure($assessment['repository'] === 'octocat/hello-world');
    ensure($assessment['target_role'] === 'backend');
    ensure($assessment['rubric_version'] === \Pathway\Evidence\RUBRIC);
    // Clean up for next test.
    $pdo->prepare("UPDATE assessments SET state = 'failed' WHERE id = ?")->execute([$id]);
});
run_test('queue rejects duplicate active assessment', function () use ($pdo, $userId): void {
    $id = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    rejects(fn() => \Pathway\Evidence\queue($pdo, $userId, 'octocat/test', 'frontend'), 'assessment_already_active');
    $pdo->prepare("UPDATE assessments SET state = 'failed' WHERE id = ?")->execute([$id]);
});
run_test('queue allows new assessment after previous completed', function () use ($pdo, $userId): void {
    $id1 = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    $pdo->prepare("UPDATE assessments SET state = 'completed' WHERE id = ?")->execute([$id1]);
    $id2 = \Pathway\Evidence\queue($pdo, $userId, 'octocat/test', 'frontend');
    ensure($id2 > $id1);
    $pdo->prepare("UPDATE assessments SET state = 'failed' WHERE id = ?")->execute([$id2]);
});
run_test('queue rejects invalid repository', function () use ($pdo, $userId): void {
    rejects(fn() => \Pathway\Evidence\queue($pdo, $userId, '', 'backend'), 'invalid_repository');
});
run_test('queue rejects invalid role', function () use ($pdo, $userId): void {
    rejects(fn() => \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'admin'), 'invalid_role');
});

// === Budget ===
run_test('budget reserve succeeds initially', function () use ($pdo): void {
    \Pathway\Evidence\reserve_budget($pdo);
});
run_test('budget rejects when blocked', function () use ($pdo): void {
    $pdo->prepare('UPDATE evidence_budget SET blocked_until = ? WHERE singleton = 1')->execute([time() + 3600]);
    rejects(fn() => \Pathway\Evidence\reserve_budget($pdo), 'rate_limited');
    $pdo->exec('UPDATE evidence_budget SET blocked_until = 0 WHERE singleton = 1');
});
run_test('budget rejects at 40 requests', function () use ($pdo): void {
    $pdo->prepare('UPDATE evidence_budget SET requests = 40, window_started = ? WHERE singleton = 1')->execute([time()]);
    rejects(fn() => \Pathway\Evidence\reserve_budget($pdo), 'rate_limited');
    $pdo->exec('UPDATE evidence_budget SET requests = 0, window_started = 0 WHERE singleton = 1');
});
run_test('budget resets after window expires', function () use ($pdo): void {
    $pdo->prepare('UPDATE evidence_budget SET requests = 39, window_started = ? WHERE singleton = 1')->execute([time() - 1000]);
    \Pathway\Evidence\reserve_budget($pdo);
    $row = $pdo->query('SELECT requests FROM evidence_budget WHERE singleton = 1')->fetch();
    ensure((int) $row['requests'] === 1);
});

// === History and detail ===
run_test('history returns assessments for user', function () use ($pdo, $userId): void {
    $items = \Pathway\Evidence\history($pdo, $userId);
    ensure(count($items) >= 1);
    // PDO returns string-typed values; cast for comparison.
    ensure((int) $items[0]['user_id'] === $userId);
});
run_test('history does not return other users assessments', function () use ($pdo): void {
    $items = \Pathway\Evidence\history($pdo, 999999);
    ensure(count($items) === 0);
});
run_test('detail scopes to user', function () use ($pdo, $userId): void {
    $items = \Pathway\Evidence\history($pdo, $userId);
    $id = (int) $items[0]['id'];
    // Same user can see it.
    $detail = \Pathway\Evidence\detail($pdo, $id, $userId);
    ensure($detail !== null);
    // Different user cannot.
    $foreign = \Pathway\Evidence\detail($pdo, $id, 999999);
    ensure($foreign === null);
});

// === Transport mock for integration testing ===
run_test('CurlTransport rejects non-GitHub paths', function (): void {
    $transport = new \Pathway\Evidence\CurlTransport();
    rejects(fn() => $transport->fetch('/etc/passwd', 1024, microtime(true) + 5), 'invalid_transport_path');
    rejects(fn() => $transport->fetch('https://evil.com/repos/x/y', 1024, microtime(true) + 5), 'invalid_transport_path');
});

// === MockTransport for offline end-to-end testing ===
final class MockTransport implements \Pathway\Evidence\Transport
{
    /** @var array<string, array{status:int, headers:array, body:string}> */
    public array $responses = [];
    public array $calls = [];

    public function fetch(string $resource, int $limit, float $deadline): array
    {
        $this->calls[] = $resource;
        if (!isset($this->responses[$resource])) {
            return ['status' => 404, 'headers' => [], 'body' => '{"message":"Not Found"}'];
        }
        $resp = $this->responses[$resource];
        if (strlen($resp['body']) > $limit) {
            throw new \Pathway\Evidence\EvidenceError('response_too_large');
        }
        return $resp;
    }

    public static function createWithFixtures(string $repo, string $commitSha, string $treeSha, array $files): self
    {
        $mock = new self();
        $mock->responses['/repos/' . $repo] = [
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['private' => false, 'visibility' => 'public', 'full_name' => $repo]),
        ];
        $mock->responses['/repos/' . $repo . '/commits?per_page=1'] = [
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([[
                'sha' => $commitSha,
                'commit' => ['tree' => ['sha' => $treeSha]],
            ]]),
        ];
        $treeEntries = [];
        foreach ($files as $path => $content) {
            $blobSha = sha1('blob ' . strlen($content) . "\0" . $content);
            $treeEntries[] = [
                'path' => $path,
                'mode' => '100644',
                'type' => 'blob',
                'sha' => $blobSha,
                'size' => strlen($content),
            ];
            $mock->responses['/repos/' . $repo . '/git/blobs/' . $blobSha] = [
                'status' => 200,
                'headers' => ['content-type' => 'application/vnd.github.raw+json'],
                'body' => $content,
            ];
        }
        $mock->responses['/repos/' . $repo . '/git/trees/' . $treeSha . '?recursive=1'] = [
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'sha' => $treeSha,
                'truncated' => false,
                'tree' => $treeEntries,
            ]),
        ];
        return $mock;
    }
}

// === End-to-end offline agent tests ===
$commitSha = '1111111111111111111111111111111111111111';
$treeSha   = '2222222222222222222222222222222222222222';
$mockFiles = [
    'README.md' => "# Test App\nInstall with php and run tests.\nSetup guide.",
    'src/Handler.php' => "<?php\nclass Handler {\n    public function route() {\n        \$pdo = new PDO('sqlite::memory:');\n        return \$pdo;\n    }\n}",
    'tests/HandlerTest.php' => "<?php\nclass HandlerTest {\n    public function testRoute() {\n        assert(true);\n    }\n}",
    '.github/workflows/ci.yml' => "name: CI\non: push\njobs:\n  test:\n    runs-on: ubuntu-latest\n    steps:\n      - run: echo ok",
];

run_test('end-to-end agent run succeeds with mock transport', function () use ($pdo, $userId, $commitSha, $treeSha, $mockFiles): void {
    $mock = MockTransport::createWithFixtures('octocat/hello-world', $commitSha, $treeSha, $mockFiles);
    $assessmentId = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    $result = \Pathway\Evidence\run($pdo, $assessmentId, $mock);

    ensure($result['rubric_version'] === \Pathway\Evidence\RUBRIC);
    ensure($result['repository'] === 'octocat/hello-world');
    ensure($result['commit_sha'] === $commitSha);
    ensure($result['target_role'] === 'backend');
    ensure(count($result['findings']) === 5); // role-source, tests, ci, readme, authorship
    ensure(count($result['tasks']) === 3);

    // Verify exactly 3 improvement tasks with priorities 1, 2, 3
    ensure($result['tasks'][0]['priority'] === 1);
    ensure($result['tasks'][1]['priority'] === 2);
    ensure($result['tasks'][2]['priority'] === 3);
    foreach ($result['tasks'] as $t) {
        ensure(!empty($t['title']) && !empty($t['acceptance']));
    }

    // Verify citations exist and are commit-pinned
    $observed = 0;
    foreach ($result['findings'] as $f) {
        if ($f['state'] === 'observed' || $f['state'] === 'claimed') {
            $observed++;
            ensure(!empty($f['citations']));
            foreach ($f['citations'] as $cite) {
                ensure(str_starts_with($cite['url'], 'https://github.com/octocat/hello-world/blob/' . $commitSha . '/'));
                ensure(str_contains($cite['url'], '#L'));
                ensure($cite['line'] >= 1);
            }
        }
    }
    ensure($observed >= 4); // source, tests, ci, readme

    // Verify persistence in SQLite
    $row = $pdo->prepare('SELECT * FROM assessments WHERE id = ?');
    $row->execute([$assessmentId]);
    $persisted = $row->fetch();
    ensure($persisted['state'] === 'completed');
    ensure($persisted['commit_sha'] === $commitSha);
    ensure($persisted['error_code'] === null);

    // Verify detail() retrieval
    $detail = \Pathway\Evidence\detail($pdo, $assessmentId, $userId);
    ensure($detail !== null);
    ensure(count($detail['tasks']) === 3);
    ensure($detail['result']['commit_sha'] === $commitSha);

    // Verify events log recorded all states
    $events = $detail['events'];
    $states = array_column($events, 'state');
    ensure(in_array('planning', $states, true));
    ensure(in_array('inspecting', $states, true));
    ensure(in_array('evaluating', $states, true));
    ensure(in_array('prioritizing', $states, true));
    ensure(in_array('persisting', $states, true));
    ensure(in_array('completed', $states, true));
});

run_test('end-to-end evaluation is deterministically repeatable', function () use ($pdo, $userId, $commitSha, $treeSha, $mockFiles): void {
    $mock1 = MockTransport::createWithFixtures('octocat/hello-world', $commitSha, $treeSha, $mockFiles);
    $mock2 = MockTransport::createWithFixtures('octocat/hello-world', $commitSha, $treeSha, $mockFiles);

    $id1 = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    $res1 = \Pathway\Evidence\run($pdo, $id1, $mock1);

    $id2 = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    $res2 = \Pathway\Evidence\run($pdo, $id2, $mock2);

    ensure($res1['rubric_version'] === $res2['rubric_version']);
    ensure($res1['commit_sha'] === $res2['commit_sha']);
    ensure(json_encode($res1['findings']) === json_encode($res2['findings']));
    ensure(json_encode($res1['tasks']) === json_encode($res2['tasks']));
});

run_test('agent run fails safely when repository is private', function () use ($pdo, $userId, $commitSha, $treeSha, $mockFiles): void {
    $mock = MockTransport::createWithFixtures('octocat/hello-world', $commitSha, $treeSha, $mockFiles);
    // Make repo private
    $mock->responses['/repos/octocat/hello-world'] = [
        'status' => 200,
        'headers' => ['content-type' => 'application/json'],
        'body' => json_encode(['private' => true, 'visibility' => 'private', 'full_name' => 'octocat/hello-world']),
    ];
    $id = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    rejects(fn() => \Pathway\Evidence\run($pdo, $id, $mock), 'public_repository_unavailable');

    $persisted = $pdo->query("SELECT state, error_code FROM assessments WHERE id = $id")->fetch();
    ensure($persisted['state'] === 'failed');
    ensure($persisted['error_code'] === 'public_repository_unavailable');

    // Verify student is not locked out of queueing next assessment
    $nextId = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    ensure($nextId > $id);
    $pdo->exec("UPDATE assessments SET state = 'failed' WHERE id = $nextId");
});

run_test('agent run fails safely on empty repository', function () use ($pdo, $userId, $commitSha, $treeSha, $mockFiles): void {
    $mock = MockTransport::createWithFixtures('octocat/hello-world', $commitSha, $treeSha, $mockFiles);
    $mock->responses['/repos/octocat/hello-world/commits?per_page=1'] = [
        'status' => 200,
        'headers' => ['content-type' => 'application/json'],
        'body' => json_encode([]),
    ];
    $id = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    rejects(fn() => \Pathway\Evidence\run($pdo, $id, $mock), 'empty_repository');

    $persisted = $pdo->query("SELECT state, error_code FROM assessments WHERE id = $id")->fetch();
    ensure($persisted['state'] === 'failed');
    ensure($persisted['error_code'] === 'empty_repository');
});

run_test('agent run fails safely on blob SHA integrity mismatch', function () use ($pdo, $userId, $commitSha, $treeSha, $mockFiles): void {
    $mock = MockTransport::createWithFixtures('octocat/hello-world', $commitSha, $treeSha, $mockFiles);
    // Corrupt one blob body
    foreach ($mock->responses as $path => &$resp) {
        if (str_contains($path, '/git/blobs/')) {
            $resp['body'] = 'CORRUPTED CONTENT NOT MATCHING SHA';
            break;
        }
    }
    unset($resp);

    $id = \Pathway\Evidence\queue($pdo, $userId, 'octocat/hello-world', 'backend');
    rejects(fn() => \Pathway\Evidence\run($pdo, $id, $mock), 'blob_integrity_mismatch');

    $persisted = $pdo->query("SELECT state, error_code FROM assessments WHERE id = $id")->fetch();
    ensure($persisted['state'] === 'failed');
    ensure($persisted['error_code'] === 'blob_integrity_mismatch');
});

run_test('completed assessment cannot be transitioned to failed', function () use ($pdo): void {
    $row = $pdo->query("SELECT id FROM assessments WHERE state = 'completed' LIMIT 1")->fetch();
    ensure($row !== false);
    $id = (int) $row['id'];
    \Pathway\Evidence\fail($pdo, $id, 'attempted_failure');
    $check = $pdo->query("SELECT state, error_code FROM assessments WHERE id = $id")->fetch();
    ensure($check['state'] === 'completed');
    ensure($check['error_code'] === null);
});

// === XSS in repository input ===
run_test('XSS in repository input rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\repository('<script>alert(1)</script>'), 'invalid_repository');
});
run_test('SQL injection in repository rejected', function (): void {
    rejects(fn() => \Pathway\Evidence\repository("'; DROP TABLE users; --"), 'invalid_repository');
});

echo "RESULT: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
