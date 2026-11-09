<?php
declare(strict_types=1);
namespace Pathway;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= match ($screen) {
    'dashboard' => 'Student Dashboard',
    'assessment' => 'Assessment Provenance & Findings',
    'profile' => 'Student Profile',
    'jobs' => 'Verified Job Opportunities',
    'job_detail' => 'Position Details',
    'applications' => 'My Applications',
    'resume' => 'Resume Builder',
    'resume_preview' => 'ATS Resume Preview',
    'company_login' => 'Company Sign In',
    'company_dashboard' => 'Company Recruitment Portal',
    'company_job_form' => 'Manage Job Listing',
    'company_applicants' => 'Candidate Review & Shortlist',
    'college_login' => 'College Placement Portal',
    'college_dashboard' => 'College Placement & Analytics Hub',
    'college_student_detail' => 'Consented Student Placement Records',
    'admin_login' => 'Platform Administration Sign In',
    'admin_dashboard' => 'Platform Governance & Audit',
    default => 'Student Access',
  } ?> · EIDOLON PATHWAY</title>
  <link rel="stylesheet" href="/assets/pathway.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<header class="navbar" role="banner" aria-label="Main navigation">
  <a class="brand" href="/" aria-label="EIDOLON PATHWAY Homepage">
    <div class="logo-symbol" aria-hidden="true">&#x25C7;</div>
    <div class="logo-text">EIDOLON <strong>PATHWAY</strong></div>
  </a>

  <!-- Multi-Tenant Role Architecture Navigation -->
  <nav class="nav-role-shell" aria-label="Platform roles">
    <a class="nav-role-tab <?= in_array($screen, ['login', 'register', 'dashboard', 'profile', 'jobs', 'job_detail', 'applications', 'resume', 'resume_preview', 'assessment']) ? 'active' : '' ?>" href="/login">Student Portal</a>
    <a class="nav-role-tab <?= str_starts_with($screen, 'company_') ? 'active' : '' ?>" href="/company/login">Company Hub</a>
    <a class="nav-role-tab <?= str_starts_with($screen, 'college_') ? 'active' : '' ?>" href="/college/login">College Network</a>
    <a class="nav-role-tab <?= str_starts_with($screen, 'admin_') ? 'active' : '' ?>" href="/admin/login">Platform Admin</a>
  </nav>

  <div class="nav-actions">
    <span class="environment-tag">Local Pilot</span>
    <?php if ($user !== null): ?>
    <form action="/logout" method="post" style="margin:0;display:inline;">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <button class="button secondary" type="submit" style="padding:7px 14px;font-size:0.85rem;">Sign out</button>
    </form>
    <?php endif; ?>
  </div>
</header>

<main id="main" class="container" role="main">
<?php if ($screen === 'error'): ?>
  <section class="auth-panel" aria-labelledby="error-title" style="max-width:540px;margin:40px auto;">
    <p class="eyebrow">Request Not Completed</p>
    <h1 id="error-title" style="font-size:1.8rem;">Let's get you back on track.</h1>
    <p role="alert" style="color:var(--rose-400);margin:16px 0;"><?= escape($message) ?></p>
    <div style="display:flex;gap:14px;margin-top:20px;">
      <a class="button" href="/login">Return to sign in</a>
      <a class="button secondary" href="/register">Create an account</a>
    </div>
  </section>

<?php elseif ($screen === 'login' || $screen === 'register'): ?>
  <div class="auth-layout">
    <section class="hero">
      <p class="eyebrow">EIDOLON PATHWAY · Verifiable Skills &amp; Evidence</p>
      <h1>Your next step starts with verifiable evidence.</h1>
      <p>Transform raw public code artifacts into rigorous, evidence-based improvement roadmaps.</p>
      
      <div class="notice">
        <h2>Local demonstration environment</h2>
        <p>This prototype demonstrates student account lifecycle, SQLite storage isolation, and bounded GitHub evidence extraction. Job listings and external applications are simulated demonstrations.</p>
      </div>

      <div style="margin-top:24px;display:flex;gap:18px;flex-wrap:wrap;">
        <span class="badge">&#x2713; Zero Code Execution</span>
        <span class="badge">&#x2713; Read-Only Analysis</span>
        <span class="badge">&#x2713; Commit-Pinned Citations</span>
      </div>
    </section>

    <section class="auth-panel" aria-labelledby="form-title">
      <h2 id="form-title"><?= $screen === 'register' ? 'Create student account' : 'Welcome back' ?></h2>
      <p class="lead">Use a demonstration name and email. No email is sent or verified.</p>
      
      <?php if (is_string($flash)): ?>
      <p class="notice success" role="status"><?= escape($flash) ?></p>
      <?php endif; ?>

      <form action="/<?= $screen ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
        
        <?php if ($screen === 'register'): ?>
        <div class="form-group">
          <label for="name">Demonstration name</label>
          <input id="name" name="name" autocomplete="name" maxlength="100" placeholder="Alex Chen" required>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="email">Demonstration email</label>
          <input id="email" name="email" type="email" autocomplete="username" maxlength="254" placeholder="student@example.test" required>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="<?= $screen === 'register' ? 'new-password' : 'current-password' ?>" <?= $screen === 'register' ? 'minlength="12"' : '' ?> maxlength="72" aria-describedby="password-help" required>
          <p class="hint" id="password-help">Use 12–72 bytes. Choose a password you do not use elsewhere.</p>
        </div>

        <button class="button" type="submit" style="margin-top:8px;">
          <?= $screen === 'register' ? 'Create student account' : 'Sign in' ?>
        </button>
      </form>

      <p class="switch">
        <?= $screen === 'register' ? 'Already have an account?' : 'New to this local pilot?' ?>
        <a href="/<?= $screen === 'register' ? 'login' : 'register' ?>">
          <?= $screen === 'register' ? 'Sign in' : 'Create an account' ?>
        </a>
      </p>
    </section>
  </div>

<?php elseif ($screen === 'assessment'): ?>
  <section class="hero">
    <p class="eyebrow">Assessment Results &amp; Provenance</p>
    <h1>Assessment #<?= (int) $assessment['id'] ?></h1>
    <p><a href="/dashboard">&larr; Return to dashboard</a></p>
  </section>

  <!-- Provenance Disclosure Strip -->
  <div class="provenance-bar" role="region" aria-label="Assessment provenance details">
    <div class="provenance-item">
      <span class="label">Repository Target</span>
      <p class="val"><code><?= escape($assessment['repository']) ?></code></p>
    </div>
    <div class="provenance-item">
      <span class="label">Target Developer Role</span>
      <p class="val"><span class="badge"><?= escape(ucfirst($assessment['target_role'])) ?></span></p>
    </div>
    <div class="provenance-item">
      <span class="label">Pinned Commit SHA</span>
      <p class="val"><code><?= escape(substr($assessment['commit_sha'] ?? 'unknown', 0, 12)) ?></code></p>
    </div>
    <div class="provenance-item">
      <span class="label">Evaluation Standard</span>
      <p class="val"><span class="badge muted"><?= escape($assessment['rubric_version'] ?? 'N/A') ?></span></p>
    </div>
  </div>

  <?php if ($assessment['state'] === 'failed'): ?>
  <div class="notice error" role="alert">
    <h2>Assessment execution halted</h2>
    <p>Reason: <?= escape($assessment['error_code'] ?? 'unknown') ?>. Files were not executed.</p>
  </div>
  <?php endif; ?>

  <!-- Prioritized Actionable Improvement Roadmap -->
  <?php if (!empty($assessment['tasks'])): ?>
  <section aria-labelledby="roadmap-title" style="margin:36px 0;">
    <p class="eyebrow">Actionable Pathway</p>
    <h2 id="roadmap-title">Prioritized Improvement Roadmap</h2>
    <p>Targeted milestones generated from sampled code evidence to strengthen your engineering portfolio.</p>
    
    <div class="roadmap-grid">
      <?php foreach ($assessment['tasks'] as $task): ?>
      <article class="task-card">
        <span class="task-priority-badge">Priority <?= (int) $task['priority'] ?> · <?= (int) $task['priority'] === 1 ? 'High Impact' : ((int) $task['priority'] === 2 ? 'Recommended' : 'Next Step') ?></span>
        <h3><?= escape($task['title']) ?></h3>
        <p style="font-size:0.88rem;color:var(--text-secondary);"><?= escape($task['reason']) ?></p>
        <div class="task-acceptance">
          <strong>Acceptance Criteria</strong>
          <?= escape($task['acceptance']) ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Evidence Findings & Citations -->
  <?php if (!empty($assessment['result']['findings'])): ?>
  <section aria-labelledby="findings-title" style="margin:36px 0;">
    <p class="eyebrow">Sampled Code Evidence</p>
    <h2 id="findings-title">Evidence Observations &amp; Claims</h2>
    <p>Each observation is tied to static lexical patterns in sampled files. Unobserved signals indicate absence in the inspected sample, not repository-wide proof.</p>
    
    <?php foreach ($assessment['result']['findings'] as $finding): 
      $state = $finding['state'];
      $cardClass = match($state) {
        'observed' => 'state-observed',
        'claimed' => 'state-claimed',
        'missing' => 'state-missing',
        default => 'state-unknown'
      };
      $badgeClass = match($state) {
        'observed' => 'badge-observed',
        'claimed' => 'badge-claimed',
        'missing' => 'badge-missing',
        default => 'badge-unknown'
      };
      $badgeLabel = match($state) {
        'observed' => 'Observed in sample',
        'claimed' => 'Documented claim',
        'missing' => 'Not observed in sample',
        default => 'Unverified / sample-limited'
      };
    ?>
    <article class="finding-card <?= $cardClass ?>">
      <div class="finding-header">
        <span class="badge <?= $badgeClass ?>"><?= escape($badgeLabel) ?></span>
        <div class="finding-title"><?= escape($finding['label']) ?></div>
      </div>
      <p><?= escape($finding['explanation']) ?></p>
      
      <?php if (!empty($finding['citations'])): ?>
      <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);margin-top:12px;">Verified Line Citations:</div>
      <ul class="citation-list">
        <?php foreach ($finding['citations'] as $cite): ?>
        <li>
          <a class="citation-link" href="<?= escape($cite['url']) ?>" rel="noopener noreferrer" target="_blank">
            <?= escape($cite['path']) ?>:line <?= (int) $cite['line'] ?> &nearr;
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <!-- Disclaimers & Limits -->
  <?php if (!empty($assessment['result']['limitations'])): ?>
  <section class="notice" aria-labelledby="limits-title" style="margin:32px 0;">
    <h2 id="limits-title">Verification boundaries &amp; disclaimers</h2>
    <ul style="margin:8px 0 0;padding-left:20px;font-size:0.88rem;color:var(--text-secondary);">
      <?php foreach ($assessment['result']['limitations'] as $limitation): ?>
      <li style="margin:4px 0;"><?= escape($limitation) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

<?php elseif ($screen === 'profile'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab <?= $screen === 'dashboard' || $screen === 'assessment' ? 'active' : '' ?>" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab <?= $screen === 'jobs' || $screen === 'job_detail' ? 'active' : '' ?>" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab <?= $screen === 'applications' ? 'active' : '' ?>" href="/applications">My Applications</a>
    <a class="nav-role-tab <?= $screen === 'profile' ? 'active' : '' ?>" href="/profile">My Profile</a>
    <a class="nav-role-tab <?= $screen === 'resume' || $screen === 'resume_preview' ? 'active' : '' ?>" href="/resume">Resume Builder</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Student Portal · Career Profile</p>
    <h1>Student Engineering Profile</h1>
    <p>Manage your academic credentials, verified skills, and GitHub portfolio handle.</p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php elseif (is_string($flashSuccess)): ?>
  <div class="notice success" role="status"><p><?= escape($flashSuccess) ?></p></div>
  <?php endif; ?>

  <div class="auth-panel" style="max-width:760px;margin-bottom:40px;">
    <form action="/profile" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

      <div class="form-group">
        <label>Account Identity</label>
        <p style="margin:4px 0 12px;font-size:0.95rem;color:var(--text-secondary);">
          <strong><?= escape($user['name']) ?></strong> &middot; <?= escape($user['email']) ?>
        </p>
      </div>

      <div class="form-group">
        <label for="college">College or Institution Name</label>
        <input id="college" name="college" maxlength="200" value="<?= escape($profile['college'] ?? '') ?>" placeholder="e.g. Dayananda Sagar Academy of Technology and Management">
        <p class="hint">Your academic institution as shown on public applications.</p>
      </div>

      <?php if (!empty($approvedColleges)): ?>
      <div class="form-group">
        <label for="college_org_id">Associated Verified College Organization</label>
        <select id="college_org_id" name="college_org_id">
          <option value="">-- None / Independent Student --</option>
          <?php foreach ($approvedColleges as $ac): ?>
          <option value="<?= (int) $ac['id'] ?>" <?= ((int)($profile['college_org_id'] ?? 0) === (int)$ac['id']) ? 'selected' : '' ?>>
            <?= escape($ac['name']) ?> (Verified)
          </option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Link your profile to your campus placement network.</p>
      </div>
      <?php endif; ?>

      <div class="form-group" style="background:var(--bg-surface);padding:14px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);margin:16px 0;">
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-weight:600;">
          <input type="checkbox" name="college_share_consent" value="1" <?= !empty($profile['college_share_consent']) ? 'checked' : '' ?> style="margin-top:3px;">
          <span>
            Authorize College Placement Cell Access
            <span style="display:block;font-size:0.8rem;color:var(--text-muted);font-weight:400;margin-top:4px;">
              When checked, authorized placement officers at your associated college can view your verified skills, resumes, and internship application outcomes. When unchecked, your profile and application history remain completely confidential.
            </span>
          </span>
        </label>
      </div>

      <div class="form-group">
        <label for="skills">Technical Skills &amp; Stack</label>
        <input id="skills" name="skills" maxlength="500" value="<?= escape($profile['skills'] ?? '') ?>" placeholder="e.g. PHP, SQLite, TypeScript, React, Docker, Python">
        <p class="hint">Comma-separated technologies and frameworks you build with.</p>
      </div>

      <div class="form-group">
        <label for="bio">Bio &amp; Engineering Focus</label>
        <textarea id="bio" name="bio" rows="4" maxlength="2000" placeholder="Describe your engineering focus, key projects, and career interests..."><?= escape($profile['bio'] ?? '') ?></textarea>
        <p class="hint">Up to 2,000 characters summarizing your practical experience.</p>
      </div>

      <div class="form-group">
        <label for="github_username">GitHub Username</label>
        <input id="github_username" name="github_username" maxlength="39" value="<?= escape($profile['github_username'] ?? '') ?>" placeholder="e.g. torvalds">
        <p class="hint">Used for linking verified assessment evidence to job applications.</p>
      </div>

      <button class="button" type="submit" style="align-self:flex-start;margin-top:12px;">
        Save Profile
      </button>
    </form>
  </div>

<?php elseif ($screen === 'jobs'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab active" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab" href="/applications">My Applications</a>
    <a class="nav-role-tab" href="/profile">My Profile</a>
    <a class="nav-role-tab" href="/resume">Resume Builder</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Employment Opportunities · Verified Companies</p>
    <h1>Browse Verified Positions</h1>
    <p>Every role is posted by an administrator-approved active company. Zero fabricated listings.</p>
  </section>

  <div class="auth-panel" style="margin-bottom:32px;padding:20px 24px;">
    <form action="/jobs" method="get" style="flex-direction:row;flex-wrap:wrap;align-items:flex-end;gap:16px;">
      <div class="form-group" style="flex:1;min-width:220px;">
        <label for="q">Keyword or Tech Stack</label>
        <input id="q" name="q" value="<?= escape($searchQuery ?? '') ?>" placeholder="e.g. SQLite, React, Backend">
      </div>
      <div class="form-group" style="width:200px;">
        <label for="category">Role Category</label>
        <select id="category" name="category">
          <option value="all" <?= ($selectedCategory ?? '') === 'all' ? 'selected' : '' ?>>All Categories</option>
          <option value="frontend" <?= ($selectedCategory ?? '') === 'frontend' ? 'selected' : '' ?>>Frontend</option>
          <option value="backend" <?= ($selectedCategory ?? '') === 'backend' ? 'selected' : '' ?>>Backend</option>
          <option value="fullstack" <?= ($selectedCategory ?? '') === 'fullstack' ? 'selected' : '' ?>>Fullstack</option>
          <option value="general" <?= ($selectedCategory ?? '') === 'general' ? 'selected' : '' ?>>General</option>
        </select>
      </div>
      <button class="button" type="submit" style="padding:11px 20px;">Filter Positions</button>
      <?php if (!empty($searchQuery) || ($selectedCategory ?? 'all') !== 'all'): ?>
      <a class="button secondary" href="/jobs" style="padding:11px 16px;">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!empty($jobs)): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(340px, 1fr));gap:20px;margin-bottom:40px;">
    <?php foreach ($jobs as $j): ?>
    <article class="task-card" style="padding:22px;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
        <span class="badge badge-observed">&#x2713; <?= escape($j['company_name']) ?></span>
        <span class="badge muted"><?= escape(ucfirst($j['role_category'])) ?></span>
      </div>
      <h3 style="font-size:1.15rem;margin:8px 0 4px;"><?= escape($j['title']) ?></h3>
      <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:12px;">Location: <?= escape($j['location']) ?></p>
      <p style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:16px;flex:1;">
        <?= escape(strlen($j['description']) > 160 ? substr($j['description'], 0, 157) . '...' : $j['description']) ?>
      </p>
      <div style="margin-bottom:16px;">
        <span style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);">Required Skills:</span>
        <p style="font-size:0.85rem;color:var(--emerald-400);margin:4px 0 0;font-family:var(--font-mono);"><?= escape($j['skills']) ?></p>
      </div>
      <a class="button secondary" href="/jobs/view?id=<?= (int) $j['id'] ?>" style="margin-top:auto;text-align:center;">
        View Position &amp; Apply &rarr;
      </a>
    </article>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="notice" style="margin:24px 0;">
    <h2>No active job listings match your filters</h2>
    <p>Try resetting the search terms or check back as verified employers post new opportunities.</p>
  </div>
  <?php endif; ?>

<?php elseif ($screen === 'job_detail'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab active" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab" href="/applications">My Applications</a>
    <a class="nav-role-tab" href="/profile">My Profile</a>
    <a class="nav-role-tab" href="/resume">Resume Builder</a>
  </div>

  <p style="margin-bottom:16px;"><a href="/jobs">&larr; Return to job listings</a></p>

  <section class="hero" style="padding-top:4px;">
    <p class="eyebrow">Verified Company Position</p>
    <h1><?= escape($job['title']) ?></h1>
    <p>Posted by <strong><?= escape($job['company_name']) ?></strong> &middot; <?= escape($job['location']) ?></p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php endif; ?>

  <div class="provenance-bar">
    <div class="provenance-item">
      <span class="label">Organization</span>
      <p class="val"><?= escape($job['company_name']) ?></p>
    </div>
    <div class="provenance-item">
      <span class="label">Role Track</span>
      <p class="val"><span class="badge"><?= escape(ucfirst($job['role_category'])) ?></span></p>
    </div>
    <div class="provenance-item">
      <span class="label">Location</span>
      <p class="val"><?= escape($job['location']) ?></p>
    </div>
    <div class="provenance-item">
      <span class="label">Verification</span>
      <p class="val"><span class="badge badge-observed">&#x2713; Active Verified</span></p>
    </div>
  </div>

  <div class="auth-panel" style="margin-bottom:32px;">
    <h2 style="font-size:1.2rem;margin-bottom:12px;">Position Description</h2>
    <div style="font-size:0.95rem;line-height:1.7;color:var(--text-secondary);white-space:pre-wrap;"><?= escape($job['description']) ?></div>

    <div style="margin-top:24px;padding-top:18px;border-top:1px solid var(--border-subtle);">
      <h3 style="font-size:0.95rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);margin-bottom:8px;">Required Competencies:</h3>
      <code><?= escape($job['skills']) ?></code>
    </div>
  </div>

  <div class="auth-panel" style="margin-bottom:40px;">
    <?php if ($existingApplication): ?>
    <div class="notice success" style="margin:0;">
      <h2>Application Already Submitted</h2>
      <p style="margin-top:6px;">
        You applied for this position on <strong><?= escape(date('Y-m-d H:i', (int) $existingApplication['created_at'])) ?></strong>.
      </p>
      <div style="margin-top:12px;display:flex;align-items:center;gap:12px;">
        <span class="badge <?= $existingApplication['status'] === 'shortlisted' ? 'badge-observed' : ($existingApplication['status'] === 'rejected' ? 'badge-failed' : 'muted') ?>">
          Current Status: <?= escape(ucfirst($existingApplication['status'])) ?>
        </span>
        <a href="/applications" style="font-size:0.88rem;font-weight:700;">View application tracking &rarr;</a>
      </div>
    </div>
    <?php else: ?>
    <h2 style="font-size:1.25rem;margin-bottom:6px;">Submit Application</h2>
    <p class="lead">Submit your application to <?= escape($job['company_name']) ?>. Your verified profile and GitHub evidence will be shared with the hiring team.</p>

    <form action="/jobs/apply" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">

      <div class="form-group">
        <label for="notes">Cover Note &amp; Project Highlight (Optional)</label>
        <textarea id="notes" name="notes" rows="4" maxlength="1000" placeholder="Briefly highlight relevant code evidence or projects demonstrating your fit..."></textarea>
        <p class="hint">Up to 1,000 characters.</p>
      </div>

      <button class="button" type="submit" style="align-self:flex-start;margin-top:8px;">
        Submit Application
      </button>
    </form>
    <?php endif; ?>
  </div>

<?php elseif ($screen === 'applications'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab active" href="/applications">My Applications</a>
    <a class="nav-role-tab" href="/profile">My Profile</a>
    <a class="nav-role-tab" href="/resume">Resume Builder</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Student Portal · Status Tracking</p>
    <h1>Application Tracking</h1>
    <p>Monitor your application submissions and review statuses in real time.</p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php elseif (is_string($flashSuccess)): ?>
  <div class="notice success" role="status"><p><?= escape($flashSuccess) ?></p></div>
  <?php endif; ?>

  <?php if (!empty($applications)): ?>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Application</th>
          <th>Company</th>
          <th>Role Title</th>
          <th>Category</th>
          <th>Location</th>
          <th>Submitted</th>
          <th>Review Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($applications as $app): 
          $status = $app['status'];
          $badgeClass = match ($status) {
            'shortlisted' => 'badge-observed',
            'rejected' => 'badge-failed',
            default => 'muted',
          };
          $statusLabel = match ($status) {
            'shortlisted' => 'Shortlisted',
            'rejected' => 'Declined',
            default => 'Under Review',
          };
        ?>
        <tr>
          <td><strong>#<?= (int) $app['id'] ?></strong></td>
          <td><strong><?= escape($app['company_name']) ?></strong></td>
          <td><a href="/jobs/view?id=<?= (int) $app['job_id'] ?>"><?= escape($app['job_title']) ?></a></td>
          <td><span class="badge muted"><?= escape(ucfirst($app['role_category'])) ?></span></td>
          <td><?= escape($app['location']) ?></td>
          <td><?= escape(date('Y-m-d H:i', (int) $app['created_at'])) ?></td>
          <td><span class="badge <?= $badgeClass ?>"><?= escape($statusLabel) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="notice" style="margin:24px 0;">
    <h2>No applications submitted yet</h2>
    <p>Browse our active job listings and submit your first verified application.</p>
    <p style="margin-top:12px;"><a class="button secondary" href="/jobs">Explore Available Opportunities &rarr;</a></p>
  </div>
  <?php endif; ?>

<?php elseif ($screen === 'resume'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab" href="/applications">My Applications</a>
    <a class="nav-role-tab" href="/profile">My Profile</a>
    <a class="nav-role-tab active" href="/resume">Resume Builder</a>
  </div>

  <section class="hero no-print">
    <p class="eyebrow">Student Portal · Smart Resume Builder</p>
    <h1>Build Your Verifiable Resume</h1>
    <p>Craft an ATS-optimized resume directly backed by your verified GitHub code evidence.</p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error no-print" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php elseif (is_string($flashSuccess)): ?>
  <div class="notice success no-print" role="status"><p><?= escape($flashSuccess) ?></p></div>
  <?php endif; ?>

  <!-- Draft Selector Bar -->
  <?php if (!empty($allResumes)): ?>
  <div class="no-print" style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;align-items:center;">
    <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Saved Drafts:</span>
    <?php foreach ($allResumes as $r): ?>
    <a class="button <?= ($resume !== null && (int)$resume['id'] === (int)$r['id']) ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:0.85rem;" href="/resume?id=<?= (int) $r['id'] ?>">
      <?= escape($r['title']) ?>
    </a>
    <?php endforeach; ?>
    <a class="button secondary" style="padding:6px 14px;font-size:0.85rem;" href="/resume">+ New Draft</a>

    <?php if ($resume !== null): ?>
    <a class="button" style="margin-left:auto;padding:7px 16px;background:var(--emerald-600);color:#fff;font-weight:700;" href="/resume/preview?id=<?= (int) $resume['id'] ?>" target="_blank">
      View ATS Print / PDF Layout &nearr;
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <form action="/resume" method="post" class="no-print">
    <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
    <?php if ($resume !== null && !empty($resume['id'])): ?>
    <input type="hidden" name="id" value="<?= (int) $resume['id'] ?>">
    <?php endif; ?>

    <!-- Document Title & Assessment Evidence Linker -->
    <div class="auth-panel" style="margin-bottom:24px;">
      <h2 style="font-size:1.2rem;margin-bottom:16px;">1. Resume Identity &amp; Evidence Citation</h2>
      
      <div class="form-group" style="margin-bottom:16px;">
        <label for="title">Resume Title / Target Profile</label>
        <input id="title" name="title" maxlength="100" value="<?= escape($resume['title'] ?? 'Software Engineering Resume') ?>" required>
        <p class="hint">Identifier for this resume version (e.g. Backend Specialist Resume 2026).</p>
      </div>

      <div class="form-group">
        <label for="assessment_id">Cite Verified GitHub Portfolio Assessment (Optional)</label>
        <select id="assessment_id" name="assessment_id">
          <option value="">-- No assessment citation (Self-reported only) --</option>
          <?php foreach ($completedAssessments as $a): ?>
          <option value="<?= (int) $a['id'] ?>" <?= ($resume !== null && (int) ($resume['assessment_id'] ?? 0) === (int) $a['id']) ? 'selected' : '' ?>>
            <?= escape($a['repository']) ?> &middot; <?= escape(ucfirst($a['target_role'])) ?> (Commit: <?= escape(substr($a['commit_sha'], 0, 8)) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Citing an assessment embeds cryptographic repository provenance and verified code milestones into the ATS print layout.</p>
      </div>
    </div>

    <!-- Professional Summary & Skills -->
    <div class="auth-panel" style="margin-bottom:24px;">
      <h2 style="font-size:1.2rem;margin-bottom:16px;">2. Professional Summary &amp; Competencies</h2>

      <div class="form-group" style="margin-bottom:16px;">
        <label for="summary">Professional Summary</label>
        <textarea id="summary" name="summary" rows="4" maxlength="2000" placeholder="A concise 2-4 sentence summary of your engineering background, core strengths, and career focus..."><?= escape($resume['summary'] ?? ($profile['bio'] ?? '')) ?></textarea>
        <p class="hint">Up to 2,000 characters.</p>
      </div>

      <div class="form-group">
        <label for="skills">Technical Skills (Comma or line-separated)</label>
        <?php
          $skillsVal = '';
          if ($resume !== null && isset($resume['skills'])) {
            $skillsVal = is_array($resume['skills']) ? implode(', ', $resume['skills']) : (string) $resume['skills'];
          } elseif (!empty($profile['skills'])) {
            $skillsVal = $profile['skills'];
          }
        ?>
        <textarea id="skills" name="skills" rows="3" placeholder="PHP, SQLite, TypeScript, React, Go, Docker, Linux, Git"><?= escape($skillsVal) ?></textarea>
        <p class="hint">Technologies, languages, and tools formatted for ATS parser recognition.</p>
      </div>
    </div>

    <!-- Education Entries -->
    <div class="auth-panel" style="margin-bottom:24px;">
      <h2 style="font-size:1.2rem;margin-bottom:8px;">3. Education</h2>
      <p class="hint" style="margin-bottom:16px;">Add your university, degree, and expected graduation year.</p>

      <?php
        $eduRows = $resume['education'] ?? [];
        if (empty($eduRows)) {
          $eduRows = [
            ['institution' => $profile['college'] ?? '', 'degree' => 'B.S. Computer Science', 'year' => '2022 – 2026', 'gpa' => '']
          ];
        }
        // Pad with one extra blank row for easy addition
        $eduRows[] = ['institution' => '', 'degree' => '', 'year' => '', 'gpa' => ''];
      ?>

      <?php foreach ($eduRows as $i => $edu): ?>
      <div style="background:var(--bg-surface);padding:14px;border-radius:var(--radius-sm);margin-bottom:14px;border:1px solid var(--border-subtle);">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
          <div class="form-group">
            <label style="font-size:0.8rem;">Institution / College</label>
            <input name="education_institution[]" maxlength="150" value="<?= escape($edu['institution'] ?? '') ?>" placeholder="e.g. Stanford University">
          </div>
          <div class="form-group">
            <label style="font-size:0.8rem;">Degree / Major</label>
            <input name="education_degree[]" maxlength="150" value="<?= escape($edu['degree'] ?? '') ?>" placeholder="e.g. B.S. in Computer Science">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label style="font-size:0.8rem;">Years (e.g. 2022 – 2026)</label>
            <input name="education_year[]" maxlength="50" value="<?= escape($edu['year'] ?? '') ?>" placeholder="2022 – 2026">
          </div>
          <div class="form-group">
            <label style="font-size:0.8rem;">GPA (Optional)</label>
            <input name="education_gpa[]" maxlength="20" value="<?= escape($edu['gpa'] ?? '') ?>" placeholder="e.g. 3.9 / 4.0">
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Experience Entries -->
    <div class="auth-panel" style="margin-bottom:24px;">
      <h2 style="font-size:1.2rem;margin-bottom:8px;">4. Work &amp; Internship Experience</h2>
      <p class="hint" style="margin-bottom:16px;">Highlight responsibilities, measurable contributions, and technical achievements.</p>

      <?php
        $expRows = $resume['experience'] ?? [];
        if (empty($expRows)) {
          $expRows = [
            ['company' => '', 'role' => '', 'start_date' => '', 'end_date' => '', 'description' => '']
          ];
        } else {
          $expRows[] = ['company' => '', 'role' => '', 'start_date' => '', 'end_date' => '', 'description' => ''];
        }
      ?>

      <?php foreach ($expRows as $i => $exp): ?>
      <div style="background:var(--bg-surface);padding:14px;border-radius:var(--radius-sm);margin-bottom:14px;border:1px solid var(--border-subtle);">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
          <div class="form-group">
            <label style="font-size:0.8rem;">Organization / Company</label>
            <input name="experience_company[]" maxlength="150" value="<?= escape($exp['company'] ?? '') ?>" placeholder="e.g. Acme Corp">
          </div>
          <div class="form-group">
            <label style="font-size:0.8rem;">Position Title</label>
            <input name="experience_role[]" maxlength="150" value="<?= escape($exp['role'] ?? '') ?>" placeholder="e.g. Backend Engineering Intern">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
          <div class="form-group">
            <label style="font-size:0.8rem;">Start Date</label>
            <input name="experience_start[]" maxlength="50" value="<?= escape($exp['start_date'] ?? '') ?>" placeholder="e.g. May 2025">
          </div>
          <div class="form-group">
            <label style="font-size:0.8rem;">End Date</label>
            <input name="experience_end[]" maxlength="50" value="<?= escape($exp['end_date'] ?? '') ?>" placeholder="e.g. August 2025 or Present">
          </div>
        </div>
        <div class="form-group">
          <label style="font-size:0.8rem;">Bullet Points / Description (one per line)</label>
          <textarea name="experience_desc[]" rows="3" maxlength="1000" placeholder="Built scalable SQLite data ingestion service&#10;Reduced query response times by 35%"><?= escape($exp['description'] ?? '') ?></textarea>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Projects Entries -->
    <div class="auth-panel" style="margin-bottom:32px;">
      <h2 style="font-size:1.2rem;margin-bottom:8px;">5. Projects &amp; Open Source</h2>
      <p class="hint" style="margin-bottom:16px;">List key repositories, applications, or systems you have built.</p>

      <?php
        $projRows = $resume['projects'] ?? [];
        if (empty($projRows)) {
          $projRows = [
            ['name' => '', 'technologies' => '', 'description' => '', 'link' => '']
          ];
        } else {
          $projRows[] = ['name' => '', 'technologies' => '', 'description' => '', 'link' => ''];
        }
      ?>

      <?php foreach ($projRows as $i => $proj): ?>
      <div style="background:var(--bg-surface);padding:14px;border-radius:var(--radius-sm);margin-bottom:14px;border:1px solid var(--border-subtle);">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
          <div class="form-group">
            <label style="font-size:0.8rem;">Project Name</label>
            <input name="project_name[]" maxlength="150" value="<?= escape($proj['name'] ?? '') ?>" placeholder="e.g. EIDOLON PATHWAY">
          </div>
          <div class="form-group">
            <label style="font-size:0.8rem;">Tech Stack</label>
            <input name="project_tech[]" maxlength="200" value="<?= escape($proj['technologies'] ?? '') ?>" placeholder="e.g. PHP 8.4, SQLite, CSS3">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:10px;">
          <label style="font-size:0.8rem;">Project Bullets / Description (one per line)</label>
          <textarea name="project_desc[]" rows="2" maxlength="1000" placeholder="Architected zero-dependency evidence inspection pipeline&#10;Achieved 100% test coverage across 300+ assertions"><?= escape($proj['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label style="font-size:0.8rem;">Repository or Live Link</label>
          <input name="project_link[]" maxlength="200" value="<?= escape($proj['link'] ?? '') ?>" placeholder="e.g. https://github.com/octocat/project">
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:14px;align-items:center;">
      <button class="button" type="submit" style="padding:12px 28px;">
        Save Resume Draft
      </button>
      <?php if ($resume !== null && !empty($resume['id'])): ?>
      <a class="button secondary" href="/resume/preview?id=<?= (int) $resume['id'] ?>" target="_blank">
        Open ATS Print Preview &nearr;
      </a>
      <?php endif; ?>
    </div>
  </form>

<?php elseif ($screen === 'resume_preview'): ?>
  <!-- Control Bar (hidden during browser print) -->
  <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;background:var(--bg-secondary);padding:16px 20px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
    <div>
      <a href="/resume?id=<?= (int) $resume['id'] ?>" style="font-weight:700;">&larr; Return to Resume Editor</a>
      <p style="margin:4px 0 0;font-size:0.85rem;color:var(--text-muted);">
        Single-column ATS format &middot; Clean print/PDF layout &middot; Zero untrusted scripts
      </p>
    </div>
    <div style="display:flex;gap:12px;">
      <button class="button" onclick="window.print()" style="padding:9px 20px;">
        Print / Save to PDF
      </button>
    </div>
  </div>

  <!-- Standard Single-Column ATS Printable Resume Paper -->
  <article class="resume-document" id="resume-sheet">
    <header class="resume-header">
      <h1><?= escape($resume['user_name'] ?? $user['name']) ?></h1>
      <p class="resume-contact">
        <?= escape($resume['user_email'] ?? $user['email']) ?>
        <?php if (!empty($resume['college'])): ?>
        &bull; <?= escape($resume['college']) ?>
        <?php endif; ?>
        <?php if (!empty($resume['github_username'])): ?>
        &bull; github.com/<?= escape($resume['github_username']) ?>
        <?php endif; ?>
      </p>
    </header>

    <!-- Professional Summary -->
    <?php if (!empty($resume['summary'])): ?>
    <section class="resume-section">
      <h2 class="resume-section-title">Professional Summary</h2>
      <p style="margin:0;font-size:0.92rem;color:#374151;line-height:1.6;"><?= escape($resume['summary']) ?></p>
    </section>
    <?php endif; ?>

    <!-- Technical Skills -->
    <?php if (!empty($resume['skills'])): ?>
    <section class="resume-section">
      <h2 class="resume-section-title">Technical Competencies</h2>
      <p class="resume-skills-list">
        <?= escape(is_array($resume['skills']) ? implode(' &bull; ', $resume['skills']) : $resume['skills']) ?>
      </p>
    </section>
    <?php endif; ?>

    <!-- Experience -->
    <?php if (!empty($resume['experience'])): ?>
    <section class="resume-section">
      <h2 class="resume-section-title">Work &amp; Internship Experience</h2>
      <?php foreach ($resume['experience'] as $exp): ?>
      <?php if (!empty($exp['company']) || !empty($exp['role'])): ?>
      <div class="resume-item">
        <div class="resume-item-header">
          <span><?= escape($exp['role'] ?? '') ?></span>
          <span><?= escape($exp['company'] ?? '') ?></span>
        </div>
        <div class="resume-item-sub">
          <span><?= escape($exp['start_date'] ?? '') ?> &ndash; <?= escape($exp['end_date'] ?? 'Present') ?></span>
        </div>
        <?php if (!empty($exp['description'])): ?>
        <ul class="resume-bullets">
          <?php foreach (explode("\n", (string) $exp['description']) as $bullet): ?>
          <?php if (trim($bullet) !== ''): ?>
          <li><?= escape(ltrim(trim($bullet), "-*• \t")) ?></li>
          <?php endif; ?>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- Projects -->
    <?php if (!empty($resume['projects'])): ?>
    <section class="resume-section">
      <h2 class="resume-section-title">Projects &amp; Open Source</h2>
      <?php foreach ($resume['projects'] as $proj): ?>
      <?php if (!empty($proj['name'])): ?>
      <div class="resume-item">
        <div class="resume-item-header">
          <span><?= escape($proj['name']) ?></span>
          <?php if (!empty($proj['technologies'])): ?>
          <span style="font-weight:400;font-size:0.85rem;color:#4b5563;">[<?= escape($proj['technologies']) ?>]</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($proj['link'])): ?>
        <div class="resume-item-sub">
          <span><?= escape($proj['link']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($proj['description'])): ?>
        <ul class="resume-bullets">
          <?php foreach (explode("\n", (string) $proj['description']) as $bullet): ?>
          <?php if (trim($bullet) !== ''): ?>
          <li><?= escape(ltrim(trim($bullet), "-*• \t")) ?></li>
          <?php endif; ?>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- Education -->
    <?php if (!empty($resume['education'])): ?>
    <section class="resume-section">
      <h2 class="resume-section-title">Education</h2>
      <?php foreach ($resume['education'] as $edu): ?>
      <?php if (!empty($edu['institution']) || !empty($edu['degree'])): ?>
      <div class="resume-item">
        <div class="resume-item-header">
          <span><?= escape($edu['institution'] ?? '') ?></span>
          <span><?= escape($edu['year'] ?? '') ?></span>
        </div>
        <div class="resume-item-sub">
          <span><?= escape($edu['degree'] ?? '') ?></span>
          <?php if (!empty($edu['gpa'])): ?>
          <span>GPA: <?= escape($edu['gpa']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- Verified Code Evidence & Audit Provenance -->
    <?php if (!empty($resume['assessment_repo'])): ?>
    <section class="resume-section">
      <h2 class="resume-section-title">Verified Code Evidence &amp; Audit Provenance</h2>
      <div class="resume-evidence-box">
        <div class="evidence-grid">
          <div>
            <strong>Verified Repository:</strong> <code><?= escape($resume['assessment_repo']) ?></code>
          </div>
          <div>
            <strong>Target Role Track:</strong> <?= escape(ucfirst($resume['assessment_role'] ?? '')) ?>
          </div>
          <div>
            <strong>Pinned Commit SHA:</strong> <code><?= escape(substr($resume['assessment_sha'] ?? '', 0, 12)) ?></code>
          </div>
          <div>
            <strong>Evaluation Standard:</strong> <?= escape($resume['assessment_rubric'] ?? '') ?>
          </div>
        </div>

        <?php if (!empty($resume['assessment_tasks'])): ?>
        <div style="margin-top:8px;">
          <strong style="font-size:0.82rem;text-transform:uppercase;color:#475569;display:block;margin-bottom:4px;">
            Verified Milestone Outcomes from Inspected Lexical Samples:
          </strong>
          <ul style="margin:0;padding-left:18px;font-size:0.85rem;color:#334155;">
            <?php foreach ($resume['assessment_tasks'] as $task): ?>
            <li style="margin-bottom:3px;">
              <strong>Priority <?= (int) $task['priority'] ?>:</strong> <?= escape($task['title']) ?>
              &mdash; <span style="color:#64748b;"><?= escape($task['acceptance']) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </article>

<?php elseif ($screen === 'dashboard'): ?>
  <!-- Student Dashboard -->
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab active" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab" href="/applications">My Applications</a>
    <a class="nav-role-tab" href="/profile">My Profile</a>
    <a class="nav-role-tab" href="/resume">Resume Builder</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Student Portal · Evidence Engine</p>
    <h1>Welcome back, <?= escape($user['name']) ?>.</h1>
    <p>Assess public project code against role rubrics or track your verified portfolio progression.</p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php elseif (is_string($flashSuccess)): ?>
  <div class="notice success" role="status"><p><?= escape($flashSuccess) ?></p></div>
  <?php endif; ?>

  <div class="account-strip" role="region" aria-labelledby="account-title">
    <div>
      <h2 id="account-title">Demonstration Account</h2>
      <p><?= escape($user['email']) ?></p>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
      <span class="badge">Student</span>
      <span class="badge muted">SQLite Store v4</span>
    </div>
  </div>

  <!-- Assessment Submission Panel -->
  <section class="auth-panel" aria-labelledby="assess-title" style="margin-bottom:36px;">
    <p class="eyebrow">Automated Portfolio Assessment</p>
    <h2 id="assess-title">Inspect a public GitHub repository</h2>
    <p class="lead">Submit an accessible repository. The agent safely pins the commit SHA and executes bounded static lexical inspections.</p>
    
    <form action="/assess" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      
      <div class="form-group">
        <label for="repository">Public GitHub repository identifier</label>
        <input id="repository" name="repository" type="text" maxlength="180" placeholder="owner/repository or https://github.com/owner/repo" required aria-describedby="repo-help">
        <p class="hint" id="repo-help">Must be a public repository. Zero code, tests or dependencies are executed on this system.</p>
      </div>

      <div class="form-group">
        <label for="target_role">Target developer competency role</label>
        <select id="target_role" name="target_role" required>
          <option value="frontend">Frontend Developer (UI, DOM, Components)</option>
          <option value="backend" selected>Backend Developer (Data handling, API, Persistence)</option>
          <option value="fullstack">Fullstack Developer (End-to-End Interaction)</option>
        </select>
      </div>

      <button class="button" type="submit" style="align-self:flex-start;">
        Execute Bounded Assessment
      </button>
    </form>
  </section>

  <!-- Assessment History Table -->
  <?php if (!empty($assessments)): ?>
  <section aria-labelledby="history-title" style="margin:36px 0;">
    <p class="eyebrow">Historical Inspections</p>
    <h2 id="history-title">Assessment History</h2>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Repository</th>
            <th>Role</th>
            <th>Status</th>
            <th>Analyzed At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assessments as $a): ?>
          <tr>
            <td><strong>#<?= (int) $a['id'] ?></strong></td>
            <td><code><?= escape($a['repository']) ?></code></td>
            <td><span class="badge muted"><?= escape(ucfirst($a['target_role'])) ?></span></td>
            <td>
              <span class="badge <?= $a['state'] === 'completed' ? 'badge-observed' : 'badge-failed' ?>">
                <?= escape(ucfirst($a['state'])) ?>
              </span>
            </td>
            <td><?= escape(date('Y-m-d H:i', (int) $a['created_at'])) ?></td>
            <td>
              <?php if ($a['state'] === 'completed'): ?>
              <a href="/assess?id=<?= (int) $a['id'] ?>" style="font-weight:700;">View Findings &rarr;</a>
              <?php elseif ($a['state'] === 'failed'): ?>
              <a href="/assess?id=<?= (int) $a['id'] ?>">View Error &rarr;</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php else: ?>
  <div class="notice" aria-labelledby="no-assess-title">
    <h2 id="no-assess-title">No assessments recorded yet</h2>
    <p>Submit your first public GitHub repository above to receive evidence-based observations and a three-milestone improvement roadmap.</p>
  </div>
  <?php endif; ?>

  <!-- Required Demonstration Notices & Sample Journey -->
  <section class="notice" aria-labelledby="demo-title">
    <h2 id="demo-title">Demonstration content — illustrative, not assessed</h2>
    <p>The examples below are static product previews. They are not live internships, personal skill assessments, or completed applications.</p>
  </section>

  <section class="journey" aria-label="Example student journey">
    <article>
      <span class="step">01</span>
      <h2>Build your profile</h2>
      <p>Example task: describe one project, your contribution and the problem it solves.</p>
      <a class="button secondary" href="/profile" style="margin-top:10px;padding:6px 12px;font-size:0.8rem;">Edit Profile &rarr;</a>
    </article>
    <article>
      <span class="step">02</span>
      <h2>Explore opportunities</h2>
      <p>Example role: frontend internship. No live job listings are connected in this slice.</p>
      <a class="button secondary" href="/jobs" style="margin-top:10px;padding:6px 12px;font-size:0.8rem;">Browse Jobs &rarr;</a>
    </article>
    <article>
      <span class="step">03</span>
      <h2>Track your progress</h2>
      <p>Future application tracking will show recorded statuses. No application metrics are available yet.</p>
      <a class="button secondary" href="/applications" style="margin-top:10px;padding:6px 12px;font-size:0.8rem;">View Applications &rarr;</a>
    </article>
  </section>

<?php elseif ($screen === 'company_login'): ?>
  <div class="auth-layout">
    <section class="hero">
      <p class="eyebrow">EIDOLON PATHWAY &middot; Verified Employer Workspace</p>
      <h1>Recruit verified software engineering talent.</h1>
      <p>Evaluate candidates through commit-pinned GitHub code evidence, objective rubric benchmarks, and verified skill profiles.</p>

      <div class="notice">
        <h2>Multi-tenant employer isolation</h2>
        <p>Positions, applications, candidate evaluation notes, and recruitment decisions are strictly isolated to your verified company organization.</p>
      </div>

      <div style="margin-top:24px;display:flex;gap:18px;flex-wrap:wrap;">
        <span class="badge">&#x2713; Verified Organization</span>
        <span class="badge">&#x2713; Tenant Isolated</span>
        <span class="badge">&#x2713; Direct Applicant Review</span>
      </div>
    </section>

    <section class="auth-panel" aria-labelledby="company-form-title">
      <h2 id="company-form-title">Company Portal Access</h2>
      <p class="lead">Sign in with your verified company staff credentials.</p>

      <?php
        $flashError = $_SESSION['flash_error'] ?? null;
        $flashSuccess = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash']);
      ?>
      <?php if (is_string($flashError)): ?>
      <p class="notice error" role="alert"><?= escape($flashError) ?></p>
      <?php elseif (is_string($flashSuccess)): ?>
      <p class="notice success" role="status"><?= escape($flashSuccess) ?></p>
      <?php endif; ?>

      <form action="/company/login" method="post">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

        <div class="form-group">
          <label for="company-email">Work Email</label>
          <input id="company-email" name="email" type="email" autocomplete="email" maxlength="254" placeholder="recruiter@company.test" required>
        </div>

        <div class="form-group">
          <label for="company-password">Password</label>
          <input id="company-password" name="password" type="password" autocomplete="current-password" maxlength="72" required>
        </div>

        <button class="button" type="submit" style="margin-top:8px;">Sign In to Company Portal</button>
      </form>

      <p class="switch">
        Student applicant?
        <a href="/login">Student Sign In &rarr;</a>
      </p>
    </section>
  </div>

<?php elseif ($screen === 'company_dashboard'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab active" href="/company/dashboard">Company Overview</a>
    <a class="nav-role-tab" href="/company/jobs/new">+ Post New Position</a>
    <a class="nav-role-tab" href="/company/applicants">Candidate Review</a>
  </div>

  <section class="hero">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
      <div>
        <p class="eyebrow">Company Recruitment Hub &middot; <?= escape($org['org_name'] ?? 'Company') ?></p>
        <h1><?= escape($org['org_name'] ?? 'Company Portal') ?></h1>
        <p style="margin-top:4px;">
          <span class="badge badge-observed">Verified Organization</span>
          <span class="badge" style="margin-left:8px;">Role: <?= escape($org['member_role'] ?? 'STAFF') ?></span>
          <span class="badge muted" style="margin-left:8px;">Slug: <?= escape($org['org_slug'] ?? '') ?></span>
        </p>
      </div>
      <div>
        <a class="button" href="/company/jobs/new">+ Post New Position</a>
      </div>
    </div>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php elseif (is_string($flashSuccess)): ?>
  <div class="notice success" role="status"><p><?= escape($flashSuccess) ?></p></div>
  <?php endif; ?>

  <!-- Metrics Cards -->
  <div class="dashboard-stats" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:28px;">
    <?php
      $jobsList = $jobs ?? [];
      $totalJobs = count($jobsList);
      $activeJobs = count(array_filter($jobsList, fn($j) => ($j['status'] ?? '') === 'active'));
      $totalApplicants = array_sum(array_map(fn($j) => (int) ($j['applicant_count'] ?? 0), $jobsList));
    ?>
    <div class="card" style="padding:18px;">
      <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);font-weight:600;">Total Positions</span>
      <p style="font-size:2rem;font-weight:700;margin:8px 0 0;"><?= $totalJobs ?></p>
    </div>
    <div class="card" style="padding:18px;">
      <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);font-weight:600;">Active Listings</span>
      <p style="font-size:2rem;font-weight:700;margin:8px 0 0;color:var(--emerald-400);"><?= $activeJobs ?></p>
    </div>
    <div class="card" style="padding:18px;">
      <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);font-weight:600;">Total Applications</span>
      <p style="font-size:2rem;font-weight:700;margin:8px 0 0;"><?= $totalApplicants ?></p>
    </div>
  </div>

  <!-- Job Listings Table -->
  <section class="card" style="margin-bottom:32px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h2 style="font-size:1.3rem;margin:0;">Posted Job Openings</h2>
      <a class="button secondary" style="padding:6px 12px;font-size:0.8rem;" href="/company/applicants">View All Applicants &rarr;</a>
    </div>

    <?php if (!empty($jobsList)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Position Title</th>
            <th>Category</th>
            <th>Location</th>
            <th>Status</th>
            <th>Applicants</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jobsList as $job): ?>
          <tr>
            <td>
              <strong><a href="/company/jobs/edit?id=<?= (int) $job['id'] ?>"><?= escape($job['title']) ?></a></strong>
            </td>
            <td><span class="badge muted"><?= escape(ucfirst($job['role_category'])) ?></span></td>
            <td><?= escape($job['location']) ?></td>
            <td>
              <span class="badge <?= ($job['status'] ?? '') === 'active' ? 'badge-observed' : 'badge-failed' ?>">
                <?= escape(ucfirst($job['status'] ?? '')) ?>
              </span>
            </td>
            <td>
              <a href="/company/applicants?job_id=<?= (int) $job['id'] ?>" style="font-weight:700;">
                <?= (int) ($job['applicant_count'] ?? 0) ?> candidates &rarr;
              </a>
            </td>
            <td>
              <div style="display:flex;gap:8px;">
                <a class="button secondary" style="padding:4px 10px;font-size:0.8rem;" href="/company/jobs/edit?id=<?= (int) $job['id'] ?>">Edit</a>
                <a class="button secondary" style="padding:4px 10px;font-size:0.8rem;" href="/company/applicants?job_id=<?= (int) $job['id'] ?>">Review</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:16px 0;">
      <p>No job positions posted yet. Publish your first opportunity to receive verified student applications.</p>
      <p style="margin-top:12px;"><a class="button" href="/company/jobs/new">+ Create First Position</a></p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'company_job_form'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/company/dashboard">Company Overview</a>
    <a class="nav-role-tab <?= $job === null ? 'active' : '' ?>" href="/company/jobs/new">+ Post New Position</a>
    <a class="nav-role-tab" href="/company/applicants">Candidate Review</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Company Recruitment Hub &middot; <?= escape($org['org_name'] ?? 'Company') ?></p>
    <h1><?= $job !== null ? 'Edit Job Opening' : 'Post New Position' ?></h1>
    <p>Describe your technical role requirements, expected stack, and location arrangements.</p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php elseif (is_string($flashSuccess)): ?>
  <div class="notice success" role="status"><p><?= escape($flashSuccess) ?></p></div>
  <?php endif; ?>

  <section class="auth-panel" style="max-width:800px;margin:0 auto 40px;">
    <form action="<?= $job !== null ? '/company/jobs/edit' : '/company/jobs/new' ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <?php if ($job !== null): ?>
      <input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label for="job-title">Job / Internship Title</label>
        <input id="job-title" name="title" maxlength="140" value="<?= escape($job['title'] ?? '') ?>" placeholder="e.g. Backend Software Engineering Intern" required>
        <p class="hint">3 to 140 characters.</p>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label for="role-category">Role Category</label>
          <select id="role-category" name="role_category">
            <?php
              $selectedCat = $job['role_category'] ?? 'backend';
              foreach (['backend' => 'Backend Engineering', 'frontend' => 'Frontend Engineering', 'fullstack' => 'Fullstack Engineering', 'general' => 'General Software Engineering'] as $catKey => $catLabel):
            ?>
            <option value="<?= $catKey ?>" <?= $selectedCat === $catKey ? 'selected' : '' ?>><?= $catLabel ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="job-location">Location</label>
          <input id="job-location" name="location" maxlength="100" value="<?= escape($job['location'] ?? 'Remote') ?>" placeholder="e.g. Remote or San Francisco, CA" required>
        </div>
      </div>

      <div class="form-group">
        <label for="job-status">Listing Status</label>
        <select id="job-status" name="status">
          <?php
            $currentStatus = $job['status'] ?? 'active';
            foreach (['active' => 'Active (Accepting applications)', 'draft' => 'Draft (Hidden)', 'closed' => 'Closed (Archived)'] as $sKey => $sLabel):
          ?>
          <option value="<?= $sKey ?>" <?= $currentStatus === $sKey ? 'selected' : '' ?>><?= $sLabel ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="job-skills">Required Skills &amp; Technologies (comma-separated)</label>
        <input id="job-skills" name="skills" maxlength="500" value="<?= escape($job['skills'] ?? '') ?>" placeholder="e.g. PHP 8.4, SQLite, REST APIs, Git" required>
        <p class="hint">Used for matching and candidate filtering.</p>
      </div>

      <div class="form-group">
        <label for="job-description">Position Description &amp; Responsibilities</label>
        <textarea id="job-description" name="description" rows="7" maxlength="5000" placeholder="Describe the role, project expectations, and team structure..." required><?= escape($job['description'] ?? '') ?></textarea>
        <p class="hint">10 to 5000 characters.</p>
      </div>

      <div style="display:flex;gap:14px;margin-top:24px;">
        <button class="button" type="submit"><?= $job !== null ? 'Save Changes' : 'Publish Position' ?></button>
        <a class="button secondary" href="/company/dashboard">Cancel</a>
      </div>
    </form>
  </section>

<?php elseif ($screen === 'company_applicants'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/company/dashboard">Company Overview</a>
    <a class="nav-role-tab" href="/company/jobs/new">+ Post New Position</a>
    <a class="nav-role-tab active" href="/company/applicants">Candidate Review</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Company Recruitment Hub &middot; <?= escape($org['org_name'] ?? 'Company') ?></p>
    <h1>Candidate Review &amp; Shortlist</h1>
    <p>Review submitted applications, inspect student qualifications, and update review decisions.</p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php elseif (is_string($flashSuccess)): ?>
  <div class="notice success" role="status"><p><?= escape($flashSuccess) ?></p></div>
  <?php endif; ?>

  <!-- Filter Bar -->
  <div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;align-items:center;">
    <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Filter by Position:</span>
    <a class="button <?= ($selectedJobId ?? null) === null ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:0.85rem;" href="/company/applicants">
      All Openings (<?= count($applicants ?? []) ?>)
    </a>
    <?php foreach (($jobs ?? []) as $j): ?>
    <a class="button <?= ($selectedJobId ?? null) === (int)$j['id'] ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:0.85rem;" href="/company/applicants?job_id=<?= (int) $j['id'] ?>">
      <?= escape($j['title']) ?> (<?= (int) ($j['applicant_count'] ?? 0) ?>)
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Applicants Table -->
  <?php if (!empty($applicants)): ?>
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Candidate</th>
          <th>College / Institution</th>
          <th>Position Applied</th>
          <th>GitHub Evidence</th>
          <th>Notes</th>
          <th>Applied Date</th>
          <th>Status</th>
          <th>Review Decision</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($applicants as $app): 
          $status = $app['status'];
          $badgeClass = match ($status) {
            'shortlisted' => 'badge-observed',
            'rejected' => 'badge-failed',
            default => 'muted',
          };
          $statusLabel = match ($status) {
            'shortlisted' => 'Shortlisted',
            'rejected' => 'Declined',
            default => 'Under Review',
          };
        ?>
        <tr>
          <td>
            <strong><?= escape($app['student_name']) ?></strong><br>
            <span style="font-size:0.8rem;color:var(--text-muted);"><?= escape($app['student_email']) ?></span>
          </td>
          <td><?= escape($app['college'] ?: 'Not specified') ?></td>
          <td>
            <a href="/jobs/view?id=<?= (int) $app['job_id'] ?>"><?= escape($app['job_title']) ?></a><br>
            <span class="badge muted" style="font-size:0.75rem;"><?= escape(ucfirst($app['role_category'])) ?></span>
          </td>
          <td>
            <?php if (!empty($app['github_username'])): ?>
            <code>github.com/<?= escape($app['github_username']) ?></code>
            <?php else: ?>
            <span style="color:var(--text-muted);">&mdash;</span>
            <?php endif; ?>
          </td>
          <td style="max-width:200px;font-size:0.85rem;color:var(--text-muted);">
            <?= escape($app['notes'] ?: 'No notes') ?>
          </td>
          <td><?= escape(date('Y-m-d H:i', (int) $app['created_at'])) ?></td>
          <td>
            <span class="badge <?= $badgeClass ?>"><?= escape($statusLabel) ?></span>
          </td>
          <td>
            <form action="/company/applicants/status" method="post" style="display:flex;gap:6px;margin:0;">
              <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
              <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
              <?php if (($selectedJobId ?? null) !== null): ?>
              <input type="hidden" name="job_id" value="<?= (int) $selectedJobId ?>">
              <?php endif; ?>
              
              <?php if ($status !== 'shortlisted'): ?>
              <button class="button" type="submit" name="status" value="shortlisted" style="padding:4px 10px;font-size:0.75rem;background:var(--emerald-600);">Shortlist</button>
              <?php endif; ?>
              
              <?php if ($status !== 'rejected'): ?>
              <button class="button secondary" type="submit" name="status" value="rejected" style="padding:4px 10px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);">Decline</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="notice" style="margin:24px 0;">
    <h2>No applications found</h2>
    <p>There are no candidate applications for the selected criteria yet.</p>
  </div>
  <?php endif; ?>

<?php elseif ($screen === 'college_login'): ?>
  <div class="auth-layout">
    <section class="hero">
      <p class="eyebrow">EIDOLON PATHWAY &middot; Verified Academic Network</p>
      <h1>Privacy-first campus placement intelligence.</h1>
      <p>Monitor student career readiness, verified portfolio milestones, and placement outcomes with explicit student consent enforcement.</p>

      <div class="notice">
        <h2>Consent-based academic access</h2>
        <p>In adherence to strict privacy controls, candidate resumes and application histories are exposed only for students who have explicitly granted placement cell authorization.</p>
      </div>

      <div style="margin-top:24px;display:flex;gap:18px;flex-wrap:wrap;">
        <span class="badge">&#x2713; Verified College</span>
        <span class="badge">&#x2713; Student Consent Enforced</span>
        <span class="badge">&#x2713; Placement Analytics</span>
      </div>
    </section>

    <section class="auth-panel" aria-labelledby="college-form-title">
      <h2 id="college-form-title">College Placement Sign In</h2>
      <p class="lead">Sign in with authorized placement cell staff credentials.</p>

      <?php
        $flashError = $_SESSION['flash_error'] ?? null;
        $flashSuccess = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash']);
      ?>
      <?php if (is_string($flashError)): ?>
      <p class="notice error" role="alert"><?= escape($flashError) ?></p>
      <?php elseif (is_string($flashSuccess)): ?>
      <p class="notice success" role="status"><?= escape($flashSuccess) ?></p>
      <?php endif; ?>

      <form action="/college/login" method="post">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

        <div class="form-group">
          <label for="college-email">Institutional Email</label>
          <input id="college-email" name="email" type="email" autocomplete="email" maxlength="254" placeholder="placement@college.edu" required>
        </div>

        <div class="form-group">
          <label for="college-password">Password</label>
          <input id="college-password" name="password" type="password" autocomplete="current-password" maxlength="72" required>
        </div>

        <button class="button" type="submit" style="margin-top:8px;">Sign In to College Portal</button>
      </form>

      <p class="switch">
        Student seeking internships?
        <a href="/login">Student Sign In &rarr;</a>
      </p>
    </section>
  </div>

<?php elseif ($screen === 'college_dashboard'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab active" href="/college/dashboard">Campus Placement Intelligence</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Academic Placement Network &middot; <?= escape($org['org_name'] ?? 'College') ?></p>
    <h1><?= escape($org['org_name'] ?? 'College Portal') ?></h1>
    <p style="margin-top:4px;">
      <span class="badge badge-observed">Verified Academic Organization</span>
      <span class="badge" style="margin-left:8px;">Role: <?= escape($org['member_role'] ?? 'STAFF') ?></span>
      <span class="badge muted" style="margin-left:8px;">Slug: <?= escape($org['org_slug'] ?? '') ?></span>
    </p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php elseif (is_string($flashSuccess)): ?>
  <div class="notice success" role="status"><p><?= escape($flashSuccess) ?></p></div>
  <?php endif; ?>

  <!-- KPI Metrics Cards -->
  <div class="dashboard-stats" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:28px;">
    <?php
      $studList = $students ?? [];
      $totalAssociated = count($studList);
      $consentedCount = count(array_filter($studList, fn($s) => (int)($s['college_share_consent'] ?? 0) === 1));
      $shortlistedCount = count(array_filter($studList, fn($s) => (int)($s['shortlisted_count'] ?? 0) > 0));
      $consentRate = $totalAssociated > 0 ? (int) round(($consentedCount / $totalAssociated) * 100) : 0;
    ?>
    <div class="card" style="padding:18px;">
      <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);font-weight:600;">Associated Students</span>
      <p style="font-size:2rem;font-weight:700;margin:8px 0 0;"><?= $totalAssociated ?></p>
    </div>
    <div class="card" style="padding:18px;">
      <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);font-weight:600;">Consent Granted</span>
      <p style="font-size:2rem;font-weight:700;margin:8px 0 0;color:var(--emerald-400);"><?= $consentedCount ?></p>
    </div>
    <div class="card" style="padding:18px;">
      <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);font-weight:600;">Placed / Shortlisted</span>
      <p style="font-size:2rem;font-weight:700;margin:8px 0 0;color:var(--emerald-400);"><?= $shortlistedCount ?></p>
    </div>
    <div class="card" style="padding:18px;">
      <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);font-weight:600;">Consent Rate</span>
      <p style="font-size:2rem;font-weight:700;margin:8px 0 0;"><?= $consentRate ?>%</p>
    </div>
  </div>

  <div class="notice" style="margin-bottom:24px;">
    <h2>Consent-based privacy safeguards active</h2>
    <p>Detailed placement records and application histories are visible only for students who have explicitly opted in via their student profile. Non-consenting student profiles display anonymized identities with confidential application metrics.</p>
  </div>

  <!-- Student Roster Table -->
  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.3rem;margin-bottom:16px;">Campus Student Roster (<?= count($studList) ?>)</h2>

    <?php if (!empty($studList)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Candidate Name</th>
            <th>Email</th>
            <th>Skills &amp; Technologies</th>
            <th>GitHub Evidence</th>
            <th>Consent Status</th>
            <th>Applications</th>
            <th>Shortlists</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($studList as $s): 
            $hasConsent = (int) ($s['college_share_consent'] ?? 0) === 1;
          ?>
          <tr>
            <td><strong><?= escape($s['student_name']) ?></strong></td>
            <td>
              <?php if ($hasConsent): ?>
              <?= escape($s['student_email']) ?>
              <?php else: ?>
              <span class="badge muted" style="font-size:0.75rem;">Consent Withheld</span>
              <?php endif; ?>
            </td>
            <td><?= escape($s['skills'] ?: 'Not specified') ?></td>
            <td>
              <?php if (!empty($s['github_username'])): ?>
              <code><?= escape($s['github_username']) ?></code>
              <?php else: ?>
              <span style="color:var(--text-muted);">&mdash;</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $hasConsent ? 'badge-observed' : 'badge-failed' ?>">
                <?= $hasConsent ? 'Consented' : 'Confidential' ?>
              </span>
            </td>
            <td><?= $hasConsent ? (int) ($s['applications_count'] ?? 0) : '&mdash;' ?></td>
            <td>
              <?php if ($hasConsent && (int) ($s['shortlisted_count'] ?? 0) > 0): ?>
              <strong style="color:var(--emerald-400);"><?= (int) $s['shortlisted_count'] ?> shortlisted</strong>
              <?php elseif ($hasConsent): ?>
              0
              <?php else: ?>
              &mdash;
              <?php endif; ?>
            </td>
            <td>
              <?php if ($hasConsent): ?>
              <a class="button secondary" style="padding:4px 10px;font-size:0.8rem;" href="/college/students/view?id=<?= (int) $s['student_id'] ?>">
                View Records &rarr;
              </a>
              <?php else: ?>
              <span style="font-size:0.8rem;color:var(--text-muted);">Private</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:16px 0;">
      <p>No students have linked their profiles to this college yet. Students can associate with your verified institution in their profile settings.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'college_student_detail'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/college/dashboard">&larr; Return to College Roster</a>
  </div>

  <?php
    $student = $placement['student'] ?? [];
    $apps = $placement['applications'] ?? [];
    $resume = $placement['resume'] ?? null;
  ?>

  <section class="hero">
    <p class="eyebrow">Consented Candidate Record &middot; <?= escape($org['org_name'] ?? 'College') ?></p>
    <h1><?= escape($student['name'] ?? 'Student') ?></h1>
    <p style="margin-top:4px;">
      <span class="badge badge-observed">Student Consent Verified</span>
      <span class="badge muted" style="margin-left:8px;"><?= escape($student['email'] ?? '') ?></span>
      <?php if (!empty($student['github_username'])): ?>
      <span class="badge" style="margin-left:8px;"><code>github.com/<?= escape($student['github_username']) ?></code></span>
      <?php endif; ?>
    </p>
  </section>

  <!-- Applications History -->
  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.3rem;margin-bottom:14px;">Internship &amp; Placement Applications (<?= count($apps) ?>)</h2>

    <?php if (!empty($apps)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Application</th>
            <th>Company</th>
            <th>Role Title</th>
            <th>Category</th>
            <th>Location</th>
            <th>Submitted</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($apps as $app): 
            $status = $app['status'];
            $badgeClass = match ($status) {
              'shortlisted' => 'badge-observed',
              'rejected' => 'badge-failed',
              default => 'muted',
            };
            $statusLabel = match ($status) {
              'shortlisted' => 'Shortlisted',
              'rejected' => 'Declined',
              default => 'Under Review',
            };
          ?>
          <tr>
            <td><strong>#<?= (int) $app['application_id'] ?></strong></td>
            <td><strong><?= escape($app['company_name']) ?></strong></td>
            <td><?= escape($app['job_title']) ?></td>
            <td><span class="badge muted"><?= escape(ucfirst($app['role_category'])) ?></span></td>
            <td><?= escape($app['location']) ?></td>
            <td><?= escape(date('Y-m-d H:i', (int) $app['created_at'])) ?></td>
            <td><span class="badge <?= $badgeClass ?>"><?= escape($statusLabel) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No job applications submitted yet by this student.</p>
    </div>
    <?php endif; ?>
  </section>

  <!-- Latest Resume Overview -->
  <?php if ($resume !== null): ?>
  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.3rem;margin-bottom:14px;">Verified Candidate Resume Summary</h2>
    <div style="background:var(--bg-surface);padding:16px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
      <h3 style="margin:0 0 8px;font-size:1.1rem;"><?= escape($resume['title']) ?></h3>
      <p style="color:var(--text-secondary);margin-bottom:12px;font-size:0.95rem;line-height:1.5;">
        <?= escape($resume['summary']) ?>
      </p>
      <?php if (!empty($resume['assessment_id'])): ?>
      <p style="margin:0;font-size:0.85rem;">
        <span class="badge badge-observed">&#x2713; Cryptographic GitHub Evidence Citation Attached (Assessment #<?= (int) $resume['assessment_id'] ?>)</span>
      </p>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>


<?php elseif ($screen === 'admin_login'): ?>
  <div class="auth-layout">
    <section class="hero">
      <p class="eyebrow">EIDOLON PATHWAY &middot; Platform Administration</p>
      <h1>Platform Governance &amp; Security Control.</h1>
      <p>Manage verified organization approvals, enforce tenant isolation, and inspect tamper-evident audit logs.</p>

      <div class="notice">
        <h2>Local CLI admin provisioning</h2>
        <p>In accordance with the security release gate, administrator credentials cannot be registered through public forms. Provision admin credentials via <code>bin/bootstrap_admin.php</code>.</p>
      </div>
    </section>

    <section class="auth-panel" aria-labelledby="admin-form-title">
      <h2 id="admin-form-title">Administrator Sign In</h2>
      <p class="lead">Sign in with bootstrapped platform admin credentials.</p>

      <?php
        $flashError = $_SESSION['flash_error'] ?? null;
        $flashSuccess = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash']);
      ?>
      <?php if (is_string($flashError)): ?>
      <p class="notice error" role="alert"><?= escape($flashError) ?></p>
      <?php elseif (is_string($flashSuccess)): ?>
      <p class="notice success" role="status"><?= escape($flashSuccess) ?></p>
      <?php endif; ?>

      <form action="/admin/login" method="post">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

        <div class="form-group">
          <label for="admin-email">Administrator Email</label>
          <input id="admin-email" name="email" type="email" autocomplete="email" maxlength="254" placeholder="admin@pathway.test" required>
        </div>

        <div class="form-group">
          <label for="admin-password">Password</label>
          <input id="admin-password" name="password" type="password" autocomplete="current-password" maxlength="72" required>
        </div>

        <button class="button" type="submit" style="margin-top:8px;">Sign In to Administration</button>
      </form>

      <p class="switch">
        Need to sign in as student?
        <a href="/login">Student Sign In &rarr;</a>
      </p>
    </section>
  </div>

<?php elseif ($screen === 'admin_dashboard'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab active" href="/admin/dashboard">Platform Governance &amp; Audit</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Platform Administration &middot; Security &amp; Compliance</p>
    <h1>System Governance Console</h1>
    <p>Manage organization lifecycle, approve partner companies and colleges, and review immutable audit records.</p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php elseif (is_string($flashSuccess)): ?>
  <div class="notice success" role="status"><p><?= escape($flashSuccess) ?></p></div>
  <?php endif; ?>

  <!-- Section 1: Pending Organizations Awaiting Approval -->
  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.3rem;margin-bottom:14px;">1. Pending Organizations Awaiting Approval (<?= count($pendingOrgs ?? []) ?>)</h2>
    <p class="hint" style="margin-bottom:16px;">New organizations start in PENDING state and cannot post jobs or invite staff until reviewed and approved.</p>

    <?php if (!empty($pendingOrgs)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Organization Name</th>
            <th>Type</th>
            <th>Slug</th>
            <th>Created By</th>
            <th>Submitted</th>
            <th>Approval Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingOrgs as $org): ?>
          <tr>
            <td><strong>#<?= (int) $org['id'] ?></strong></td>
            <td><strong><?= escape($org['name']) ?></strong></td>
            <td><span class="badge"><?= escape($org['type']) ?></span></td>
            <td><code><?= escape($org['slug']) ?></code></td>
            <td><?= escape($org['creator_name'] ?? 'System') ?> (<?= escape($org['creator_email'] ?? '') ?>)</td>
            <td><?= escape(date('Y-m-d H:i', (int) $org['created_at'])) ?></td>
            <td>
              <form action="/admin/organizations/approve" method="post" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <button class="button" type="submit" style="padding:5px 12px;font-size:0.8rem;background:var(--emerald-600);">Approve &amp; Activate</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No pending organizations awaiting approval.</p>
    </div>
    <?php endif; ?>
  </section>

  <!-- Section 2: Active Organizations -->
  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.3rem;margin-bottom:14px;">2. Active Organizations (<?= count($activeOrgs ?? []) ?>)</h2>
    <p class="hint" style="margin-bottom:16px;">Verified organizations authorized to post jobs and recruit on the platform.</p>

    <?php if (!empty($activeOrgs)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Organization Name</th>
            <th>Type</th>
            <th>Active Members</th>
            <th>Posted Jobs</th>
            <th>Status</th>
            <th>Suspend Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activeOrgs as $org): ?>
          <tr>
            <td><strong>#<?= (int) $org['id'] ?></strong></td>
            <td>
              <strong><?= escape($org['name']) ?></strong><br>
              <code><?= escape($org['slug']) ?></code>
            </td>
            <td><span class="badge"><?= escape($org['type']) ?></span></td>
            <td><?= (int) ($org['member_count'] ?? 1) ?></td>
            <td><?= (int) ($org['job_count'] ?? 0) ?></td>
            <td><span class="badge badge-observed">Active</span></td>
            <td>
              <form action="/admin/organizations/suspend" method="post" style="display:flex;gap:6px;margin:0;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <input name="reason" maxlength="200" placeholder="Reason" style="padding:4px 8px;font-size:0.8rem;width:120px;" required>
                <button class="button secondary" type="submit" style="padding:4px 10px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);">Suspend</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No active organizations registered yet.</p>
    </div>
    <?php endif; ?>
  </section>

  <!-- Section 3: Immutable Audit Log -->
  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.3rem;margin-bottom:14px;">3. Platform Security &amp; Compliance Audit Trail</h2>
    <p class="hint" style="margin-bottom:16px;">Immutable append-only record of administrative, organizational, and recruitment actions.</p>

    <?php if (!empty($auditLogs)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Action</th>
            <th>Actor</th>
            <th>Target</th>
            <th>Metadata Details</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($auditLogs as $log): ?>
          <tr>
            <td><?= escape(date('Y-m-d H:i:s', (int) $log['created_at'])) ?></td>
            <td><code><?= escape($log['action']) ?></code></td>
            <td>
              <?= escape($log['actor_name'] ?? 'System') ?><br>
              <span style="font-size:0.75rem;color:var(--text-muted);"><?= escape($log['actor_email'] ?? '') ?></span>
            </td>
            <td>
              <?= escape($log['target_type']) ?> #<?= (int) $log['target_id'] ?>
            </td>
            <td style="max-width:280px;font-size:0.8rem;font-family:monospace;word-break:break-all;">
              <?= escape($log['metadata_json']) ?>
            </td>
            <td><code><?= escape($log['ip_address']) ?></code></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No audit events logged yet.</p>
    </div>
    <?php endif; ?>
  </section>

<?php endif; ?>
</main>

<footer class="container" role="contentinfo">
  <div>EIDOLON PATHWAY &middot; Verifiable Skills &amp; Evidence Architecture</div>
  <div>Local-First Prototype &middot; Standalone PHP 8.4 + SQLite v4 &middot; Zero Code Execution</div>
</footer>
</body>
</html>
