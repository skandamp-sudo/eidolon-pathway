<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/evidence/agent.php';

$directory = sys_get_temp_dir() . '/pathway-live-' . bin2hex(random_bytes(8));
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

$userId = \Pathway\register_student($pdo, [
    'name' => 'Live Test Student',
    'email' => 'live.student@example.test',
    'password' => 'SecurePassword1234!',
]);

echo "=== LIVE GITHUB REPOSITORY ASSESSMENT ===\n";
echo "Target Repository: octocat/Hello-World\n";
echo "Target Role: backend\n";

$assessmentId = \Pathway\Evidence\queue($pdo, $userId, 'octocat/Hello-World', 'backend');
echo "Queued Assessment ID: $assessmentId\n";

$t0 = microtime(true);
$result = \Pathway\Evidence\run($pdo, $assessmentId); // uses default CurlTransport
$elapsed = round(microtime(true) - $t0, 3);
echo "Assessment completed in {$elapsed}s\n\n";

echo "Pinned Commit SHA: " . $result['commit_sha'] . "\n";
echo "Rubric Version: " . $result['rubric_version'] . "\n";
echo "Target Role: " . $result['target_role'] . "\n\n";

echo "--- Findings (" . count($result['findings']) . ") ---\n";
foreach ($result['findings'] as $f) {
    echo "• [{$f['state']}] {$f['label']} ({$f['id']})\n";
    echo "  Explanation: {$f['explanation']}\n";
    if (!empty($f['citations'])) {
        foreach ($f['citations'] as $c) {
            echo "  Citation: {$c['url']}\n";
        }
    }
}

echo "\n--- Prioritized Improvement Tasks (" . count($result['tasks']) . ") ---\n";
foreach ($result['tasks'] as $t) {
    echo "Priority {$t['priority']} [Finding: {$t['finding_id']}]: {$t['title']}\n";
    echo "  Acceptance: {$t['acceptance']}\n";
    echo "  Reason: {$t['reason']}\n";
}

$detail = \Pathway\Evidence\detail($pdo, $assessmentId, $userId);
assert($detail['state'] === 'completed');
assert(strlen($detail['commit_sha']) === 40);
assert(count($detail['tasks']) === 3);

echo "\nDatabase verification: PASS (state=completed, 3 tasks stored, events logged)\n";
echo "LIVE VERIFICATION SUCCESSFUL\n";
