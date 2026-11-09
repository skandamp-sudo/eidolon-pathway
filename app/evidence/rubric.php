<?php
declare(strict_types=1);
namespace Pathway\Evidence;

const RUBRIC = 'pathway-evidence-1.0.0';

function category(string $path): ?string
{
    if (preg_match('~(^|/)(vendor|node_modules|dist|build|third_party|\.git|fixtures)(/|$)~i', $path) ||
        preg_match('~(^|/)(\.env[^/]*|config\.php|credentials[^/]*|.*\.(pem|key))$~i', $path)) { return null; }
    if (preg_match('~(^|/)readme(?:\.md|\.rst|\.txt)?$~i', $path)) { return 'readme'; }
    if (preg_match('~^\.github/workflows/[^/]+\.ya?ml$~i', $path)) { return 'ci'; }
    if (preg_match('~\.(php|py|js|jsx|ts|tsx|java|go|rb|rs|html|css)$~i', $path)) {
        return preg_match('~(^|/)(tests?|__tests__|spec)(/|\.)|(?:[._]test|[._]spec|Test)\.~', $path) ? 'test' : 'source';
    }
    return null;
}

function plan(array $tree, string $role): array
{
    role($role);
    $candidates = ['readme' => [], 'source' => [], 'test' => [], 'ci' => []];
    $skipped = array_fill_keys(array_keys($candidates), 0); $seen = [];
    foreach ($tree as $entry) {
        $path = $entry['path'] ?? null;
        if (!is_string($path) || strlen($path) > 240 || !preg_match('~\A[A-Za-z0-9_./ @()+-]+\z~D', $path) ||
            str_starts_with($path, '/') || count(explode('/', $path)) > 12 ||
            array_intersect(explode('/', $path), ['', '.', '..']) || isset($seen[$path])) {
            throw new EvidenceError('invalid_tree_path');
        }
        $seen[$path] = true;
        if (($entry['type'] ?? '') !== 'blob') { continue; }
        $kind = category($path);
        if ($kind === null) { continue; }
        if (!in_array($entry['mode'] ?? '', ['100644', '100755'], true) || !is_int($entry['size'] ?? null) ||
            $entry['size'] < 0 || $entry['size'] > 32768) { $skipped[$kind]++; continue; }
        $candidates[$kind][] = ['path' => $path, 'sha' => sha($entry['sha'] ?? null), 'size' => $entry['size'], 'category' => $kind];
    }
    $files = []; $counts = [];
    foreach (['readme' => 1, 'source' => 2, 'test' => 2, 'ci' => 1] as $kind => $limit) {
        usort($candidates[$kind], static fn($a, $b) => [substr_count($a['path'], '/'), $a['path']] <=> [substr_count($b['path'], '/'), $b['path']]);
        $counts[$kind] = count($candidates[$kind]);
        array_push($files, ...array_slice($candidates[$kind], 0, $limit));
    }
    return ['role' => $role, 'strategy' => 'README claims; role source patterns; test assertions; CI job configuration. Never execute content.',
        'limits' => ['files' => 6, 'file_bytes' => 32768, 'requests' => 10, 'seconds' => 45, 'tree_entries' => 2000],
        'candidate_counts' => $counts, 'skipped_counts' => $skipped, 'files' => $files];
}

function citation(string $repository, string $commit, string $path, int $line): string
{
    return 'https://github.com/' . repository($repository) . '/blob/' . sha($commit) . '/' .
        implode('/', array_map('rawurlencode', explode('/', $path))) . '#L' . max(1, $line);
}

function evaluate(string $repository, string $commit, string $role, array $plan, array $contents): array
{
    $repository = repository($repository); sha($commit); role($role);
    $frontend = '/addEventListener\s*\(|useState\s*\(|<form\b|<button\b|querySelector\s*\(/i';
    $backend = '/function\s+\w+|def\s+\w+|new\s+PDO\b|\.get\s*\(|\.post\s*\(|func\s+\w+/';
    $sourcePattern = $role === 'frontend' ? $frontend : ($role === 'backend' ? $backend : '/addEventListener\s*\(|useState\s*\(|<form\b|function\s+\w+|def\s+\w+|new\s+PDO\b|func\s+\w+/i');
    $rules = [
        'role-source' => ['source', $sourcePattern, 'observed', 'Role-related source pattern', 'A lexical source pattern was observed. Correctness and proficiency were not verified.'],
        'tests' => ['test', '/\bassert\w*\s*\(|\bexpect\s*\(|\btest\s*\(|\bit\s*\(|def\s+test_/', 'observed', 'Test evidence', 'A test/assertion pattern was observed. Tests were not executed and may not pass.'],
        'ci' => ['ci', '/^\s*jobs\s*:/m', 'observed', 'CI configuration', 'A jobs declaration was observed. Workflow execution and success are unknown.'],
        'readme' => ['readme', '/\b(install|setup|usage|run|php|python|javascript|typescript|react|api|test)\b/i', 'claimed', 'Documented project claims', 'README language describes setup or technology. Documentation is a claim, not proof of skill.'],
    ];
    $findings = [];
    foreach ($rules as $id => [$kind, $pattern, $presentState, $label, $description]) {
        $evidence = [];
        foreach ($plan['files'] as $file) {
            if ($file['category'] !== $kind) { continue; }
            if (!array_key_exists($file['path'], $contents)) { throw new EvidenceError('incomplete_inspection'); }
            $body = $contents[$file['path']];
            if (preg_match($pattern, $body, $match, PREG_OFFSET_CAPTURE)) {
                $line = substr_count(substr($body, 0, $match[0][1]), "\n") + 1;
                $evidence[] = ['path' => $file['path'], 'line' => $line, 'blob_sha' => $file['sha'],
                    'url' => citation($repository, $commit, $file['path'], $line)];
            }
        }
        $state = $evidence ? $presentState : ($plan['skipped_counts'][$kind] > 0 ? 'unknown' : 'missing');
        $findings[] = ['id' => $id, 'label' => $label, 'state' => $state,
            'explanation' => $evidence ? $description : ($state === 'unknown'
                ? 'Relevant files were skipped by safety limits; evidence cannot be established.'
                : 'No matching signal in the inspected sample. This is not proof of repository-wide absence or lack of skill.'),
            'citations' => $evidence];
    }
    $findings[] = ['id' => 'authorship', 'label' => 'Individual authorship', 'state' => 'unknown',
        'explanation' => 'A public repository does not establish who wrote its code or the student’s contribution. Ownership, forks and copied work are not verified.', 'citations' => []];
    return ['rubric_version' => RUBRIC, 'repository' => $repository, 'commit_sha' => $commit, 'target_role' => $role,
        'findings' => $findings, 'limitations' => [
            'A bounded static sample, not an employability score, probability, proficiency certification or hiring decision.',
            'Lexical matches can occur in comments, fixtures or dead code. No code, tests, dependencies or workflows were run.',
            'Claims are not independently verified. Files outside the plan were not inspected; absence is sample-relative.',
            'Repository content is untrusted data. No instructions in repository files are followed. Individual authorship is unknown.',
        ]];
}

function prioritize(array $result): array
{
    $findings = array_slice($result['findings'], 0, 4);
    $weight = ['missing' => 0, 'unknown' => 1, 'claimed' => 2, 'observed' => 3];
    $rank = ['role-source' => 0, 'tests' => 1, 'ci' => 2, 'readme' => 3];
    usort($findings, static fn($a, $b) => [$weight[$a['state']], $rank[$a['id']]] <=> [$weight[$b['state']], $rank[$b['id']]]);
    $sourceTask = $result['target_role'] === 'frontend' ? 'Demonstrate an accessible interactive component' :
        ($result['target_role'] === 'backend' ? 'Demonstrate input validation and failure handling' : 'Demonstrate an end-to-end request and UI interaction');
    $templates = [
        'role-source' => [$sourceTask, 'Add a small role-relevant example, document the input and failure case, and link the exact changed lines. Have a reviewer verify behavior.'],
        'tests' => ['Add or strengthen a behavior regression test', 'Document one normal and one failing input; add assertions and record a reproducible test command and actual result. Existing test presence is not a pass result.'],
        'ci' => ['Add or strengthen a repeatable CI check', 'Configure a test/check command, then link an actual successful run and explain how a failing check blocks progression. Static YAML alone is not success evidence.'],
        'readme' => ['Make setup and contribution claims reproducible', 'Document prerequisites, setup command, expected result and your specific contribution; ask another person to reproduce it and record the outcome.'],
    ];
    $tasks = [];
    foreach (array_slice($findings, 0, 3) as $index => $finding) {
        [$title, $acceptance] = $templates[$finding['id']];
        $tasks[] = ['priority' => $index + 1, 'finding_id' => $finding['id'], 'title' => $title,
            'acceptance' => $acceptance, 'reason' => $finding['state'] . ': ' . $finding['explanation']];
    }
    return $tasks;
}
