<?php
declare(strict_types=1);

namespace Pathway\Tests;

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/employment/resume_intelligence.php';

use function Pathway\Employment\analyze_resume_for_ats;
use function Pathway\Employment\get_action_verbs;
use function Pathway\Employment\get_role_keywords;

function ensure(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

echo "=== CHECKPOINT 009C: RESUME INTELLIGENCE & ATS ANALYSIS TESTS ===\n";

// 1. Minimal / Empty Resume
$minimalResume = [
    'title' => 'My Draft',
    'contact' => [],
    'summary' => '',
    'education' => [],
    'experience' => [],
    'projects' => [],
    'skills' => [],
    'assessment_id' => null,
];

$minAnalysis = analyze_resume_for_ats($minimalResume);
ensure($minAnalysis['overall_score'] < 30, 'Minimal resume must score low (<30)');
ensure(str_contains($minAnalysis['rating'], 'Incomplete'), 'Rating must indicate incomplete draft');
ensure(count($minAnalysis['checks']) === 6, 'Must contain 6 ATS check categories');
echo "PASS minimal resume scores low with actionable improvement feedback\n";

// 2. High-Quality Comprehensive Resume
$fullResume = [
    'title' => 'Software Engineer ATS Resume',
    'contact' => [
        'full_name' => 'Aarav Patel',
        'email' => 'aarav.patel@dsatm.test',
        'phone' => '+91 98765 43210',
        'location' => 'Bengaluru, India',
    ],
    'summary' => 'Experienced backend software engineering student specializing in high-assurance PHP and Python microservices, distributed systems, and automated verification pipelines. Dedicated to building reliable, secure software with deterministic testing.',
    'education' => [
        [
            'institution' => 'Dayananda Sagar Academy of Technology and Management',
            'degree' => 'B.E. Computer Science and Engineering',
            'graduation_year' => 2026,
            'details' => 'GPA 9.2/10. Focus on Distributed Operating Systems and Cryptography.',
        ]
    ],
    'experience' => [
        [
            'company' => 'EIDOLON Software Research Labs',
            'role' => 'Backend Engineering Intern',
            'start_date' => '2024-06',
            'end_date' => '2024-09',
            'description' => "Implemented high-performance SQLite storage backend for verification data.\nArchitected deterministic mock transports for API integrations.\nOptimized database indexing and queries, reducing retrieval latency by 45%.\nAutomated unit test coverage reaching 98% across core services.",
        ]
    ],
    'projects' => [
        [
            'name' => 'EIDOLON PATHWAY',
            'technologies' => 'PHP, SQLite, Python, Docker',
            'description' => 'Developed multi-tenant platform for verified software credentials and GitHub evidence inspection.',
        ]
    ],
    'skills' => ['Python', 'PHP', 'SQLite', 'REST APIs', 'Docker', 'Git', 'FastAPI', 'PyTest', 'CI/CD'],
    'assessment_id' => 42, // Verified GitHub assessment linked!
];

$fullAnalysis = analyze_resume_for_ats($fullResume, null, 'backend');
ensure($fullAnalysis['overall_score'] >= 85, 'Comprehensive resume must score >= 85 (got: ' . $fullAnalysis['overall_score'] . ')');
ensure($fullAnalysis['rating'] === 'Strong ATS Alignment', 'Rating must be Strong ATS Alignment');

// Verify each check category
$checkScores = [];
foreach ($fullAnalysis['checks'] as $c) {
    $checkScores[$c['id']] = $c;
    ensure($c['passed'] === true, 'Category ' . $c['category'] . ' must pass');
}

ensure($checkScores['contact_info']['score'] === 15, 'Contact info must receive 15 points');
ensure($checkScores['summary']['score'] === 15, 'Summary must receive 15 points');
ensure($checkScores['education']['score'] === 15, 'Education must receive 15 points');
ensure($checkScores['action_verbs']['score'] === 20, 'Action verbs must receive 20 points');
ensure($checkScores['skills']['score'] === 15, 'Skills must receive 15 points');
ensure($checkScores['evidence_citation']['score'] === 20, 'Evidence citation must receive 20 points');

echo "PASS comprehensive resume with verified assessment achieves high ATS score (>=85)\n";

// 3. Action Verbs Detection
$verbsFound = $fullAnalysis['action_verbs_found'];
ensure(in_array('implemented', $verbsFound, true), 'Must detect "implemented"');
ensure(in_array('architected', $verbsFound, true), 'Must detect "architected"');
ensure(in_array('optimized', $verbsFound, true), 'Must detect "optimized"');
ensure(in_array('automated', $verbsFound, true), 'Must detect "automated"');
echo "PASS action verbs detected accurately in project and experience descriptions\n";

// 4. Keyword Matching Against Target Job
$sampleJob = [
    'title' => 'Python & Go Backend Engineer',
    'role_category' => 'backend',
    'skills' => 'Python, Go, Redis, Docker, Kubernetes, PostgreSQL',
];

$jobAnalysis = analyze_resume_for_ats($fullResume, $sampleJob);
$matched = $jobAnalysis['matched_keywords'];
$missing = $jobAnalysis['suggested_keywords'];

ensure(in_array('python', $matched, true), 'Must match "python" keyword');
ensure(in_array('docker', $matched, true), 'Must match "docker" keyword');
ensure(in_array('sqlite', $matched, true), 'Must match "sqlite" keyword');

ensure(in_array('kubernetes', $missing, true) || in_array('redis', $missing, true) || in_array('postgresql', $missing, true), 'Must identify missing job keywords');
echo "PASS targeted job comparison matches keywords and identifies relevant missing skills\n";

// 5. Guidelines and Transparent Disclaimer
ensure(!empty($fullAnalysis['formatting_guidelines']), 'Must include formatting guidelines');
ensure(str_contains(implode(' ', $fullAnalysis['formatting_guidelines']), 'Transparent scoring disclaimer'), 'Must include transparent scoring disclaimer');
echo "PASS transparent scoring disclaimer and ATS formatting guidelines present\n";

echo "====================================================================\n";
echo "RESULT: 5 passed, 0 failed\n";
echo "====================================================================\n";
