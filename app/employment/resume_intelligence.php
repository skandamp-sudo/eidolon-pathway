<?php
declare(strict_types=1);

namespace Pathway\Employment;

/**
 * Standard industry keywords mapped to role categories for ATS alignment analysis.
 */
function get_role_keywords(): array
{
    return [
        'backend' => [
            'python', 'php', 'java', 'go', 'golang', 'node', 'nodejs', 'sql', 'sqlite', 'postgresql',
            'mysql', 'rest', 'api', 'microservices', 'docker', 'redis', 'git', 'testing', 'pytest',
            'authentication', 'jwt', 'security', 'caching', 'concurrency', 'linux'
        ],
        'frontend' => [
            'javascript', 'typescript', 'react', 'vue', 'html', 'html5', 'css', 'css3', 'responsive',
            'tailwind', 'sass', 'redux', 'dom', 'webpack', 'vite', 'accessibility', 'a11y', 'ui', 'ux',
            'rest', 'git', 'testing', 'jest'
        ],
        'fullstack' => [
            'javascript', 'typescript', 'python', 'php', 'react', 'node', 'sql', 'postgresql', 'sqlite',
            'rest', 'api', 'docker', 'git', 'html', 'css', 'testing', 'ci/cd', 'cloud', 'security'
        ],
        'devops' => [
            'docker', 'kubernetes', 'k8s', 'terraform', 'ansible', 'ci/cd', 'github actions', 'jenkins',
            'linux', 'bash', 'shell', 'aws', 'gcp', 'cloud', 'monitoring', 'prometheus', 'grafana', 'git'
        ],
        'ml' => [
            'python', 'pytorch', 'tensorflow', 'scikit-learn', 'pandas', 'numpy', 'machine learning',
            'deep learning', 'nlp', 'computer vision', 'data analysis', 'sql', 'git', 'docker', 'jupyter'
        ],
    ];
}

/**
 * Standard strong action verbs recommended for high-impact ATS resumes.
 */
function get_action_verbs(): array
{
    return [
        'implemented', 'architected', 'developed', 'engineered', 'optimized', 'designed',
        'deployed', 'automated', 'integrated', 'refactored', 'built', 'analyzed',
        'configured', 'maintained', 'established', 'spearheaded', 'resolved', 'created',
        'constructed', 'streamlined', 'secured', 'validated', 'benchmarked', 'led'
    ];
}

/**
 * Extract all recursive text content from a structured resume array.
 */
function extract_resume_text(mixed $data): string
{
    if (is_string($data)) {
        return $data . ' ';
    }
    if (is_array($data)) {
        $str = '';
        foreach ($data as $val) {
            $str .= extract_resume_text($val);
        }
        return $str;
    }
    return '';
}

/**
 * Analyze a structured resume draft for ATS readability, keyword alignment, and completeness.
 *
 * @param array $resume Parsed resume draft array
 * @param array|null $job Optional job listing array for targeted match scoring
 * @param string|null $targetRole Optional role category ('backend', 'frontend', 'fullstack', etc.)
 * @return array Structured intelligence report
 */
function analyze_resume_for_ats(array $resume, ?array $job = null, ?string $targetRole = null): array
{
    $checks = [];
    $score = 0;
    $allText = strtolower(extract_resume_text($resume));

    // 1. Contact Details Check (Max: 15)
    $contact = $resume['contact'] ?? [];
    $namePresent = !empty(trim((string) ($contact['full_name'] ?? '')));
    $emailPresent = !empty(trim((string) ($contact['email'] ?? '')));
    $phonePresent = !empty(trim((string) ($contact['phone'] ?? '')));
    $locPresent = !empty(trim((string) ($contact['location'] ?? '')));

    $contactPoints = 0;
    if ($namePresent) $contactPoints += 5;
    if ($emailPresent) $contactPoints += 5;
    if ($phonePresent) $contactPoints += 3;
    if ($locPresent) $contactPoints += 2;

    $score += $contactPoints;
    $checks[] = [
        'id' => 'contact_info',
        'category' => 'Contact Information',
        'passed' => $contactPoints >= 10,
        'score' => $contactPoints,
        'max' => 15,
        'feedback' => ($contactPoints >= 10)
            ? 'Candidate contact header is complete and parseable.'
            : 'Add full name and professional email to ensure recruiter contact reachability.',
    ];

    // 2. Professional Summary Check (Max: 15)
    $summary = trim((string) ($resume['summary'] ?? ''));
    $summaryLen = strlen($summary);
    $summaryPoints = 0;
    if ($summaryLen >= 80 && $summaryLen <= 1200) {
        $summaryPoints = 15;
    } elseif ($summaryLen >= 30) {
        $summaryPoints = 8;
    }

    $score += $summaryPoints;
    $checks[] = [
        'id' => 'summary',
        'category' => 'Professional Summary',
        'passed' => $summaryPoints === 15,
        'score' => $summaryPoints,
        'max' => 15,
        'feedback' => match (true) {
            $summaryPoints === 15 => 'Concise, high-impact career summary aligned with technical focus.',
            $summaryPoints === 8 => 'Summary is somewhat brief; elaborate on technical strengths and domain focus.',
            default => 'Add a 2-4 sentence executive summary highlighting key competencies.',
        },
    ];

    // 3. Education Check (Max: 15)
    $education = $resume['education'] ?? [];
    $eduValid = false;
    if (is_array($education) && count($education) > 0) {
        foreach ($education as $edu) {
            if (!empty($edu['institution']) && !empty($edu['degree'])) {
                $eduValid = true;
                break;
            }
        }
    }
    $eduPoints = $eduValid ? 15 : 0;
    $score += $eduPoints;
    $checks[] = [
        'id' => 'education',
        'category' => 'Education & Qualifications',
        'passed' => $eduValid,
        'score' => $eduPoints,
        'max' => 15,
        'feedback' => $eduValid
            ? 'Academic credentials formatted with degree and institution.'
            : 'Include university degree, field of study, and graduation year.',
    ];

    // 4. Experience & Projects Action Verbs (Max: 20)
    $verbs = get_action_verbs();
    $foundVerbs = [];
    foreach ($verbs as $verb) {
        if (preg_match('/\b' . preg_quote($verb, '/') . '\b/i', $allText)) {
            $foundVerbs[] = $verb;
        }
    }

    $verbCount = count($foundVerbs);
    $expPoints = min(20, $verbCount * 5);
    $score += $expPoints;
    $checks[] = [
        'id' => 'action_verbs',
        'category' => 'Action-Oriented Bullet Points',
        'passed' => $verbCount >= 3,
        'score' => $expPoints,
        'max' => 20,
        'feedback' => ($verbCount >= 3)
            ? 'Strong technical action verbs identified across project descriptions (' . implode(', ', array_slice($foundVerbs, 0, 5)) . ').'
            : 'Begin project bullet points with active verbs (e.g., "Architected", "Implemented", "Optimized", "Deployed").',
    ];

    // 5. Technical Skills Density (Max: 15)
    $skills = $resume['skills'] ?? [];
    $skillCount = is_array($skills) ? count(array_filter($skills, fn($s) => !empty(trim((string)$s)))) : 0;
    $skillPoints = 0;
    if ($skillCount >= 5) {
        $skillPoints = 15;
    } elseif ($skillCount >= 2) {
        $skillPoints = 8;
    }
    $score += $skillPoints;
    $checks[] = [
        'id' => 'skills',
        'category' => 'Technical Skills Categorization',
        'passed' => $skillPoints === 15,
        'score' => $skillPoints,
        'max' => 15,
        'feedback' => ($skillPoints === 15)
            ? 'Skills section contains diverse competencies parsed cleanly.'
            : 'List at least 5 relevant technical tools, languages, and frameworks.',
    ];

    // 6. Verifiable Evidence Citation (Max: 20)
    $hasCitation = !empty($resume['assessment_id']) && (int) $resume['assessment_id'] > 0;
    $citationPoints = $hasCitation ? 20 : 0;
    $score += $citationPoints;
    $checks[] = [
        'id' => 'evidence_citation',
        'category' => 'Verifiable Portfolio Citation',
        'passed' => $hasCitation,
        'score' => $citationPoints,
        'max' => 20,
        'feedback' => $hasCitation
            ? 'Resume cites a verified cryptographic GitHub assessment finding, standing out from unverified resumes.'
            : 'Link a completed GitHub evidence assessment finding to back claims with deterministic proof.',
    ];

    // Keyword Analysis against target role or job
    $roleToTest = $targetRole ?: ($job['role_category'] ?? 'backend');
    $roleKeywords = get_role_keywords()[$roleToTest] ?? get_role_keywords()['backend'];

    if ($job !== null && !empty($job['skills'])) {
        $jobSkillList = array_map('trim', explode(',', strtolower((string) $job['skills'])));
        $roleKeywords = array_values(array_unique(array_merge($roleKeywords, $jobSkillList)));
    }

    $matchedKeywords = [];
    $missingKeywords = [];

    foreach ($roleKeywords as $kw) {
        $cleanKw = trim(strtolower($kw));
        if ($cleanKw === '') continue;
        if (str_contains($allText, $cleanKw)) {
            $matchedKeywords[] = $cleanKw;
        } else {
            $missingKeywords[] = $cleanKw;
        }
    }

    $rating = match (true) {
        $score >= 85 => 'Strong ATS Alignment',
        $score >= 70 => 'Good ATS Compatibility',
        $score >= 50 => 'Fair Alignment (Needs Optimization)',
        default => 'Incomplete (Requires Key Sections)',
    };

    return [
        'overall_score' => $score,
        'rating' => $rating,
        'checks' => $checks,
        'action_verbs_found' => array_values(array_unique($foundVerbs)),
        'matched_keywords' => array_slice(array_values(array_unique($matchedKeywords)), 0, 15),
        'suggested_keywords' => array_slice(array_values(array_unique($missingKeywords)), 0, 8),
        'target_role' => $roleToTest,
        'formatting_guidelines' => [
            'Single-column layout ensures 100% linear text extraction across ATS software (Workday, Taleo, Greenhouse).',
            'Section headings use standard terminology: Summary, Education, Experience, Projects, Skills.',
            'Cryptographic evidence citations provide verifiable proof of authorship and software engineering skill.',
            'Transparent scoring disclaimer: ATS scores reflect structural readability and keyword relevance; they are guidance rather than guaranteed hiring outcomes.'
        ]
    ];
}
