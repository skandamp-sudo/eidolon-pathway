<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/organizations/organization.php';
require dirname(__DIR__) . '/app/employment/jobs.php';

function parse_cli_options(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
    }
    return $options;
}

$options = parse_cli_options($argv);

// Gate 1: Safety confirmation guard
if (empty($options['confirm-demo-seed'])) {
    fwrite(STDERR, <<<EOW
================================================================================
EIDOLON PATHWAY — DEMONSTRATION DATA SEEDING SAFETY GUARD
================================================================================
SAFETY WARNING:
This command seeds synthetic demonstration data for hackathon evaluation.
To protect live and development environments from accidental pollution,
you must explicitly supply:

    php bin/seed_demo.php --confirm-demo-seed --demo-db=var/pathway_demo.sqlite

Required flags:
    --confirm-demo-seed           Explicit authorization to run demo seeder
    --demo-db=<path>              Dedicated, isolated demo database file
                                  (MUST NOT be the primary var/pathway.sqlite)

Optional flags:
    --reset-demo                  Clear existing demo records before re-seeding
================================================================================

EOW
    );
    exit(1);
}

// Gate 2: Dedicated isolated demo database target required
$demoDbOption = $options['demo-db'] ?? null;
if (!is_string($demoDbOption) || trim($demoDbOption) === '') {
    fwrite(STDERR, <<<EOW
================================================================================
ERROR: DEDICATED DEMO DATABASE TARGET REQUIRED
================================================================================
The seeder refuses to run without an explicitly specified demo database.
To prevent accidental modification or deletion of actual application data,
you must provide a dedicated demo database target with 'demo' in its name:

    php bin/seed_demo.php --confirm-demo-seed --demo-db=var/pathway_demo.sqlite
================================================================================

EOW
    );
    exit(1);
}

$root = dirname(__DIR__);
$targetDb = str_starts_with($demoDbOption, '/') ? $demoDbOption : $root . '/' . ltrim($demoDbOption, './');
$primaryDb = realpath($root . '/var/pathway.sqlite') ?: ($root . '/var/pathway.sqlite');

// Gate 3: Refuse to operate on primary pathway.sqlite
if ($targetDb === $primaryDb || basename($targetDb) === 'pathway.sqlite') {
    fwrite(STDERR, <<<EOW
================================================================================
SECURITY REFUSAL: PRIMARY DATABASE TARGET REJECTED
================================================================================
The target database {$targetDb} is identified as the primary application
database. Seeding synthetic demonstration records into the primary database is
strictly prohibited.

Specify an isolated demo database instead, e.g.:
    --demo-db=var/pathway_demo.sqlite
================================================================================

EOW
    );
    exit(1);
}

if (!str_contains(basename($targetDb), 'demo')) {
    fwrite(STDERR, "Error: Demo database filename must explicitly contain 'demo' (e.g. pathway_demo.sqlite).\n");
    exit(1);
}

// Ensure directory exists with 0700 permissions
$targetDir = dirname($targetDb);
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0700, true);
}

putenv('PATHWAY_DATABASE_PATH=' . $targetDb);
$pdo = \Pathway\database(true, $targetDb);
\Pathway\initialize_database($pdo);

$reset = !empty($options['reset-demo']);
$credsFile = $targetDir . '/demo_credentials.json';

// Idempotency check
$checkStmt = $pdo->query("SELECT id FROM organizations WHERE slug = 'dsatm-bengaluru-demo'");
$existingCollegeId = $checkStmt->fetchColumn();

if ($existingCollegeId && !$reset) {
    echo "Notice: Demo dataset is already present in this isolated database ($targetDb).\n";
    if (is_file($credsFile)) {
        echo "Loading stored credentials from: $credsFile\n\n";
        $saved = json_decode((string) file_get_contents($credsFile), true);
        if (is_array($saved) && isset($saved['credentials'])) {
            print_credentials_summary($saved['credentials'], $targetDb);
            exit(0);
        }
    }
    echo "To re-seed fresh records with new passwords, run:\n";
    echo "    php bin/seed_demo.php --confirm-demo-seed --demo-db=" . escapeshellarg($demoDbOption) . " --reset-demo\n\n";
    exit(0);
}

function generate_secure_demo_password(): string
{
    // Cryptographically secure, non-reusable demo password
    return 'Demo#' . bin2hex(random_bytes(6)) . '!' . random_int(10, 99);
}

// Generate distinct, high-entropy passwords for this run
$generatedPasswords = [
    'admin' => generate_secure_demo_password(),
    'college_officer' => generate_secure_demo_password(),
    'recruiter_eidolon' => generate_secure_demo_password(),
    'recruiter_studio' => generate_secure_demo_password(),
    'recruiter_nextgen' => generate_secure_demo_password(),
    'student_consenting' => generate_secure_demo_password(),
    'student_confidential' => generate_secure_demo_password(),
];

$pdo->beginTransaction();

try {
    if ($reset && $existingCollegeId) {
        echo "Resetting previous demo records from isolated demo database...\n";
        $pdo->exec("
            DELETE FROM applications WHERE notes LIKE '%DEMO%' OR student_id IN (
                SELECT id FROM users WHERE email LIKE '%@dsatm.test' OR email LIKE '%@eidolon-labs.test' OR email LIKE '%@bengaluru-studio.test' OR email LIKE '%@nextgen-cloud.test' OR email LIKE '%@eidolon-pathway.test'
            );
            DELETE FROM resumes WHERE title LIKE '%DEMO%' OR user_id IN (
                SELECT id FROM users WHERE email LIKE '%@dsatm.test' OR email LIKE '%@eidolon-labs.test' OR email LIKE '%@bengaluru-studio.test' OR email LIKE '%@nextgen-cloud.test' OR email LIKE '%@eidolon-pathway.test'
            );
            DELETE FROM improvement_tasks WHERE assessment_id IN (
                SELECT id FROM assessments WHERE repository LIKE '%Hello-World%'
            );
            DELETE FROM assessments WHERE user_id IN (
                SELECT id FROM users WHERE email LIKE '%@dsatm.test'
            );
            DELETE FROM jobs WHERE organization_id IN (
                SELECT id FROM organizations WHERE slug LIKE '%demo%'
            );
            DELETE FROM organization_memberships WHERE organization_id IN (
                SELECT id FROM organizations WHERE slug LIKE '%demo%'
            );
            DELETE FROM student_profiles WHERE user_id IN (
                SELECT id FROM users WHERE email LIKE '%@dsatm.test'
            );
            DELETE FROM users WHERE email LIKE '%@dsatm.test' OR email LIKE '%@eidolon-labs.test' OR email LIKE '%@bengaluru-studio.test' OR email LIKE '%@nextgen-cloud.test' OR email LIKE '%@eidolon-pathway.test';
            DELETE FROM organizations WHERE slug LIKE '%demo%';
        ");
    }

    $now = time();
    $nowStr = (string) $now;

    echo "Seeding platform administrator with unique credential...\n";
    $adminHash = password_hash($generatedPasswords['admin'], PASSWORD_DEFAULT);
    $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'admin', ?)");
    $userStmt->execute(['Demo Platform Admin', 'demo.admin@eidolon-pathway.test', $adminHash, $nowStr]);
    $adminId = (int) $pdo->lastInsertId();

    echo "Seeding demo college organization & placement officer...\n";
    $orgStmt = $pdo->prepare("
        INSERT INTO organizations (name, slug, type, status, created_by_user_id, created_at, updated_at)
        VALUES (?, ?, ?, 'ACTIVE', ?, ?, ?)
    ");
    $orgStmt->execute([
        'Dayananda Sagar Academy of Technology and Management (DSATM), Bengaluru — DEMO DATA',
        'dsatm-bengaluru-demo',
        'COLLEGE',
        $adminId,
        $now,
        $now
    ]);
    $collegeId = (int) $pdo->lastInsertId();

    $officerHash = password_hash($generatedPasswords['college_officer'], PASSWORD_DEFAULT);
    $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'college', ?)");
    $userStmt->execute(['Prof. M. S. Rao (DSATM Placement Officer - DEMO)', 'demo.placement@dsatm.test', $officerHash, $nowStr]);
    $officerId = (int) $pdo->lastInsertId();
    $collegeOfficerId = $officerId;

    $memStmt = $pdo->prepare("
        INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at)
        VALUES (?, ?, 'ADMIN', 'ACTIVE', ?, ?)
    ");
    $memStmt->execute([$collegeId, $officerId, $now, $now]);

    echo "Seeding 3 verified demo companies & recruiters...\n";
    $companies = [
        [
            'name' => 'EIDOLON Research Labs — DEMO COMPANY',
            'slug' => 'eidolon-research-labs-demo',
            'recruiter_name' => 'Vikram Malhotra (Lead Recruiter - DEMO)',
            'recruiter_email' => 'demo.recruiter@eidolon-labs.test',
            'recruiter_pass' => $generatedPasswords['recruiter_eidolon'],
        ],
        [
            'name' => 'Bangalore Software Studio — DEMO COMPANY',
            'slug' => 'bangalore-software-studio-demo',
            'recruiter_name' => 'Ananya Roy (Talent Acquisition - DEMO)',
            'recruiter_email' => 'demo.talent@bengaluru-studio.test',
            'recruiter_pass' => $generatedPasswords['recruiter_studio'],
        ],
        [
            'name' => 'NextGen Cloud Systems — DEMO COMPANY',
            'slug' => 'nextgen-cloud-systems-demo',
            'recruiter_name' => 'Karthik Menon (Engineering Manager - DEMO)',
            'recruiter_email' => 'demo.eng@nextgen-cloud.test',
            'recruiter_pass' => $generatedPasswords['recruiter_nextgen'],
        ],
    ];

    $companyIds = [];
    $recruiterIds = [];
    foreach ($companies as $c) {
        $orgStmt->execute([$c['name'], $c['slug'], 'COMPANY', $adminId, $now, $now]);
        $cOrgId = (int) $pdo->lastInsertId();
        $companyIds[$c['slug']] = $cOrgId;

        $cHash = password_hash($c['recruiter_pass'], PASSWORD_DEFAULT);
        $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'company', ?)");
        $userStmt->execute([$c['recruiter_name'], $c['recruiter_email'], $cHash, $nowStr]);
        $cUserId = (int) $pdo->lastInsertId();
        $recruiterIds[$c['slug']] = $cUserId;

        $memStmt->execute([$cOrgId, $cUserId, $now, $now]);
    }
    $companyEidolonId = $companyIds['eidolon-research-labs-demo'];
    $recruiterEidolonId = $recruiterIds['eidolon-research-labs-demo'];

    echo "Seeding 6 realistic demo job & internship opportunities...\n";
    $jobs = [
        [
            'org_slug' => 'eidolon-research-labs-demo',
            'title' => 'Python Developer Intern — DEMO VACANCY',
            'description' => '[DEMO OPPORTUNITY - NOT A REAL VACANCY] Join the Autonomous Verification team at EIDOLON Research Labs. Build verified microservices, explore high-assurance Python toolchains, and integrate continuous verification pipelines.',
            'skills' => 'Python, FastAPI, SQLite, PyTest, Automated Verification',
            'role_category' => 'backend',
            'location' => 'Bengaluru (Hybrid)',
        ],
        [
            'org_slug' => 'eidolon-research-labs-demo',
            'title' => 'Cybersecurity Research Intern — DEMO VACANCY',
            'description' => '[DEMO OPPORTUNITY - NOT A REAL VACANCY] Investigate software assurance mechanisms, binary auditing, and cryptographic access control implementations for mission-critical software systems.',
            'skills' => 'C, Cryptography, Static Analysis, Memory Safety, Linux',
            'role_category' => 'backend',
            'location' => 'Bengaluru / Remote',
        ],
        [
            'org_slug' => 'bangalore-software-studio-demo',
            'title' => 'Full-Stack Developer Intern — DEMO VACANCY',
            'description' => '[DEMO OPPORTUNITY - NOT A REAL VACANCY] Help construct next-generation web applications using lightweight, performant standalone runtimes. Zero unnecessary containers; maximum developer velocity and security.',
            'skills' => 'PHP 8.4, SQLite, TypeScript, React, Modern CSS',
            'role_category' => 'fullstack',
            'location' => 'Bengaluru',
        ],
        [
            'org_slug' => 'bangalore-software-studio-demo',
            'title' => 'AI/ML Engineering Intern — DEMO VACANCY',
            'description' => '[DEMO OPPORTUNITY - NOT A REAL VACANCY] Develop evaluation benchmarks and verifiable rubric-scoring agents for developer artifacts. Work closely with product and engineering teams.',
            'skills' => 'Python, PyTorch, Transformers, Model Evaluation, Data Curation',
            'role_category' => 'general',
            'location' => 'Bengaluru',
        ],
        [
            'org_slug' => 'nextgen-cloud-systems-demo',
            'title' => 'Software Quality & Testing Intern — DEMO VACANCY',
            'description' => '[DEMO OPPORTUNITY - NOT A REAL VACANCY] Formulate rigorous automated test matrices, boundary value analysis suites, and regression guards across SQLite and HTTP boundaries.',
            'skills' => 'PHPUnit, Python Unittest, End-to-End Testing, Security Auditing',
            'role_category' => 'backend',
            'location' => 'Bengaluru',
        ],
        [
            'org_slug' => 'nextgen-cloud-systems-demo',
            'title' => 'Frontend Developer Intern — DEMO VACANCY',
            'description' => '[DEMO OPPORTUNITY - NOT A REAL VACANCY] Craft high-contrast, accessible, responsive dashboards and data visualizers adhering to WCAG 2.1 AA standards and modern web design principles.',
            'skills' => 'Modern Semantic HTML5, WCAG Accessibility, Pure CSS, Vanilla JS',
            'role_category' => 'frontend',
            'location' => 'Bengaluru / Hybrid',
        ],
    ];

    $jobIds = [];
    $jobStmt = $pdo->prepare("
        INSERT INTO jobs (organization_id, created_by_user_id, title, description, skills, role_category, location, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
    ");
    foreach ($jobs as $j) {
        $orgId = $companyIds[$j['org_slug']];
        $jobStmt->execute([
            $orgId,
            $adminId,
            $j['title'],
            $j['description'],
            $j['skills'],
            $j['role_category'],
            $j['location'],
            $now,
            $now
        ]);
        $jobIds[] = (int) $pdo->lastInsertId();
    }

    echo "Seeding consenting student (Aarav Patel) with verified portfolio assessment & resume...\n";
    $studentHash = password_hash($generatedPasswords['student_consenting'], PASSWORD_DEFAULT);
    $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'student', ?)");
    $userStmt->execute(['Aarav Patel (Demo Candidate)', 'demo.student.aarav@dsatm.test', $studentHash, $nowStr]);
    $studentAaravId = (int) $pdo->lastInsertId();

    $profStmt = $pdo->prepare("
        INSERT INTO student_profiles (
            user_id, college, skills, bio, github_username,
            college_org_id, college_share_consent,
            share_contact_details, share_resume, share_achievements, share_applications,
            talent_discovery_opt_in, enrollment_number, degree, department, graduation_year,
            affiliation_status, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 1, 1, 1, 1, 1, 1, '1DT23CS007', 'B.E. Computer Science', 'Computer Science & Engineering', 2026, 'AFFILIATED', ?)
    ");
    $profStmt->execute([
        $studentAaravId,
        'Dayananda Sagar Academy of Technology and Management, Bengaluru',
        'Python, FastAPI, SQLite, Automated Testing, Security',
        'Pre-final year CSE student passionate about software assurance, verifiable engineering, and systems reliability.',
        'aarav-patel-dev',
        $collegeId,
        $now
    ]);

    // Seed approved affiliation request for Aarav
    $affReqStmt = $pdo->prepare("
        INSERT INTO college_affiliation_requests (
            student_id, college_org_id, enrollment_number, degree, department,
            graduation_year, verification_note, status, reviewed_by_user_id,
            review_decision_reason, reviewed_at, created_at, updated_at
        ) VALUES (
            ?, ?, '1DT23CS007', 'B.E. Computer Science', 'Computer Science & Engineering',
            2026, '7th semester institutional placement registration.', 'APPROVED', ?,
            'Verified through University Registrar database.', ?, ?, ?
        )
    ");
    $affReqStmt->execute([
        $studentAaravId,
        $collegeId,
        $collegeOfficerId,
        $now - 86400,
        $now - 90000,
        $now - 86400
    ]);

    // Seed completed portfolio assessment
    $eventsJson = json_encode([
        ['step' => 'pin', 'message' => 'Pinned commit 7fd1a60b01f91b314f59955a4e4d4e80d8edf11d'],
        ['step' => 'plan', 'message' => 'Planned bounded file inspection for backend target role'],
        ['step' => 'evaluate', 'message' => 'Rubric 2026.1 evaluation completed with high confidence'],
        ['step' => 'prioritize', 'message' => 'Generated top 3 verifiable improvement tasks'],
    ], JSON_UNESCAPED_SLASHES);

    $resultJson = json_encode([
        'overall_score' => 88,
        'status' => 'verified',
        'findings_count' => 3,
        'summary' => 'Demonstrated proficiency in backend services, clear unit testing structure, and clean module boundaries.',
    ], JSON_UNESCAPED_SLASHES);

    $assessStmt = $pdo->prepare("
        INSERT INTO assessments (user_id, repository, target_role, rubric_version, state, commit_sha, events_json, result_json, created_at, updated_at)
        VALUES (?, 'octocat/Hello-World', 'backend', '2026.1', 'completed', '7fd1a60b01f91b314f59955a4e4d4e80d8edf11d', ?, ?, ?, ?)
    ");
    $assessStmt->execute([$studentAaravId, $eventsJson, $resultJson, $now, $now]);
    $assessmentId = (int) $pdo->lastInsertId();

    // Seed 3 improvement tasks
    $taskStmt = $pdo->prepare("
        INSERT INTO improvement_tasks (assessment_id, priority, finding_id, title, acceptance)
        VALUES (?, ?, ?, ?, ?)
    ");
    $taskStmt->execute([
        $assessmentId,
        1,
        'ERR-01',
        'Add structured error codes to repository handlers',
        'Implement RFC-7807 compliant error responses and explicit domain exception mapping across controllers.'
    ]);
    $taskStmt->execute([
        $assessmentId,
        2,
        'SQL-02',
        'Isolate SQLite transactions with immediate locks',
        'Ensure database mutations acquire BEGIN IMMEDIATE to prevent write contention and lock timeouts.'
    ]);
    $taskStmt->execute([
        $assessmentId,
        3,
        'VAL-03',
        'Enhance boundary input validation for search queries',
        'Ensure query parameter filters sanitize wildcard characters and enforce strict bounds on length.'
    ]);

    // Seed ATS resume citing the verified assessment
    $eduJson = json_encode([
        [
            'institution' => 'DSATM Bengaluru',
            'degree' => 'B.Tech in Computer Science & Engineering',
            'year' => '2023 - 2026',
            'cgpa' => '9.1 / 10.0'
        ]
    ], JSON_UNESCAPED_SLASHES);

    $expJson = json_encode([
        [
            'company' => 'Open Source Contributor',
            'role' => 'Python Backend Contributor',
            'duration' => '2025 - Present',
            'description' => 'Maintained CI workflows, optimized SQLite indexes, and expanded unit test coverage across async services.'
        ]
    ], JSON_UNESCAPED_SLASHES);

    $projJson = json_encode([
        [
            'name' => 'Pathway High-Assurance Runtime',
            'description' => 'Zero-dependency SQLite and PHP 8.4 service featuring deterministic cryptographic testing and RBAC.',
            'technologies' => 'Python, PHP 8.4, SQLite, REST APIs'
        ]
    ], JSON_UNESCAPED_SLASHES);

    $skillsJson = json_encode(['Python', 'FastAPI', 'SQLite', 'PyTest', 'Linux', 'REST APIs'], JSON_UNESCAPED_SLASHES);

    $resumeStmt = $pdo->prepare("
        INSERT INTO resumes (user_id, title, summary, education_json, experience_json, projects_json, skills_json, assessment_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $resumeStmt->execute([
        $studentAaravId,
        'Aarav Patel — Verified Backend Systems Resume',
        'Computer science undergraduate with verified GitHub portfolio evidence in backend services and secure database architecture.',
        $eduJson,
        $expJson,
        $projJson,
        $skillsJson,
        $assessmentId,
        $now,
        $now
    ]);

    // Seed Shortlisted application for Aarav at EIDOLON Research Labs (Job 1)
    $appStmt = $pdo->prepare("
        INSERT INTO applications (student_id, job_id, status, notes, created_at, updated_at)
        VALUES (?, ?, 'shortlisted', ?, ?, ?)
    ");
    $appStmt->execute([
        $studentAaravId,
        $jobIds[0],
        'Eager to contribute to EIDOLON\'s verification engine; verified GitHub portfolio assessment attached.',
        $now,
        $now
    ]);

    // Seed structured achievements for Aarav
    $achStmt = $pdo->prepare("
        INSERT INTO student_achievements (
            user_id, category, title, issuer, issue_date, credential_url,
            description, evidence_assessment_id, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $achStmt->execute([
        $studentAaravId,
        'hackathon',
        'Code for a Billion 2026 Hackathon Finalist',
        'Hackathon Jury',
        'November 2026',
        'https://example.com/cfab-2026/aarav',
        'Developed zero-dependency local-first verifiable career architecture.',
        $assessmentId,
        $now - 86400,
        $now - 86400
    ]);
    $achStmt->execute([
        $studentAaravId,
        'certification',
        'AWS Certified Solutions Architect Associate',
        'Amazon Web Services',
        'August 2025',
        'https://aws.amazon.com/verify/12345',
        'Demonstrated architecture design across resilient distributed cloud systems.',
        null,
        $now - 172800,
        $now - 172800
    ]);

    // Seed access audit logs for Aarav
    $accessLogStmt = $pdo->prepare("
        INSERT INTO sensitive_record_access_logs (
            accessor_user_id, accessor_role, accessor_org_id, student_id,
            record_type, record_id, access_context, granted, accessed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $accessLogStmt->execute([
        $collegeOfficerId,
        'college',
        $collegeId,
        $studentAaravId,
        'profile',
        null,
        'college_placement_details',
        1,
        $now - 7200
    ]);
    $accessLogStmt->execute([
        $recruiterEidolonId,
        'company',
        $companyEidolonId,
        $studentAaravId,
        'application',
        1,
        'recruiter_candidate_view',
        1,
        $now - 3600
    ]);

    echo "Seeding non-consenting student (Priya Sharma - Confidential)...\n";
    $confidentialHash = password_hash($generatedPasswords['student_confidential'], PASSWORD_DEFAULT);
    $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'student', ?)");
    $userStmt->execute(['Priya Sharma (Demo Candidate - Confidential)', 'demo.student.priya@dsatm.test', $confidentialHash, $nowStr]);
    $studentPriyaId = (int) $pdo->lastInsertId();

    $profStmt = $pdo->prepare("
        INSERT INTO student_profiles (
            user_id, college, skills, bio, github_username,
            college_org_id, college_share_consent,
            share_contact_details, share_resume, share_achievements, share_applications,
            talent_discovery_opt_in, enrollment_number, degree, department, graduation_year,
            affiliation_status, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, '1DT23CS088', 'B.E. Information Science', 'Information Science & Engineering', 2026, 'AFFILIATED', ?)
    ");
    $profStmt->execute([
        $studentPriyaId,
        'Dayananda Sagar Academy of Technology and Management, Bengaluru',
        'C, Rust, Systems Security, Cryptography',
        'Systems programmer focused on confidential computing and secure enclaves.',
        'priya-sec',
        $collegeId,
        $now
    ]);

    // Seed approved affiliation request for Priya
    $affReqStmt->execute([
        $studentPriyaId,
        $collegeId,
        $collegeOfficerId,
        $now - 86400,
        $now - 90000,
        $now - 86400
    ]);

    // Seed an access audit log showing denied placement attempt for Priya (Confidential)
    $accessLogStmt->execute([
        $collegeOfficerId,
        'college',
        $collegeId,
        $studentPriyaId,
        'profile',
        null,
        'college_placement_details',
        0,
        $now - 1800
    ]);

    // Seed an application for Priya (Cybersecurity Research Intern)
    $appStmt = $pdo->prepare("
        INSERT INTO applications (student_id, job_id, status, notes, created_at, updated_at)
        VALUES (?, ?, 'applied', ?, ?, ?)
    ");
    $appStmt->execute([
        $studentPriyaId,
        $jobIds[1],
        'Interested in binary auditing and software assurance research.',
        $now,
        $now
    ]);

    // Seed 3rd student: Rohan Kumar with a PENDING affiliation request for placement officer review demo
    echo "Seeding applicant student (Rohan Kumar) with pending affiliation request...\n";
    $rohanHash = password_hash('StudentRohan#2026', PASSWORD_DEFAULT);
    $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, 'student', ?)");
    $userStmt->execute(['Rohan Kumar (Demo Applicant)', 'demo.student.rohan@dsatm.test', $rohanHash, $nowStr]);
    $studentRohanId = (int) $pdo->lastInsertId();

    $profStmt->execute([
        $studentRohanId,
        'Dayananda Sagar Academy of Technology and Management, Bengaluru',
        'Go, Docker, Kubernetes, Microservices',
        'Cloud engineering student preparing for platform operations roles.',
        'rohan-cloud',
        null, // Unaffiliated until review
        $now
    ]);

    $pendingReqStmt = $pdo->prepare("
        INSERT INTO college_affiliation_requests (
            student_id, college_org_id, enrollment_number, degree, department,
            graduation_year, verification_note, status, created_at, updated_at
        ) VALUES (?, ?, '1DT23CS105', 'B.E. Computer Science', 'Computer Science & Engineering', 2026, 'Final year cloud computing specialization enrollment.', 'PENDING', ?, ?)
    ");
    $pendingReqStmt->execute([
        $studentRohanId,
        $collegeId,
        $now - 3600,
        $now - 3600
    ]);

    // Seed sample pending organization access request for EIDOLON CONTROL review demo
    echo "Seeding pending institutional access request (Aether Autonomous Robotics)...\n";
    $orgReqStmt = $pdo->prepare("
        INSERT INTO organization_requests (
            name, type, website, contact_name, contact_email, designation, verification_notes,
            status, ip_address, created_at
        ) VALUES (
            'Aether Autonomous Robotics', 'COMPANY', 'https://aether-robotics.test',
            'Dr. Vikram Sen', 'vikram.sen@aether-robotics.test', 'Chief Technology Officer',
            'Corporate registration CIN-U72900KA2024PTC11892. Department of Science & Technology AI research partner.',
            'PENDING', '127.0.0.1', ?
        )
    ");
    $orgReqStmt->execute([$now - 7200]);

    $pdo->commit();

    // Securely persist generated credentials to protected local JSON file (mode 0600)
    $credData = [
        'generated_at' => date('c'),
        'database' => $targetDb,
        'credentials' => [
            [
                'role' => 'Platform Administrator',
                'portal_path' => '/admin/login',
                'email' => 'demo.admin@eidolon-pathway.test',
                'password' => $generatedPasswords['admin'],
                'focus' => 'Multi-Tenant Governance & Immutable Audit Logs',
            ],
            [
                'role' => 'College Placement Officer',
                'portal_path' => '/college/login',
                'email' => 'demo.placement@dsatm.test',
                'password' => $generatedPasswords['college_officer'],
                'focus' => 'Placement Analytics & Consent-First Privacy',
            ],
            [
                'role' => 'Company Recruiter (EIDOLON Labs)',
                'portal_path' => '/company/login',
                'email' => 'demo.recruiter@eidolon-labs.test',
                'password' => $generatedPasswords['recruiter_eidolon'],
                'focus' => 'Candidate Evidence Review & Shortlisting',
            ],
            [
                'role' => 'Company Recruiter (Studio)',
                'portal_path' => '/company/login',
                'email' => 'demo.talent@bengaluru-studio.test',
                'password' => $generatedPasswords['recruiter_studio'],
                'focus' => 'Cross-Tenant Employer Isolation',
            ],
            [
                'role' => 'Student (Consenting)',
                'portal_path' => '/login',
                'email' => 'demo.student.aarav@dsatm.test',
                'password' => $generatedPasswords['student_consenting'],
                'focus' => 'GitHub Evidence, ATS Resume, Shortlisted Status',
            ],
            [
                'role' => 'Student (Confidential)',
                'portal_path' => '/login',
                'email' => 'demo.student.priya@dsatm.test',
                'password' => $generatedPasswords['student_confidential'],
                'focus' => 'Privacy-First Confidential Application',
            ],
        ],
    ];

    file_put_contents($credsFile, json_encode($credData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    chmod($credsFile, 0600);

    echo "\n=== DEMONSTRATION DATA SEEDED SUCCESSFULLY ===\n";
    echo "Protected credential storage saved to: $credsFile (permissions 0600)\n\n";

    print_credentials_summary($credData['credentials'], $targetDb);

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Error seeding demo data: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}

function print_credentials_summary(array $credentials, string $dbPath): void
{
    echo <<<EOT
=======================================================================================================
                        EIDOLON PATHWAY — DEMONSTRATION ECOSYSTEM DIRECTORY
=======================================================================================================
Database: {$dbPath}
Credentials below were dynamically generated with cryptographically secure random bytes for this run.
They are stored locally with mode 0600 and are NEVER committed to version control.

+--------------------------------+-----------------------+---------------------------------------+-----------------------+
| Role                           | Portal Path           | Demo Email                            | Generated Password    |
+--------------------------------+-----------------------+---------------------------------------+-----------------------+

EOT;

    foreach ($credentials as $c) {
        $role = str_pad($c['role'], 30);
        $path = str_pad($c['portal_path'], 21);
        $email = str_pad($c['email'], 37);
        $pass = str_pad($c['password'], 21);
        echo "| {$role} | {$path} | {$email} | {$pass} |\n";
    }

    echo <<<EOT
+--------------------------------+-----------------------+---------------------------------------+-----------------------+

TO LAUNCH LOCAL DEMO SERVER:
-------------------------------------------------------------------------------------------------------
    PATHWAY_DATABASE_PATH={$dbPath} php bin/serve.php 8080
    (or: php bin/serve.php 8080 --demo)

GUIDED DEMONSTRATION JOURNEY:
-------------------------------------------------------------------------------------------------------
1. STUDENT PORTAL (/login)
   - Sign in as Aarav Patel (demo.student.aarav@dsatm.test).
   - View /dashboard: see your active shortlisted application for Python Developer Intern.
   - View /resume: inspect ATS resume with commit-pinned GitHub assessment citation & 3 improvement tasks.
   - View /profile: examine the DSATM affiliation and active Placement Sharing Consent checkbox.

2. COMPANY HUB (/company/login)
   - Sign in as Vikram Malhotra (demo.recruiter@eidolon-labs.test).
   - View /company/dashboard: see 2 active postings and candidates in pipeline.
   - View /company/applicants: see Aarav Patel with SHORTLISTED status, view application notes.

3. COLLEGE NETWORK (/college/login)
   - Sign in as Prof. M. S. Rao (demo.placement@dsatm.test).
   - View /college/dashboard: observe KPI cards (2 Associated, 1 Consented, 1 Shortlisted, 50% Rate).
   - In Student Roster:
     * Aarav Patel is marked [Consented], with email visible and 1 application shown.
     * Priya Sharma is marked [Confidential], with email masked as 'Consent Withheld' and counts hidden.
   - Click 'View Records' on Aarav Patel: view full placement record, application status, and resume.
   - Notice: Priya's records cannot be viewed without explicit student consent (fails closed with 403).

4. PLATFORM ADMIN (/admin/login)
   - Sign in as Platform Admin (demo.admin@eidolon-pathway.test).
   - View /admin/dashboard: review active organizations, audit logs, and organization lifecycle controls.
=======================================================================================================

EOT;
}
