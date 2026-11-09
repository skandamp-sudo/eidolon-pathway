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
    'request_access' => 'Request Institutional Access',
    default => 'Student Access',
  } ?> · EIDOLON PATHWAY</title>
  <script src="/assets/theme.js"></script>
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
    <a class="nav-role-tab <?= in_array($screen, ['login', 'register', 'dashboard', 'profile', 'jobs', 'job_detail', 'applications', 'resume', 'resume_preview', 'assessment', 'affiliation_request', 'privacy_settings', 'privacy_access_log', 'achievements']) ? 'active' : '' ?>" href="/login">Student Portal</a>
    <a class="nav-role-tab <?= str_starts_with($screen, 'company_') ? 'active' : '' ?>" href="/company/login">Company Hub</a>
    <a class="nav-role-tab <?= str_starts_with($screen, 'college_') ? 'active' : '' ?>" href="/college/login">College Network</a>
    <a class="nav-role-tab <?= str_starts_with($screen, 'admin_') ? 'active' : '' ?>" href="/admin/login">Platform Admin</a>
    <a class="nav-role-tab <?= $screen === 'request_access' ? 'active' : '' ?>" href="/request-access">Request Access</a>
  </nav>

  <div class="nav-actions">
    <!-- Three-Theme Switcher (Light / System / Dark) -->
    <div class="theme-switcher" role="group" aria-label="Theme selection">
      <button type="button" class="theme-btn" data-theme-set="light" title="Light Theme" aria-label="Light Theme">&#x2600;</button>
      <button type="button" class="theme-btn" data-theme-set="system" title="System Theme" aria-label="System Theme">&#x25D1;</button>
      <button type="button" class="theme-btn" data-theme-set="dark" title="Dark Theme" aria-label="Dark Theme">&#x263E;</button>
    </div>
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
      
      <?php if (!empty($flashError) && is_string($flashError)): ?>
      <p class="notice error" role="alert"><?= escape($flashError) ?></p>
      <?php endif; ?>
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

      <?php $configuredProviders = $configuredProviders ?? []; ?>
      <?php if (!empty($configuredProviders)): ?>
      <div class="oauth-divider"><span>or continue with</span></div>
      <div class="oauth-actions">
        <?php if (isset($configuredProviders['google'])): ?>
        <a href="/auth/login/google" class="button-oauth button-google">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/></svg>
          Continue with Google
        </a>
        <?php endif; ?>
        <?php if (isset($configuredProviders['github'])): ?>
        <a href="/auth/login/github" class="button-oauth button-github">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
          Continue with GitHub
        </a>
        <?php endif; ?>
        <?php if (isset($configuredProviders['linkedin'])): ?>
        <a href="/auth/login/linkedin" class="button-oauth button-linkedin">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="#0A66C2" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 8.76a1.68 1.68 0 1 0 0-3.36 1.68 1.68 0 0 0 0 3.36m1.4 9.74v-8.37H5.06v8.37h2.8z"/></svg>
          Continue with LinkedIn
        </a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="oauth-unconfigured">
        <p class="hint">OAuth single sign-on available: set <code>GOOGLE_CLIENT_ID</code> / <code>GITHUB_CLIENT_ID</code> environment variables to enable.</p>
      </div>
      <?php endif; ?>

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
    <a class="nav-role-tab <?= $screen === 'achievements' ? 'active' : '' ?>" href="/achievements">Achievements</a>
    <a class="nav-role-tab <?= $screen === 'affiliation_request' ? 'active' : '' ?>" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab <?= $screen === 'privacy_settings' || $screen === 'privacy_access_log' ? 'active' : '' ?>" href="/privacy/settings">Privacy &amp; Consent</a>
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
    <a class="nav-role-tab" href="/achievements">Achievements</a>
    <a class="nav-role-tab" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab" href="/privacy/settings">Privacy &amp; Consent</a>
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
    <a class="nav-role-tab" href="/achievements">Achievements</a>
    <a class="nav-role-tab" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab" href="/privacy/settings">Privacy &amp; Consent</a>
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
    <a class="nav-role-tab" href="/achievements">Achievements</a>
    <a class="nav-role-tab" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab" href="/privacy/settings">Privacy &amp; Consent</a>
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
    <a class="nav-role-tab" href="/achievements">Achievements</a>
    <a class="nav-role-tab" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab" href="/privacy/settings">Privacy &amp; Consent</a>
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

  <?php if (!empty($atsAnalysis)): ?>
  <!-- ATS Intelligence & Match Benchmark (no-print) -->
  <section class="card no-print" style="margin-bottom:28px;border-left:4px solid var(--emerald-500);">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:16px;">
      <div>
        <p class="eyebrow" style="margin-bottom:4px;">Evidence-Grounded Resume Intelligence</p>
        <h2 style="font-size:1.3rem;margin:0;">ATS Parser Analysis &amp; Keyword Alignment</h2>
      </div>
      <div style="text-align:right;">
        <span style="font-size:2rem;font-weight:750;color:var(--emerald-400);"><?= (int) ($atsAnalysis['overall_score'] ?? 0) ?></span>
        <span style="font-size:1rem;color:var(--text-muted);">/ 100</span>
        <div><span class="badge badge-observed"><?= escape($atsAnalysis['rating'] ?? 'ATS Analysis') ?></span></div>
      </div>
    </div>

    <!-- Active Job Benchmark Form -->
    <form action="/resume/preview" method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:18px;background:var(--bg-secondary);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
      <input type="hidden" name="id" value="<?= (int) $resume['id'] ?>">
      <label for="benchmark-job" style="font-size:0.85rem;font-weight:600;">Benchmark Against Vacancy:</label>
      <select id="benchmark-job" name="job_id" style="flex:1;min-width:200px;padding:6px 10px;font-size:0.85rem;background:var(--bg-card);color:var(--text-primary);border:1px solid var(--border-subtle);border-radius:6px;">
        <option value="">General Industry Standard (<?= escape(strtoupper($atsAnalysis['target_role'] ?? 'Backend')) ?>)</option>
        <?php foreach ($activeJobs ?? [] as $aj): ?>
        <option value="<?= (int) $aj['id'] ?>" <?= ((int)($targetJob['id'] ?? 0) === (int)$aj['id']) ? 'selected' : '' ?>>
          <?= escape($aj['title']) ?> (<?= escape($aj['company_name'] ?? 'Company') ?>)
        </option>
        <?php endforeach; ?>
      </select>
      <button class="button secondary" type="submit" style="padding:6px 14px;font-size:0.85rem;">Analyze Match</button>
    </form>

    <!-- Checklist Breakdown -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:16px;">
      <?php foreach ($atsAnalysis['checks'] ?? [] as $chk): ?>
      <div style="border:1px solid var(--border-subtle);padding:10px 14px;border-radius:var(--radius-sm);background:var(--bg-card);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
          <strong style="font-size:0.85rem;"><?= escape($chk['category']) ?></strong>
          <span style="font-size:0.8rem;color:<?= $chk['passed'] ? 'var(--emerald-400)' : 'var(--rose-400)' ?>;">
            <?= (int) $chk['score'] ?> / <?= (int) $chk['max'] ?>
          </span>
        </div>
        <p style="font-size:0.75rem;color:var(--text-secondary);margin:0;line-height:1.4;"><?= escape($chk['feedback']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Keyword Alignment -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px;">
      <div>
        <h4 style="font-size:0.85rem;margin-bottom:6px;color:var(--emerald-400);">&#10003; Matched Industry Keywords</h4>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php foreach ($atsAnalysis['matched_keywords'] ?? [] as $mkw): ?>
          <span class="badge" style="font-size:0.75rem;background:var(--bg-secondary);"><?= escape($mkw) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <h4 style="font-size:0.85rem;margin-bottom:6px;color:var(--text-muted);">&#x25C7; Recommended Skills for <?= escape(strtoupper($atsAnalysis['target_role'] ?? 'Role')) ?></h4>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php foreach ($atsAnalysis['suggested_keywords'] ?? [] as $skw): ?>
          <span class="badge muted" style="font-size:0.75rem;"><?= escape($skw) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <p style="font-size:0.75rem;color:var(--text-muted);margin:0;line-height:1.4;">
      <em>Notice: ATS analysis provides transparent rule-based guidance to help structure technical qualifications. Never include keywords for skills you do not possess. No system can guarantee shortlisting.</em>
    </p>
  </section>
  <?php endif; ?>

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
    <a class="nav-role-tab" href="/achievements">Achievements</a>
    <a class="nav-role-tab" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab" href="/privacy/settings">Privacy &amp; Consent</a>
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
      <span class="badge muted">SQLite Store v7</span>
    </div>
  </div>

  <?php
    $appsList = $applications ?? [];
    $totalApps = count($appsList);
    $underReviewApps = count(array_filter($appsList, fn($a) => ($a['status'] ?? '') === 'under_review'));
    $shortlistedApps = count(array_filter($appsList, fn($a) => ($a['status'] ?? '') === 'shortlisted'));
    $completedAssessmentsCount = count(array_filter($assessments ?? [], fn($a) => ($a['state'] ?? '') === 'completed'));
    $achievementsCount = count($achievements ?? []);
    $resumesCount = count($allResumes ?? []);

    // Calculate real profile completeness percentage
    $completenessPoints = 0;
    if (!empty($user['email'])) $completenessPoints += 20;
    if (!empty($profile['skills'])) $completenessPoints += 20;
    if (!empty($profile['bio'])) $completenessPoints += 15;
    if ($resumesCount > 0) $completenessPoints += 20;
    if ($completedAssessmentsCount > 0) $completenessPoints += 25;
  ?>

  <!-- Student Career Analytics Cards -->
  <section class="dashboard-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px;">
    <div class="card stat-card">
      <div class="stat-value"><?= $totalApps ?></div>
      <div class="stat-label">Applications Submitted</div>
      <div style="margin-top:6px;font-size:0.75rem;color:var(--text-muted);">
        <?= $underReviewApps ?> under review &middot; <?= $shortlistedApps ?> shortlisted
      </div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--emerald-400);"><?= $shortlistedApps ?></div>
      <div class="stat-label">Shortlisted Opportunities</div>
      <div style="margin-top:6px;font-size:0.75rem;color:var(--emerald-400);">Verified Employer Outreach</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--sky-400);"><?= $completedAssessmentsCount ?></div>
      <div class="stat-label">Verified Portfolio Audits</div>
      <div style="margin-top:6px;font-size:0.75rem;color:var(--text-muted);">Commit-pinned evidence</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--violet-400);"><?= $achievementsCount ?></div>
      <div class="stat-label">Credentials &amp; Badges</div>
      <div style="margin-top:6px;font-size:0.75rem;color:var(--text-muted);">Structured credentials</div>
    </div>
  </section>

  <!-- Career Readiness & Profile Completeness Progress -->
  <section class="card" style="margin-bottom:28px;background:var(--bg-secondary);border:1px solid var(--border-subtle);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <h3 style="font-size:0.95rem;margin:0;font-weight:600;">Candidate Career Readiness</h3>
      <span style="font-weight:700;color:var(--emerald-400);"><?= $completenessPoints ?>% Complete</span>
    </div>
    <div style="width:100%;height:8px;background:var(--bg-primary);border-radius:var(--radius-pill);overflow:hidden;margin-bottom:10px;">
      <div style="width:<?= $completenessPoints ?>%;height:100%;background:var(--emerald-500);border-radius:var(--radius-pill);transition:width 0.3s ease;"></div>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:0.8rem;color:var(--text-muted);">
      <span><?= !empty($profile['skills']) ? '&#10003; Skills added' : '&#x25C7; Add skills' ?></span>
      <span><?= $resumesCount > 0 ? '&#10003; ATS resume created' : '&#x25C7; Build ATS resume' ?></span>
      <span><?= $completedAssessmentsCount > 0 ? '&#10003; GitHub evidence linked' : '&#x25C7; Run repository assessment' ?></span>
      <span><?= ($profile['affiliation_status'] ?? '') === 'AFFILIATED' ? '&#10003; College affiliated' : '&#x25C7; Request college affiliation' ?></span>
    </div>
  </section>

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
  <div class="dashboard-stats" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:28px;">
    <?php
      $jobsList = $jobs ?? [];
      $allApplicantsList = $allApplicants ?? [];
      $totalJobs = count($jobsList);
      $activeJobs = count(array_filter($jobsList, fn($j) => ($j['status'] ?? '') === 'active'));
      $totalApplicants = count($allApplicantsList);
      $shortlistedApplicants = count(array_filter($allApplicantsList, fn($a) => ($a['status'] ?? '') === 'shortlisted'));
      $underReviewApplicants = count(array_filter($allApplicantsList, fn($a) => ($a['status'] ?? '') === 'under_review'));
      $conversionRate = $totalApplicants > 0 ? (int) round(($shortlistedApplicants / $totalApplicants) * 100) : 0;
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
      <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);font-weight:600;">Total Applicants</span>
      <p style="font-size:2rem;font-weight:700;margin:8px 0 0;"><?= $totalApplicants ?></p>
      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;"><?= $underReviewApplicants ?> under review</div>
    </div>
    <div class="card" style="padding:18px;">
      <span style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);font-weight:600;">Shortlisted</span>
      <p style="font-size:2rem;font-weight:700;margin:8px 0 0;color:var(--emerald-400);"><?= $shortlistedApplicants ?></p>
      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;"><?= $conversionRate ?>% conversion rate</div>
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
    <a class="nav-role-tab" href="/company/talent">Talent Discovery</a>
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
            <span style="font-size:0.8rem;color:var(--text-muted);"><?= escape($app['student_email']) ?></span><br>
            <a href="/company/candidate?id=<?= (int) $app['application_id'] ?>" style="font-size:0.8rem;color:var(--brand-accent);">View Candidate Dossier &rarr;</a>
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
    <a class="nav-role-tab" href="/college/affiliations">Affiliation Review Queue</a>
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
              <div style="display:flex;gap:6px;align-items:center;">
                <?php if ($hasConsent): ?>
                <a class="button secondary" style="padding:4px 10px;font-size:0.8rem;" href="/college/students/view?id=<?= (int) $s['student_id'] ?>">
                  View Records &rarr;
                </a>
                <?php else: ?>
                <span style="font-size:0.8rem;color:var(--text-muted);">Private</span>
                <?php endif; ?>
                <form action="/college/affiliations/revoke" method="post" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                  <input type="hidden" name="student_id" value="<?= (int) $s['student_id'] ?>">
                  <input type="hidden" name="reason" value="Administrative revocation by placement coordinator">
                  <button class="button secondary" type="submit" style="padding:4px 8px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);">Revoke</button>
                </form>
              </div>
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
    <h1><?= escape($student['student_name'] ?? $student['name'] ?? 'Student') ?></h1>
    <p style="margin-top:4px;">
      <span class="badge badge-observed">Student Consent Verified</span>
      <span class="badge muted" style="margin-left:8px;"><?= escape($student['student_email'] ?? $student['email'] ?? '') ?></span>
      <?php if (!empty($student['enrollment_number'])): ?>
      <span class="badge" style="margin-left:8px;">USN: <code><?= escape($student['enrollment_number']) ?></code></span>
      <?php endif; ?>
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

  <?php $achievements = $placement['achievements'] ?? []; ?>
  <?php if (!empty($achievements)): ?>
  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.3rem;margin-bottom:14px;">Verified Credentials &amp; Achievements (<?= count($achievements) ?>)</h2>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($achievements as $ach): ?>
      <div style="padding:12px;background:var(--bg-surface);border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span class="badge" style="font-size:0.75rem;"><?= escape($ach['category']) ?></span>
          <strong><?= escape($ach['title']) ?></strong>
          <?php if (!empty($ach['issuer'])): ?>
          <span style="font-size:0.85rem;color:var(--text-muted);">&middot; <?= escape($ach['issuer']) ?></span>
          <?php endif; ?>
          <?php if (!empty($ach['issue_date'])): ?>
          <span style="font-size:0.85rem;color:var(--text-muted);">(<?= escape($ach['issue_date']) ?>)</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($ach['description'])): ?>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin:6px 0 0;"><?= escape($ach['description']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
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

<?php elseif ($screen === 'request_access'): ?>
  <div class="auth-layout" style="max-width:760px;margin:24px auto;">
    <section class="hero" style="text-align:center;margin-bottom:28px;">
      <p class="eyebrow">Institutional Onboarding &middot; High Assurance Trust</p>
      <h1>Request Organization Access</h1>
      <p>Partner companies and accredited universities: submit your credentials to join EIDOLON PATHWAY.</p>
    </section>

    <?php
      $flashError = $flashError ?? ($_SESSION['flash_error'] ?? null);
      $flashSuccess = $flash ?? ($_SESSION['flash'] ?? null);
      unset($_SESSION['flash_error'], $_SESSION['flash']);
    ?>
    <?php if (is_string($flashError)): ?>
    <div class="notice error" role="alert" style="margin-bottom:20px;"><p><?= escape($flashError) ?></p></div>
    <?php elseif (is_string($flashSuccess)): ?>
    <div class="notice success" role="status" style="margin-bottom:20px;"><p><?= escape($flashSuccess) ?></p></div>
    <?php endif; ?>

    <?php if (isset($_GET['submitted'])): ?>
    <section class="card" style="text-align:center;padding:36px 24px;">
      <div style="font-size:3rem;color:var(--emerald-400);margin-bottom:12px;">&#10003;</div>
      <h2 style="font-size:1.4rem;margin-bottom:12px;">Application Received</h2>
      <p style="color:var(--text-secondary);max-width:540px;margin:0 auto 24px;line-height:1.6;">
        Your institutional access request has been securely recorded. Our compliance officers verify official domain records, educational accreditation, and authority to represent before issuing cryptographic invitation links.
      </p>
      <div style="display:flex;gap:12px;justify-content:center;">
        <a class="button" href="/login">Return to Platform</a>
        <a class="button secondary" href="/request-access">Submit Another Request</a>
      </div>
    </section>
    <?php else: ?>
    <section class="card">
      <form action="/request-access" method="post" style="display:flex;flex-direction:column;gap:18px;">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

        <div>
          <label for="req-name" style="font-weight:600;display:block;margin-bottom:6px;">Organization Legal Name</label>
          <input id="req-name" name="name" type="text" placeholder="e.g. Acme Technologies Inc. or Dayananda Sagar Academy of Technology & Management" maxlength="120" required style="width:100%;box-sizing:border-box;">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <label for="req-type" style="font-weight:600;display:block;margin-bottom:6px;">Organization Type</label>
            <select id="req-type" name="type" required style="width:100%;box-sizing:border-box;padding:8px 12px;background:var(--bg-card);color:var(--text-primary);border:1px solid var(--border-subtle);border-radius:6px;">
              <option value="COMPANY">Company / Employer</option>
              <option value="COLLEGE">College / University</option>
            </select>
          </div>
          <div>
            <label for="req-website" style="font-weight:600;display:block;margin-bottom:6px;">Official Website / Domain</label>
            <input id="req-website" name="website" type="text" placeholder="https://example.com" maxlength="200" required style="width:100%;box-sizing:border-box;">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <label for="req-contact-name" style="font-weight:600;display:block;margin-bottom:6px;">Authorized Contact Full Name</label>
            <input id="req-contact-name" name="contact_name" type="text" placeholder="e.g. Dr. Ramesh Rao or Jane Doe" maxlength="100" required style="width:100%;box-sizing:border-box;">
          </div>
          <div>
            <label for="req-designation" style="font-weight:600;display:block;margin-bottom:6px;">Designation / Official Title</label>
            <input id="req-designation" name="designation" type="text" placeholder="e.g. Dean of Placements or Head of Talent" maxlength="100" required style="width:100%;box-sizing:border-box;">
          </div>
        </div>

        <div>
          <label for="req-email" style="font-weight:600;display:block;margin-bottom:6px;">Official Institutional Work Email</label>
          <input id="req-email" name="contact_email" type="email" placeholder="name@organization.edu or name@company.com" maxlength="254" required style="width:100%;box-sizing:border-box;">
          <small class="hint" style="display:block;margin-top:4px;">Must use official institutional domain. Generic free email providers (Gmail, Yahoo) are subjected to strict scrutiny.</small>
        </div>

        <div>
          <label for="req-notes" style="font-weight:600;display:block;margin-bottom:6px;">Verification Evidence &amp; Authority Statement</label>
          <textarea id="req-notes" name="verification_notes" rows="4" placeholder="Provide proof of authority: official university accreditation ID, department URL, leadership directory link, corporate registration number, or official contact reference." maxlength="2000" required style="width:100%;box-sizing:border-box;"></textarea>
        </div>

        <div style="margin-top:12px;">
          <button class="button" type="submit" style="width:100%;padding:10px 18px;font-size:1rem;">Submit Institutional Access Request</button>
        </div>
      </form>
    </section>
    <?php endif; ?>
  </div>

<?php elseif ($screen === 'admin_dashboard'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab <?= ($activeTab ?? 'overview') === 'overview' ? 'active' : '' ?>" href="/admin/dashboard?tab=overview">Overview</a>
    <a class="nav-role-tab <?= ($activeTab ?? '') === 'requests' ? 'active' : '' ?>" href="/admin/dashboard?tab=requests">
      Access Requests <?php if (!empty($pendingRequests)): ?><span class="badge" style="background:var(--rose-500);color:#fff;margin-left:4px;padding:1px 6px;"><?= count($pendingRequests) ?></span><?php endif; ?>
    </a>
    <a class="nav-role-tab <?= ($activeTab ?? '') === 'orgs' ? 'active' : '' ?>" href="/admin/dashboard?tab=orgs">Organizations</a>
    <a class="nav-role-tab <?= ($activeTab ?? '') === 'users' ? 'active' : '' ?>" href="/admin/dashboard?tab=users">User Directory</a>
    <a class="nav-role-tab <?= ($activeTab ?? '') === 'invitations' ? 'active' : '' ?>" href="/admin/dashboard?tab=invitations">Invitations</a>
    <a class="nav-role-tab <?= ($activeTab ?? '') === 'access_reviews' ? 'active' : '' ?>" href="/admin/dashboard?tab=access_reviews">Access Reviews</a>
    <a class="nav-role-tab <?= ($activeTab ?? '') === 'audit' ? 'active' : '' ?>" href="/admin/dashboard?tab=audit">Audit Trail</a>
    <a class="nav-role-tab <?= ($activeTab ?? '') === 'security' ? 'active' : '' ?>" href="/admin/dashboard?tab=security">Security Posture</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Platform Administration &middot; Security &amp; Compliance</p>
    <h1>System Governance Console &middot; EIDOLON CONTROL Center</h1>
    <p>Unified administrative authority for institutional verification, organization lifecycles, user governance, and cryptographic audit.</p>
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

  <?php if (($activeTab ?? 'overview') === 'overview'): ?>
  <!-- TAB: OVERVIEW -->
  <section class="dashboard-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px;">
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--rose-400);"><?= (int) ($metrics['pending_requests'] ?? 0) ?></div>
      <div class="stat-label">Pending Access Requests</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--sky-400);"><?= (int) ($metrics['active_companies'] ?? 0) ?></div>
      <div class="stat-label">Active Companies</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--violet-400);"><?= (int) ($metrics['active_colleges'] ?? 0) ?></div>
      <div class="stat-label">Active Colleges</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--emerald-400);"><?= (int) ($metrics['active_affiliations'] ?? 0) ?></div>
      <div class="stat-label">Verified Student Affiliations</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value"><?= (int) ($metrics['total_students'] ?? 0) ?></div>
      <div class="stat-label">Registered Students</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value"><?= (int) ($metrics['total_jobs'] ?? 0) ?></div>
      <div class="stat-label">Active Jobs</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value"><?= (int) ($metrics['total_applications'] ?? 0) ?></div>
      <div class="stat-label">Job Applications</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value" style="color:var(--amber-400);"><?= (int) ($metrics['total_sensitive_accesses'] ?? 0) ?></div>
      <div class="stat-label">Sensitive Access Events</div>
    </div>
  </section>

  <!-- Urgent Action Items: Pending Requests -->
  <?php if (!empty($pendingRequests)): ?>
  <section class="card" style="margin-bottom:28px;border-left:4px solid var(--rose-500);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h2 style="font-size:1.2rem;margin:0;">Pending Access Requests Requiring Review (<?= count($pendingRequests) ?>)</h2>
      <a class="button secondary" href="/admin/dashboard?tab=requests" style="padding:4px 10px;font-size:0.8rem;">Open Request Queue &rarr;</a>
    </div>
    <p class="hint" style="margin-bottom:14px;">Review identity and institutional authority before issuing setup invitations.</p>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Organization</th>
            <th>Type</th>
            <th>Website</th>
            <th>Applicant</th>
            <th>Official Email</th>
            <th>Submitted</th>
            <th>Review Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($pendingRequests, 0, 5) as $req): ?>
          <tr>
            <td><strong><?= escape($req['name']) ?></strong></td>
            <td><span class="badge"><?= escape($req['type']) ?></span></td>
            <td><a href="<?= escape($req['website']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:0.85rem;"><?= escape($req['website']) ?></a></td>
            <td><?= escape($req['contact_name']) ?><br><span style="font-size:0.75rem;color:var(--text-muted);"><?= escape($req['designation']) ?></span></td>
            <td><code><?= escape($req['contact_email']) ?></code></td>
            <td><?= escape(date('Y-m-d H:i', (int) $req['created_at'])) ?></td>
            <td>
              <a class="button" href="/admin/dashboard?tab=requests" style="padding:4px 10px;font-size:0.8rem;">Review</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <!-- Pending Organizations Section -->
  <?php if (!empty($pendingOrgs)): ?>
  <section class="card" style="margin-bottom:28px;border-left:4px solid var(--amber-500);">
    <h2 style="font-size:1.2rem;margin-bottom:12px;">Pending Organizations Awaiting Activation (<?= count($pendingOrgs) ?>)</h2>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Slug</th>
            <th>Creator</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingOrgs as $org): ?>
          <tr>
            <td><strong><?= escape($org['name']) ?></strong></td>
            <td><span class="badge"><?= escape($org['type']) ?></span></td>
            <td><code><?= escape($org['slug']) ?></code></td>
            <td><?= escape($org['creator_name'] ?? 'System') ?> (<?= escape($org['creator_email'] ?? '') ?>)</td>
            <td>
              <form action="/admin/organizations/approve" method="post" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <button class="button" type="submit" style="padding:4px 10px;font-size:0.8rem;background:var(--emerald-600);">Approve &amp; Activate</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <!-- Active Organizations -->
  <section class="card" style="margin-bottom:28px;">
    <h2 style="font-size:1.2rem;margin-bottom:12px;">Active Organizations (<?= count($activeOrgs ?? []) ?>)</h2>
    <p class="hint" style="margin-bottom:14px;">Verified partner organizations authorized to operate on EIDOLON PATHWAY.</p>
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
              <form action="/admin/organizations/suspend" method="post" style="display:flex;gap:4px;margin:0;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <input name="reason" maxlength="200" placeholder="Reason" style="padding:3px 6px;font-size:0.75rem;width:110px;" required>
                <button class="button secondary" type="submit" style="padding:3px 8px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);">Suspend</button>
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

  <?php elseif (($activeTab ?? '') === 'requests'): ?>
  <!-- TAB: ACCESS REQUESTS -->
  <section class="card" style="margin-bottom:28px;">
    <h2 style="font-size:1.3rem;margin-bottom:8px;">Organization Access Requests Queue (<?= count($allRequests ?? []) ?>)</h2>
    <p class="hint" style="margin-bottom:18px;">Self-service institutional onboarding applications. Verify domain ownership, accreditation, and authority to represent before approval.</p>

    <?php if (!empty($allRequests)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Organization &amp; Domain</th>
            <th>Type</th>
            <th>Applicant &amp; Title</th>
            <th>Institutional Email</th>
            <th>Verification Evidence</th>
            <th>Submitted</th>
            <th>Status</th>
            <th>Action / Outcome</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allRequests as $req): ?>
          <tr>
            <td><strong>#<?= (int) $req['id'] ?></strong></td>
            <td>
              <strong><?= escape($req['name']) ?></strong><br>
              <a href="<?= escape($req['website']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:0.8rem;"><?= escape($req['website']) ?></a>
            </td>
            <td><span class="badge"><?= escape($req['type']) ?></span></td>
            <td>
              <?= escape($req['contact_name']) ?><br>
              <span style="font-size:0.75rem;color:var(--text-muted);"><?= escape($req['designation']) ?></span>
            </td>
            <td><code><?= escape($req['contact_email']) ?></code></td>
            <td style="max-width:260px;font-size:0.8rem;white-space:pre-wrap;line-height:1.4;">
              <?= escape($req['verification_notes']) ?>
            </td>
            <td><?= escape(date('Y-m-d H:i', (int) $req['created_at'])) ?></td>
            <td>
              <?php if ($req['status'] === 'PENDING'): ?>
              <span class="badge" style="background:var(--amber-600);color:#fff;">PENDING</span>
              <?php elseif ($req['status'] === 'APPROVED'): ?>
              <span class="badge" style="background:var(--emerald-600);color:#fff;">APPROVED</span>
              <?php else: ?>
              <span class="badge" style="background:var(--rose-600);color:#fff;">REJECTED</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($req['status'] === 'PENDING'): ?>
              <form action="/admin/requests/review" method="post" style="display:flex;flex-direction:column;gap:6px;margin:0;min-width:180px;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                <input name="review_notes" placeholder="Review notes (optional)" maxlength="500" style="padding:4px 8px;font-size:0.75rem;">
                <div style="display:flex;gap:4px;">
                  <button class="button" type="submit" name="decision" value="APPROVE" style="padding:4px 8px;font-size:0.75rem;background:var(--emerald-600);flex:1;">Approve</button>
                  <button class="button secondary" type="submit" name="decision" value="REJECT" style="padding:4px 8px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);flex:1;">Reject</button>
                </div>
              </form>
              <?php else: ?>
              <div style="font-size:0.75rem;color:var(--text-muted);">
                Reviewed by: <?= escape($req['reviewer_name'] ?? 'Admin') ?><br>
                <?= escape(date('Y-m-d H:i', (int) ($req['reviewed_at'] ?? 0))) ?><br>
                <?php if (!empty($req['review_notes'])): ?>
                Note: <em><?= escape($req['review_notes']) ?></em>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice"><p>No organization access requests received yet.</p></div>
    <?php endif; ?>
  </section>

  <?php elseif (($activeTab ?? '') === 'orgs'): ?>
  <!-- TAB: ORGANIZATIONS -->
  <section class="card" style="margin-bottom:28px;">
    <h2 style="font-size:1.3rem;margin-bottom:8px;">Registered Organizations (<?= count($allOrgs ?? []) ?>)</h2>
    <p class="hint" style="margin-bottom:18px;">Companies and Colleges operating inside EIDOLON PATHWAY.</p>

    <?php if (!empty($allOrgs)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name &amp; Slug</th>
            <th>Type</th>
            <th>Status</th>
            <th>Active Members</th>
            <th>Jobs Posted</th>
            <th>Created</th>
            <th>Governance Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allOrgs as $org): ?>
          <tr>
            <td><strong>#<?= (int) $org['id'] ?></strong></td>
            <td>
              <strong><?= escape($org['name']) ?></strong><br>
              <code><?= escape($org['slug']) ?></code>
            </td>
            <td><span class="badge"><?= escape($org['type']) ?></span></td>
            <td>
              <?php if ($org['status'] === 'ACTIVE'): ?>
              <span class="badge badge-observed">Active</span>
              <?php elseif ($org['status'] === 'PENDING'): ?>
              <span class="badge" style="background:var(--amber-600);color:#fff;">Pending</span>
              <?php else: ?>
              <span class="badge" style="background:var(--rose-600);color:#fff;">Suspended</span>
              <?php endif; ?>
            </td>
            <td><?= (int) ($org['member_count'] ?? 1) ?></td>
            <td><?= (int) ($org['job_count'] ?? 0) ?></td>
            <td><?= escape(date('Y-m-d', (int) $org['created_at'])) ?></td>
            <td>
              <?php if ($org['status'] === 'PENDING'): ?>
              <form action="/admin/organizations/approve" method="post" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <button class="button" type="submit" style="padding:4px 10px;font-size:0.75rem;background:var(--emerald-600);">Approve</button>
              </form>
              <?php elseif ($org['status'] === 'ACTIVE'): ?>
              <form action="/admin/organizations/suspend" method="post" style="display:flex;gap:4px;margin:0;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <input name="reason" maxlength="200" placeholder="Reason" style="padding:3px 6px;font-size:0.75rem;width:110px;" required>
                <button class="button secondary" type="submit" style="padding:3px 8px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);">Suspend</button>
              </form>
              <?php else: ?>
              <span style="font-size:0.75rem;color:var(--text-muted);">Suspended</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice"><p>No organizations registered yet.</p></div>
    <?php endif; ?>
  </section>

  <?php elseif (($activeTab ?? '') === 'users'): ?>
  <!-- TAB: USER DIRECTORY -->
  <section class="card" style="margin-bottom:28px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
      <h2 style="font-size:1.3rem;margin:0;">Platform User Directory (<?= count($usersList ?? []) ?>)</h2>
      <form action="/admin/dashboard" method="get" style="display:flex;gap:8px;margin:0;">
        <input type="hidden" name="tab" value="users">
        <input name="user_search" placeholder="Search name or email..." value="<?= escape($_GET['user_search'] ?? '') ?>" style="padding:6px 10px;font-size:0.85rem;width:180px;">
        <select name="user_role" style="padding:6px 10px;font-size:0.85rem;background:var(--bg-card);color:var(--text-primary);border:1px solid var(--border-subtle);border-radius:6px;">
          <option value="">All Roles</option>
          <option value="student" <?= ($_GET['user_role'] ?? '') === 'student' ? 'selected' : '' ?>>Students</option>
          <option value="company" <?= ($_GET['user_role'] ?? '') === 'company' ? 'selected' : '' ?>>Company Staff</option>
          <option value="college" <?= ($_GET['user_role'] ?? '') === 'college' ? 'selected' : '' ?>>College Placement</option>
          <option value="admin" <?= ($_GET['user_role'] ?? '') === 'admin' ? 'selected' : '' ?>>Platform Admin</option>
        </select>
        <button class="button secondary" type="submit" style="padding:6px 12px;font-size:0.85rem;">Filter</button>
      </form>
    </div>

    <?php if (!empty($usersList)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>User ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>System Role</th>
            <th>Active Memberships</th>
            <th>Registered</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usersList as $u): ?>
          <tr>
            <td><strong>#<?= (int) $u['id'] ?></strong></td>
            <td><strong><?= escape($u['name']) ?></strong></td>
            <td><code><?= escape($u['email']) ?></code></td>
            <td><span class="badge"><?= escape(strtoupper($u['role'])) ?></span></td>
            <td><?= (int) ($u['active_memberships_count'] ?? 0) ?></td>
            <td><?= escape(date('Y-m-d H:i', (int) ($u['created_at'] ?? time()))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice"><p>No users matching filter criteria.</p></div>
    <?php endif; ?>
  </section>

  <?php elseif (($activeTab ?? '') === 'invitations'): ?>
  <!-- TAB: INVITATIONS -->
  <section class="card" style="margin-bottom:28px;">
    <h2 style="font-size:1.3rem;margin-bottom:8px;">Issue New Staff Invitation</h2>
    <p class="hint" style="margin-bottom:16px;">Issue a cryptographically secure, single-use 7-day staff onboarding invitation to an active organization.</p>
    <form action="/admin/invitations/create" method="post" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <div style="flex:1;min-width:200px;">
        <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:4px;">Target Organization</label>
        <select name="organization_id" required style="width:100%;padding:7px 10px;background:var(--bg-card);color:var(--text-primary);border:1px solid var(--border-subtle);border-radius:6px;">
          <?php foreach ($activeOrgs as $org): ?>
          <option value="<?= (int) $org['id'] ?>"><?= escape($org['name']) ?> (<?= escape($org['type']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="flex:1;min-width:200px;">
        <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:4px;">Invitee Email</label>
        <input name="email" type="email" placeholder="staff@organization.com" required style="width:100%;box-sizing:border-box;">
      </div>
      <div style="width:140px;">
        <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:4px;">Role</label>
        <select name="role" required style="width:100%;padding:7px 10px;background:var(--bg-card);color:var(--text-primary);border:1px solid var(--border-subtle);border-radius:6px;">
          <option value="STAFF">STAFF</option>
          <option value="ADMIN">ADMIN</option>
          <option value="OWNER">OWNER</option>
          <option value="REVIEWER">REVIEWER</option>
        </select>
      </div>
      <button class="button" type="submit" style="padding:8px 16px;">Issue Invitation</button>
    </form>
  </section>

  <section class="card" style="margin-bottom:28px;">
    <h2 style="font-size:1.3rem;margin-bottom:8px;">Issued Staff Invitations (<?= count($invitations ?? []) ?>)</h2>
    <p class="hint" style="margin-bottom:16px;">Single-use invitation secrets are hashed with SHA-256 upon issuance. Raw tokens are never stored in the database.</p>

    <?php if (!empty($invitations)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Organization</th>
            <th>Invitee Email</th>
            <th>Role</th>
            <th>State</th>
            <th>Token Hash (Prefix)</th>
            <th>Invited By</th>
            <th>Expires At</th>
            <th>Governance Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invitations as $inv): ?>
          <tr>
            <td><strong>#<?= (int) $inv['id'] ?></strong></td>
            <td><?= escape($inv['org_name']) ?></td>
            <td><code><?= escape($inv['email']) ?></code></td>
            <td><span class="badge"><?= escape($inv['role']) ?></span></td>
            <td>
              <?php if ($inv['state'] === 'PENDING'): ?>
              <span class="badge" style="background:var(--emerald-600);color:#fff;">PENDING</span>
              <?php elseif ($inv['state'] === 'USED'): ?>
              <span class="badge badge-observed">USED</span>
              <?php elseif ($inv['state'] === 'REVOKED'): ?>
              <span class="badge" style="background:var(--rose-600);color:#fff;">REVOKED</span>
              <?php else: ?>
              <span class="badge" style="background:var(--text-muted);color:#fff;">EXPIRED</span>
              <?php endif; ?>
            </td>
            <td><code><?= escape(substr($inv['token_hash'], 0, 12)) ?>...</code></td>
            <td><?= escape($inv['inviter_name'] ?? 'Admin') ?></td>
            <td><?= escape(date('Y-m-d H:i', (int) $inv['expires_at'])) ?></td>
            <td>
              <?php if ($inv['state'] === 'PENDING'): ?>
              <form action="/admin/invitations/revoke" method="post" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                <button class="button secondary" type="submit" style="padding:3px 8px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);">Revoke</button>
              </form>
              <?php else: ?>
              <span style="font-size:0.75rem;color:var(--text-muted);">&mdash;</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice"><p>No invitations issued yet.</p></div>
    <?php endif; ?>
  </section>

  <?php elseif (($activeTab ?? '') === 'access_reviews'): ?>
  <!-- TAB: ACCESS REVIEWS -->
  <section class="card" style="margin-bottom:28px;">
    <h2 style="font-size:1.3rem;margin-bottom:8px;">Sensitive Record Access Reviews (<?= count($accessReviews ?? []) ?>)</h2>
    <p class="hint" style="margin-bottom:16px;">Immutable security audit of all college placement officer and employer recruiter views of student profiles, resumes, and candidate dossiers.</p>

    <?php if (!empty($accessReviews)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Accessor (Actor)</th>
            <th>Role &amp; Org</th>
            <th>Target Student</th>
            <th>Action</th>
            <th>Status</th>
            <th>Metadata Details</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessReviews as $log): ?>
          <tr>
            <td><?= escape(date('Y-m-d H:i:s', (int) $log['created_at'])) ?></td>
            <td>
              <strong><?= escape($log['accessor_name'] ?? 'Unknown') ?></strong><br>
              <span style="font-size:0.75rem;color:var(--text-muted);"><?= escape($log['accessor_email'] ?? '') ?></span>
            </td>
            <td>
              <span class="badge"><?= escape(strtoupper($log['accessor_role'] ?? 'user')) ?></span><br>
              <span style="font-size:0.75rem;"><?= escape($log['accessor_org_name'] ?? 'Platform') ?></span>
            </td>
            <td>
              <?php if (!empty($log['student_name'])): ?>
              <?= escape($log['student_name']) ?><br>
              <span style="font-size:0.75rem;color:var(--text-muted);"><?= escape($log['student_email'] ?? '') ?></span>
              <?php else: ?>
              <span style="font-size:0.75rem;color:var(--text-muted);">Aggregated (Talent Search)</span>
              <?php endif; ?>
            </td>
            <td><code><?= escape($log['action']) ?></code></td>
            <td>
              <?php if ($log['status'] === 'PERMITTED'): ?>
              <span class="badge" style="background:var(--emerald-600);color:#fff;">PERMITTED</span>
              <?php else: ?>
              <span class="badge" style="background:var(--rose-600);color:#fff;">BLOCKED</span>
              <?php endif; ?>
            </td>
            <td style="max-width:240px;font-size:0.75rem;font-family:monospace;word-break:break-all;">
              <?= escape($log['metadata_json']) ?>
            </td>
            <td><code><?= escape($log['ip_address']) ?></code></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice"><p>No sensitive record accesses logged yet.</p></div>
    <?php endif; ?>
  </section>

  <?php elseif (($activeTab ?? '') === 'audit'): ?>
  <!-- TAB: AUDIT TRAIL -->
  <section class="card" style="margin-bottom:28px;">
    <h2 style="font-size:1.3rem;margin-bottom:8px;">Platform Security &amp; Compliance Audit Trail</h2>
    <p class="hint" style="margin-bottom:16px;">Immutable append-only record of administrative, organizational, and authorization events.</p>

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
    <div class="notice"><p>No audit events logged yet.</p></div>
    <?php endif; ?>
  </section>

  <?php elseif (($activeTab ?? '') === 'security'): ?>
  <!-- TAB: SECURITY POSTURE -->
  <section class="card" style="margin-bottom:28px;">
    <h2 style="font-size:1.3rem;margin-bottom:8px;">Platform Security Posture &amp; Cryptographic Architecture</h2>
    <p class="hint" style="margin-bottom:20px;">Defense-in-depth security verification mapped to OWASP ASVS 5.0 controls.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
      <div style="border:1px solid var(--border-subtle);border-radius:8px;padding:16px;background:var(--bg-card);">
        <h3 style="font-size:1rem;color:var(--emerald-400);margin-bottom:8px;">&#10003; Cryptographic Authentication</h3>
        <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.5;">
          All user credentials hashed using standard <code>PASSWORD_DEFAULT</code> (bcrypt/Argon2 with cost factor). Zero plaintext passwords stored or logged. Timing-safe verification prevents side-channel analysis.
        </p>
      </div>

      <div style="border:1px solid var(--border-subtle);border-radius:8px;padding:16px;background:var(--bg-card);">
        <h3 style="font-size:1rem;color:var(--emerald-400);margin-bottom:8px;">&#10003; Secret Token Architecture</h3>
        <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.5;">
          Staff invitations and session tokens generated via CSPRNG (<code>random_bytes(32)</code>). Only 64-character SHA-256 hashes are stored. Possession of raw token proves legitimate email delivery.
        </p>
      </div>

      <div style="border:1px solid var(--border-subtle);border-radius:8px;padding:16px;background:var(--bg-card);">
        <h3 style="font-size:1rem;color:var(--emerald-400);margin-bottom:8px;">&#10003; Storage Isolation &amp; Integrity</h3>
        <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.5;">
          SQLite database stored outside the web root with permissions <code>0600</code> and directory <code>0700</code>. Foreign key constraints enforced on connection. Transactional migrations with SHA-256 checksums.
        </p>
      </div>

      <div style="border:1px solid var(--border-subtle);border-radius:8px;padding:16px;background:var(--bg-card);">
        <h3 style="font-size:1rem;color:var(--emerald-400);margin-bottom:8px;">&#10003; Session &amp; CSRF Defense</h3>
        <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.5;">
          Session rotation (<code>session_regenerate_id(true)</code>) enforced on every login. Cookies set with <code>HttpOnly=true</code> and <code>SameSite=Strict</code>. Per-session anti-CSRF token verified on all POST requests.
        </p>
      </div>

      <div style="border:1px solid var(--border-subtle);border-radius:8px;padding:16px;background:var(--bg-card);">
        <h3 style="font-size:1rem;color:var(--emerald-400);margin-bottom:8px;">&#10003; Multi-Tenant Authorization</h3>
        <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.5;">
          Explicit RBAC with deny-by-default permission checks. Strict tenant isolation prevents cross-organization data leakage or IDOR tampering across employer and institutional boundaries.
        </p>
      </div>

      <div style="border:1px solid var(--border-subtle);border-radius:8px;padding:16px;background:var(--bg-card);">
        <h3 style="font-size:1rem;color:var(--emerald-400);margin-bottom:8px;">&#10003; Granular Consent &amp; Audit</h3>
        <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.5;">
          Student records protected by explicit consent controls. Master and granular sharing toggles take immediate effect on future accesses. All sensitive access events immutably audited.
        </p>
      </div>
    </div>
  </section>
  <?php endif; ?>

<?php elseif ($screen === 'affiliation_request'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab" href="/applications">My Applications</a>
    <a class="nav-role-tab" href="/profile">My Profile</a>
    <a class="nav-role-tab" href="/resume">Resume Builder</a>
    <a class="nav-role-tab" href="/achievements">Achievements</a>
    <a class="nav-role-tab active" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab" href="/privacy/settings">Privacy &amp; Consent</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Institutional Verification &middot; Academic Portal</p>
    <h1>Campus Institutional Affiliation</h1>
    <p>Submit your academic credentials to connect your profile with your college placement department.</p>
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

  <div class="card" style="padding:20px;margin-bottom:28px;">
    <h2 style="font-size:1.1rem;margin-bottom:10px;">Institutional Status</h2>
    <?php
      $status = $profile['affiliation_status'] ?? 'NONE';
      $orgName = $profile['college_org_name'] ?? null;
    ?>
    <?php if ($status === 'AFFILIATED' && $orgName): ?>
      <div style="display:flex;align-items:center;gap:12px;">
        <span class="badge badge-observed" style="font-size:0.9rem;padding:6px 14px;">AFFILIATED</span>
        <span style="font-size:1rem;color:var(--text-primary);font-weight:600;"><?= escape($orgName) ?></span>
      </div>
      <p style="margin-top:10px;font-size:0.9rem;color:var(--text-muted);">
        Enrolled as <?= escape($profile['degree'] ?: 'Student') ?> (<?= escape($profile['department'] ?: 'Engineering') ?>) &middot; USN/Roll: <code><?= escape($profile['enrollment_number'] ?: 'N/A') ?></code> &middot; Class of <?= escape((string)($profile['graduation_year'] ?: 'N/A')) ?>.
      </p>
      <div style="margin-top:14px;">
        <a class="button secondary" href="/privacy/settings" style="font-size:0.85rem;padding:6px 14px;">Manage Privacy &amp; Sharing Permissions &rarr;</a>
      </div>
    <?php elseif ($status === 'PENDING'): ?>
      <div style="display:flex;align-items:center;gap:12px;">
        <span class="badge" style="font-size:0.9rem;padding:6px 14px;background:var(--amber-500);color:#000;">PENDING REVIEW</span>
        <span style="font-size:0.95rem;color:var(--text-secondary);">Your affiliation request is awaiting review by college placement officers.</span>
      </div>
    <?php elseif ($status === 'REVOKED'): ?>
      <div style="display:flex;align-items:center;gap:12px;">
        <span class="badge badge-failed" style="font-size:0.9rem;padding:6px 14px;">AFFILIATION REVOKED</span>
        <span style="font-size:0.95rem;color:var(--text-secondary);">Your institutional affiliation was revoked by college staff. You may submit a new verification request below.</span>
      </div>
    <?php else: ?>
      <p style="font-size:0.95rem;color:var(--text-muted);margin:0;">
        You are not currently affiliated with a verified academic institution. Select your institution below and provide your enrollment credentials.
      </p>
    <?php endif; ?>
  </div>

  <?php if ($status !== 'AFFILIATED' && $status !== 'PENDING'): ?>
  <div class="auth-panel" style="max-width:760px;margin-bottom:36px;">
    <h2 style="font-size:1.2rem;margin-bottom:16px;">Request College Affiliation</h2>
    <form action="/affiliation/request" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

      <div class="form-group">
        <label for="college_org_id">Select Academic Institution</label>
        <select id="college_org_id" name="college_org_id" required>
          <option value="">-- Choose Verified College --</option>
          <?php foreach (($approvedColleges ?? []) as $col): ?>
          <option value="<?= (int) $col['id'] ?>"><?= escape($col['name']) ?> (Verified)</option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Only accredited and approved colleges can verify student enrollments.</p>
      </div>

      <div class="form-group">
        <label for="enrollment_number">University Enrollment / USN / Roll Number</label>
        <input id="enrollment_number" name="enrollment_number" maxlength="50" placeholder="e.g. 1DT22CS001" required>
        <p class="hint">Institutional identification number to verify your student enrollment.</p>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label for="degree">Degree Program</label>
          <input id="degree" name="degree" maxlength="100" placeholder="e.g. B.E. / B.Tech Computer Science" required>
        </div>
        <div class="form-group">
          <label for="department">Department</label>
          <input id="department" name="department" maxlength="100" placeholder="e.g. Computer Science & Engineering" required>
        </div>
      </div>

      <div class="form-group">
        <label for="graduation_year">Expected Year of Graduation</label>
        <input id="graduation_year" name="graduation_year" type="number" min="2000" max="2100" value="2026" required>
      </div>

      <div class="form-group">
        <label for="verification_note">Verification Note (Optional)</label>
        <textarea id="verification_note" name="verification_note" rows="3" maxlength="1000" placeholder="Add any additional context for placement cell coordinators..."></textarea>
      </div>

      <button class="button" type="submit" style="align-self:flex-start;margin-top:10px;">
        Submit Affiliation Request
      </button>
    </form>
  </div>
  <?php endif; ?>

  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.2rem;margin-bottom:16px;">Affiliation History &amp; Requests</h2>
    <?php if (!empty($affiliationRequests)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>College Institution</th>
            <th>USN / Roll</th>
            <th>Degree &amp; Dept</th>
            <th>Grad Year</th>
            <th>Submitted</th>
            <th>Status</th>
            <th>Review Notes</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($affiliationRequests as $req): 
            $reqStatus = $req['status'];
            $reqBadge = match ($reqStatus) {
              'APPROVED' => 'badge-observed',
              'REJECTED' => 'badge-failed',
              'REVOKED' => 'badge-failed',
              'PENDING' => 'badge',
              default => 'muted',
            };
          ?>
          <tr>
            <td><strong><?= escape($req['college_name']) ?></strong></td>
            <td><code><?= escape($req['enrollment_number']) ?></code></td>
            <td><?= escape($req['degree']) ?> &middot; <?= escape($req['department']) ?></td>
            <td><?= escape((string) $req['graduation_year']) ?></td>
            <td><?= escape(date('Y-m-d H:i', (int) $req['created_at'])) ?></td>
            <td><span class="badge <?= $reqBadge ?>"><?= escape($reqStatus) ?></span></td>
            <td style="max-width:200px;font-size:0.85rem;color:var(--text-muted);">
              <?= escape($req['review_decision_reason'] ?: 'No notes recorded') ?>
            </td>
            <td>
              <?php if ($reqStatus === 'PENDING'): ?>
              <form action="/affiliation/cancel" method="post" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                <button class="button secondary" type="submit" style="padding:4px 10px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);">Cancel</button>
              </form>
              <?php else: ?>
              <span style="color:var(--text-muted);">&mdash;</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No affiliation requests submitted yet.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'college_affiliations'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/college/dashboard">Campus Placement Intelligence</a>
    <a class="nav-role-tab active" href="/college/affiliations">Affiliation Review Queue</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Placement Coordination &middot; <?= escape($org['org_name'] ?? 'College') ?></p>
    <h1>Institutional Affiliation Review Queue</h1>
    <p>Review and verify student enrollment requests before institutional records are connected.</p>
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

  <div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;align-items:center;">
    <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Status Filter:</span>
    <a class="button <?= ($selectedStatus ?? 'all') === 'all' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:0.85rem;" href="/college/affiliations">All (<?= count($requests ?? []) ?>)</a>
    <a class="button <?= ($selectedStatus ?? '') === 'PENDING' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:0.85rem;" href="/college/affiliations?status=PENDING">Pending Review</a>
    <a class="button <?= ($selectedStatus ?? '') === 'APPROVED' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:0.85rem;" href="/college/affiliations?status=APPROVED">Approved</a>
    <a class="button <?= ($selectedStatus ?? '') === 'REJECTED' ? '' : 'secondary' ?>" style="padding:6px 14px;font-size:0.85rem;" href="/college/affiliations?status=REJECTED">Declined</a>
  </div>

  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.2rem;margin-bottom:16px;">Enrollment Verification Queue (<?= count($requests ?? []) ?>)</h2>
    <?php if (!empty($requests)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Student Name &amp; Email</th>
            <th>Enrollment / USN</th>
            <th>Degree &amp; Department</th>
            <th>Grad Year</th>
            <th>Student Note</th>
            <th>Submitted</th>
            <th>Status</th>
            <th>Decision &amp; Audit Rationale</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $r): 
            $status = $r['status'];
            $badgeClass = match ($status) {
              'APPROVED' => 'badge-observed',
              'REJECTED' => 'badge-failed',
              'REVOKED' => 'badge-failed',
              'PENDING' => 'badge',
              default => 'muted',
            };
          ?>
          <tr>
            <td>
              <strong><?= escape($r['student_name']) ?></strong><br>
              <span style="font-size:0.8rem;color:var(--text-muted);"><?= escape($r['student_email']) ?></span>
            </td>
            <td><code><?= escape($r['enrollment_number']) ?></code></td>
            <td><?= escape($r['degree']) ?><br><span style="font-size:0.75rem;color:var(--text-muted);"><?= escape($r['department']) ?></span></td>
            <td><?= escape((string) $r['graduation_year']) ?></td>
            <td style="max-width:180px;font-size:0.85rem;color:var(--text-muted);"><?= escape($r['verification_note'] ?: 'None') ?></td>
            <td><?= escape(date('Y-m-d H:i', (int) $r['created_at'])) ?></td>
            <td><span class="badge <?= $badgeClass ?>"><?= escape($status) ?></span></td>
            <td>
              <?php if ($status === 'PENDING'): ?>
              <form action="/college/affiliations/review" method="post" style="display:flex;flex-direction:column;gap:6px;margin:0;">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                <input type="text" name="reason" placeholder="Audit verification note..." style="padding:4px 8px;font-size:0.75rem;width:180px;" required>
                <div style="display:flex;gap:6px;">
                  <button class="button" type="submit" name="decision" value="APPROVED" style="padding:4px 8px;font-size:0.75rem;background:var(--emerald-600);">Approve</button>
                  <button class="button secondary" type="submit" name="decision" value="REJECTED" style="padding:4px 8px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);">Decline</button>
                </div>
              </form>
              <?php else: ?>
              <span style="font-size:0.8rem;color:var(--text-muted);">
                <?= escape($r['review_decision_reason'] ?: 'Reviewed') ?><br>
                <em>By: <?= escape($r['reviewer_name'] ?? 'Staff') ?></em>
              </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No affiliation requests in this view.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'privacy_settings'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab" href="/applications">My Applications</a>
    <a class="nav-role-tab" href="/profile">My Profile</a>
    <a class="nav-role-tab" href="/resume">Resume Builder</a>
    <a class="nav-role-tab" href="/achievements">Achievements</a>
    <a class="nav-role-tab" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab active" href="/privacy/settings">Privacy &amp; Consent</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Student Sovereignty &middot; Access Governance</p>
    <h1>Privacy, Sharing &amp; Consent Controls</h1>
    <p>Fine-tune who can access your academic records, structured resume, application histories, and verified credentials.</p>
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

  <div class="auth-panel" style="max-width:760px;margin-bottom:36px;">
    <form action="/privacy/settings" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

      <div style="background:var(--bg-surface);padding:18px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);margin-bottom:24px;">
        <h3 style="margin-top:0;font-size:1.1rem;color:var(--text-primary);">Institutional Placement Sharing (Master Gate)</h3>
        <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:14px;">
          Master switch for your associated college placement department (<?= escape($profile['college_org_name'] ?? 'Associated College') ?>). If turned off, all access is immediately denied with HTTP 403.
        </p>
        <label style="display:flex;align-items:center;gap:12px;cursor:pointer;font-weight:600;">
          <input type="checkbox" name="college_share_consent" value="1" <?= !empty($profile['college_share_consent']) ? 'checked' : '' ?> style="transform:scale(1.2);">
          <span>Enable College Placement Cell Access</span>
        </label>
      </div>

      <div style="background:var(--bg-surface);padding:18px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);margin-bottom:24px;">
        <h3 style="margin-top:0;font-size:1.1rem;color:var(--text-primary);">Granular Institutional Permissions</h3>
        <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:14px;">
          When institutional sharing is enabled, choose exactly which data fields are accessible to placement coordinators:
        </p>

        <div style="display:flex;flex-direction:column;gap:12px;">
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
            <input type="checkbox" name="share_contact_details" value="1" <?= !empty($profile['share_contact_details']) ? 'checked' : '' ?> style="margin-top:3px;">
            <span>
              <strong>Share Contact Information</strong>
              <span style="display:block;font-size:0.8rem;color:var(--text-muted);">Allow placement officers to view your primary email address. If unchecked, email is masked as <code>[Consent Withheld]</code>.</span>
            </span>
          </label>

          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
            <input type="checkbox" name="share_resume" value="1" <?= !empty($profile['share_resume']) ? 'checked' : '' ?> style="margin-top:3px;">
            <span>
              <strong>Share Structured Resumes</strong>
              <span style="display:block;font-size:0.8rem;color:var(--text-muted);">Allow placement officers to inspect your latest resume draft and verified skill citations.</span>
            </span>
          </label>

          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
            <input type="checkbox" name="share_achievements" value="1" <?= !empty($profile['share_achievements']) ? 'checked' : '' ?> style="margin-top:3px;">
            <span>
              <strong>Share Verified Credentials &amp; Achievements</strong>
              <span style="display:block;font-size:0.8rem;color:var(--text-muted);">Display certifications, hackathon awards, and project evidence in institutional reports.</span>
            </span>
          </label>

          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
            <input type="checkbox" name="share_applications" value="1" <?= !empty($profile['share_applications']) ? 'checked' : '' ?> style="margin-top:3px;">
            <span>
              <strong>Share Job Application Outcomes</strong>
              <span style="display:block;font-size:0.8rem;color:var(--text-muted);">Allow placement officers to track your application counts, shortlists, and hiring status across platform employers.</span>
            </span>
          </label>
        </div>
      </div>

      <div style="background:var(--bg-surface);padding:18px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);margin-bottom:24px;">
        <h3 style="margin-top:0;font-size:1.1rem;color:var(--text-primary);">Employer Talent Discovery (Opt-In Directory)</h3>
        <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:14px;">
          Allow verified platform employers to discover your public profile, skills, and achievements in candidate search before you apply to a specific job.
        </p>
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
          <input type="checkbox" name="talent_discovery_opt_in" value="1" <?= !empty($profile['talent_discovery_opt_in']) ? 'checked' : '' ?> style="margin-top:3px;">
          <span>
            <strong>Opt into Employer Talent Discovery</strong>
            <span style="display:block;font-size:0.8rem;color:var(--text-muted);">Strictly opt-in only. Your profile is invisible to talent search unless this is checked.</span>
          </span>
        </label>
      </div>

      <button class="button" type="submit" style="align-self:flex-start;">Save Privacy Preferences</button>
    </form>
  </div>

  <section class="card" style="margin-bottom:32px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h2 style="font-size:1.2rem;margin:0;">Recent Access Audit Events</h2>
      <a class="button secondary" href="/privacy/access-log" style="font-size:0.8rem;padding:4px 12px;">View Complete Audit Trail &rarr;</a>
    </div>
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">
      Immutable audit logs recording every external attempt by college staff or recruiters to inspect your sensitive records.
    </p>

    <?php if (!empty($recentLogs)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Accessor</th>
            <th>Organization</th>
            <th>Record Type</th>
            <th>Context</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentLogs as $log): ?>
          <tr>
            <td><?= escape(date('Y-m-d H:i:s', (int) $log['accessed_at'])) ?></td>
            <td>
              <strong><?= escape($log['accessor_name']) ?></strong><br>
              <span class="badge muted" style="font-size:0.75rem;"><?= escape(ucfirst($log['accessor_role'])) ?></span>
            </td>
            <td><?= escape($log['organization_name'] ?: 'Platform') ?></td>
            <td><code><?= escape($log['record_type']) ?></code></td>
            <td style="font-size:0.85rem;color:var(--text-muted);"><?= escape($log['access_context']) ?></td>
            <td>
              <?php if ((int)$log['granted'] === 1): ?>
              <span class="badge badge-observed">Granted</span>
              <?php else: ?>
              <span class="badge badge-failed">Denied (No Consent)</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No sensitive record access events recorded yet.</p>
    </div>
    <?php endif; ?>
  </section>

  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.2rem;margin-top:0;margin-bottom:8px;">Linked Authentication Identities</h2>
    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">
      Federated OAuth 2.0 / OpenID Connect credentials bound to this student account. Unlinking prevents access via that provider.
    </p>

    <?php $linkedIdentities = $linkedIdentities ?? []; ?>
    <?php $configuredProviders = $configuredProviders ?? []; ?>

    <?php if (!empty($linkedIdentities)): ?>
    <div class="table-container" style="margin-bottom:20px;">
      <table>
        <thead>
          <tr>
            <th>Provider</th>
            <th>Verified Identity Subject</th>
            <th>Provider Email</th>
            <th>Bound At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($linkedIdentities as $ident): ?>
          <tr>
            <td>
              <strong style="text-transform:capitalize;"><?= escape($ident['provider']) ?></strong>
            </td>
            <td><code><?= escape(substr($ident['provider_subject_id'], 0, 16)) ?>...</code></td>
            <td><?= escape($ident['provider_email'] ?? '—') ?></td>
            <td style="font-size:0.85rem;color:var(--text-muted);"><?= escape(date('Y-m-d H:i', (int) $ident['created_at'])) ?></td>
            <td>
              <form action="/auth/unlink" method="post" style="display:inline;" onsubmit="return confirm('Unlink this social identity?');">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="provider" value="<?= escape($ident['provider']) ?>">
                <button class="button secondary" type="submit" style="font-size:0.75rem;padding:4px 10px;color:var(--rose-400);">Unlink</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div style="background:var(--bg-surface-elevated);padding:14px;border-radius:6px;border:1px solid var(--border-subtle);margin-bottom:18px;">
      <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">No third-party social identities are currently bound to your account.</p>
    </div>
    <?php endif; ?>

    <?php
      $boundProviders = array_column($linkedIdentities, 'provider');
      $availableToLink = array_filter(array_keys($configuredProviders), fn($p) => !in_array($p, $boundProviders, true));
    ?>
    <?php if (!empty($availableToLink)): ?>
    <div style="margin-top:16px;">
      <h3 style="font-size:0.95rem;margin-bottom:8px;color:var(--text-primary);">Link Additional Provider</h3>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php foreach ($availableToLink as $p): ?>
        <form action="/auth/link" method="post" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
          <input type="hidden" name="provider" value="<?= escape($p) ?>">
          <button class="button secondary" type="submit" style="font-size:0.85rem;padding:6px 14px;">
            Link <?= escape(ucfirst($p)) ?> Account
          </button>
        </form>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'privacy_access_log'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab" href="/applications">My Applications</a>
    <a class="nav-role-tab" href="/profile">My Profile</a>
    <a class="nav-role-tab" href="/resume">Resume Builder</a>
    <a class="nav-role-tab" href="/achievements">Achievements</a>
    <a class="nav-role-tab" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab active" href="/privacy/settings">Privacy &amp; Consent</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Audit Integrity &middot; Access Logging</p>
    <h1>Sensitive Record Access Audit History</h1>
    <p>Comprehensive audit log of institutional placement officers and verified company recruiters who inspected your candidate records.</p>
  </section>

  <div style="margin-bottom:20px;">
    <a class="button secondary" href="/privacy/settings" style="font-size:0.85rem;padding:6px 14px;">&larr; Return to Privacy Settings</a>
  </div>

  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.2rem;margin-bottom:16px;">Complete Audit Trail (<?= count($accessLogs ?? []) ?>)</h2>
    <?php if (!empty($accessLogs)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Accessor Name</th>
            <th>Role</th>
            <th>Organization</th>
            <th>Record Type</th>
            <th>Context Action</th>
            <th>Result</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessLogs as $log): ?>
          <tr>
            <td><?= escape(date('Y-m-d H:i:s', (int) $log['accessed_at'])) ?></td>
            <td><strong><?= escape($log['accessor_name']) ?></strong></td>
            <td><span class="badge muted" style="font-size:0.75rem;"><?= escape(ucfirst($log['accessor_role'])) ?></span></td>
            <td><?= escape($log['organization_name'] ?: 'Platform Admin') ?></td>
            <td><code><?= escape($log['record_type']) ?></code></td>
            <td style="font-size:0.85rem;color:var(--text-muted);"><?= escape($log['access_context']) ?></td>
            <td>
              <?php if ((int)$log['granted'] === 1): ?>
              <span class="badge badge-observed">Granted</span>
              <?php else: ?>
              <span class="badge badge-failed">Denied (Consent Withheld)</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No access logs recorded for your profile yet.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'achievements'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/dashboard">Evidence Dashboard</a>
    <a class="nav-role-tab" href="/jobs">Browse Jobs</a>
    <a class="nav-role-tab" href="/applications">My Applications</a>
    <a class="nav-role-tab" href="/profile">My Profile</a>
    <a class="nav-role-tab" href="/resume">Resume Builder</a>
    <a class="nav-role-tab active" href="/achievements">Achievements</a>
    <a class="nav-role-tab" href="/affiliation/request">College Affiliation</a>
    <a class="nav-role-tab" href="/privacy/settings">Privacy &amp; Consent</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Student Credentials &middot; Verified Portfolio</p>
    <h1>Student Achievements &amp; Credentials</h1>
    <p>Manage structured credentials, certifications, hackathon awards, and verified technical projects.</p>
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

  <div class="auth-panel" style="max-width:760px;margin-bottom:36px;">
    <h2 style="font-size:1.2rem;margin-bottom:16px;">Add New Achievement or Credential</h2>
    <form action="/achievements" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label for="category">Category</label>
          <select id="category" name="category" required>
            <option value="certification">Certification</option>
            <option value="hackathon">Hackathon / Competition</option>
            <option value="project" selected>Technical Project</option>
            <option value="award">Honors &amp; Awards</option>
            <option value="publication">Research Publication</option>
            <option value="academic">Academic Excellence</option>
          </select>
        </div>
        <div class="form-group">
          <label for="title">Title / Name</label>
          <input id="title" name="title" maxlength="150" placeholder="e.g. Code for a Billion Hackathon Winner" required>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label for="issuer">Issuing Organization</label>
          <input id="issuer" name="issuer" maxlength="150" placeholder="e.g. AWS, IEEE, HackerEarth">
        </div>
        <div class="form-group">
          <label for="issue_date">Issue Date</label>
          <input id="issue_date" name="issue_date" maxlength="30" placeholder="e.g. November 2026">
        </div>
      </div>

      <div class="form-group">
        <label for="credential_url">Credential / Verification URL (Optional)</label>
        <input id="credential_url" name="credential_url" type="url" maxlength="500" placeholder="https://example.com/certificate/12345">
        <p class="hint">Public link where recruiters or placement officers can independently verify this credential.</p>
      </div>

      <?php if (!empty($assessments)): ?>
      <div class="form-group">
        <label for="evidence_assessment_id">Link Verified GitHub Assessment (Optional)</label>
        <select id="evidence_assessment_id" name="evidence_assessment_id">
          <option value="">-- Do not link assessment --</option>
          <?php foreach ($assessments as $ev): ?>
          <option value="<?= (int) $ev['id'] ?>">
            <?= escape($ev['repository']) ?> (<?= escape(substr($ev['commit_sha'] ?? '', 0, 7)) ?>) &middot; <?= escape($ev['target_role']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Cites deterministic commit evidence evaluated by the EIDOLON assessment engine.</p>
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label for="description">Summary / Impact Details</label>
        <textarea id="description" name="description" rows="3" maxlength="1000" placeholder="Summarize your key contribution, ranking, technologies used, or measurable results..."></textarea>
      </div>

      <button class="button" type="submit" style="align-self:flex-start;">Add Achievement</button>
    </form>
  </div>

  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.2rem;margin-bottom:16px;">Your Verified Achievements (<?= count($achievements ?? []) ?>)</h2>
    <?php if (!empty($achievements)): ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
      <?php foreach ($achievements as $ach): ?>
      <div style="padding:16px;background:var(--bg-surface);border-radius:var(--radius-sm);border:1px solid var(--border-subtle);display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
            <span class="badge" style="font-size:0.75rem;text-transform:uppercase;"><?= escape($ach['category']) ?></span>
            <strong style="font-size:1.05rem;color:var(--text-primary);"><?= escape($ach['title']) ?></strong>
            <?php if (!empty($ach['issuer'])): ?>
            <span style="font-size:0.85rem;color:var(--text-muted);">&middot; <?= escape($ach['issuer']) ?></span>
            <?php endif; ?>
            <?php if (!empty($ach['issue_date'])): ?>
            <span style="font-size:0.85rem;color:var(--text-muted);">(<?= escape($ach['issue_date']) ?>)</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($ach['description'])): ?>
          <p style="font-size:0.9rem;color:var(--text-secondary);margin:6px 0;"><?= escape($ach['description']) ?></p>
          <?php endif; ?>
          <div style="display:flex;gap:12px;margin-top:8px;align-items:center;flex-wrap:wrap;">
            <?php if (!empty($ach['credential_url'])): ?>
            <a href="<?= escape($ach['credential_url']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:0.8rem;color:var(--brand-accent);">
              Verify Credential &rarr;
            </a>
            <?php endif; ?>
            <?php if (!empty($ach['assessment_repo'])): ?>
            <span class="badge badge-observed" style="font-size:0.75rem;">
              Linked Assessment: <?= escape($ach['assessment_repo']) ?>@<?= escape(substr($ach['assessment_sha'] ?? '', 0, 7)) ?>
            </span>
            <?php endif; ?>
          </div>
        </div>
        <form action="/achievements/delete" method="post" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int) $ach['id'] ?>">
          <button class="button secondary" type="submit" style="padding:4px 10px;font-size:0.75rem;color:var(--rose-400);border-color:var(--rose-500);">Remove</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No achievements added yet. Use the form above to add certifications, hackathon projects, or awards.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'company_talent'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/company/dashboard">Company Overview</a>
    <a class="nav-role-tab" href="/company/jobs/new">+ Post New Position</a>
    <a class="nav-role-tab" href="/company/applicants">Candidate Review</a>
    <a class="nav-role-tab active" href="/company/talent">Talent Discovery</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Verified Talent Search &middot; <?= escape($org['org_name'] ?? 'Company') ?></p>
    <h1>Candidate Talent Discovery</h1>
    <p>Discover student engineers who have explicitly opted into recruitment discovery with verified skills and portfolio achievements.</p>
  </section>

  <div class="card" style="padding:20px;margin-bottom:24px;">
    <form action="/company/talent" method="get" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:0;">
      <input type="text" name="q" value="<?= escape($searchQuery ?? '') ?>" placeholder="Search by skill (e.g. Python, React, Go) or degree program..." style="flex:1;min-width:240px;padding:8px 12px;font-size:0.9rem;">
      <button class="button" type="submit" style="padding:8px 18px;">Search Candidates</button>
      <?php if (!empty($searchQuery)): ?>
      <a class="button secondary" href="/company/talent" style="padding:8px 14px;">Clear Filter</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="notice" style="margin-bottom:24px;">
    <h2>Consent-Guaranteed Discovery Active</h2>
    <p>This directory displays only candidates who have explicitly enabled <code>talent_discovery_opt_in = 1</code> in their private settings. Unconsenting students remain completely hidden.</p>
  </div>

  <section class="card" style="margin-bottom:32px;">
    <h2 style="font-size:1.2rem;margin-bottom:16px;">Opted-in Engineering Candidates (<?= count($talentList ?? []) ?>)</h2>
    <?php if (!empty($talentList)): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;">
      <?php foreach ($talentList as $cand): ?>
      <div style="background:var(--bg-surface);padding:18px;border-radius:var(--radius-sm);border:1px solid var(--border-subtle);display:flex;flex-direction:column;justify-content:space-between;">
        <div>
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
            <strong style="font-size:1.1rem;color:var(--text-primary);"><?= escape($cand['student_name']) ?></strong>
            <span class="badge badge-observed" style="font-size:0.75rem;">Opted In</span>
          </div>
          <p style="font-size:0.85rem;color:var(--text-muted);margin:0 0 10px;">
            <?= escape($cand['degree'] ?: 'Engineering Student') ?> &middot; <?= escape($cand['college'] ?: 'Institution') ?>
            <?php if (!empty($cand['graduation_year'])): ?>
            (Class of <?= escape((string) $cand['graduation_year']) ?>)
            <?php endif; ?>
          </p>

          <?php if (!empty($cand['skills'])): ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
            <?php foreach (array_slice(explode(',', $cand['skills']), 0, 5) as $sk): ?>
            <span class="badge muted" style="font-size:0.75rem;"><?= escape(trim($sk)) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($cand['bio'])): ?>
          <p style="font-size:0.85rem;color:var(--text-secondary);margin:0 0 12px;line-height:1.4;">
            <?= escape(substr($cand['bio'], 0, 160)) ?><?= strlen($cand['bio']) > 160 ? '...' : '' ?>
          </p>
          <?php endif; ?>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border-subtle);padding-top:10px;margin-top:8px;font-size:0.8rem;color:var(--text-muted);">
          <span><?= (int) ($cand['achievements_count'] ?? 0) ?> Credentials &middot; <?= (int) ($cand['resumes_count'] ?? 0) ?> Resumes</span>
          <?php if (!empty($cand['github_username'])): ?>
          <code>@<?= escape($cand['github_username']) ?></code>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="notice" style="margin:12px 0;">
      <p>No candidates found matching the search criteria.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'company_candidate'): ?>
  <div class="student-nav no-print" style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--border-subtle);padding-bottom:12px;overflow-x:auto;">
    <a class="nav-role-tab" href="/company/dashboard">Company Overview</a>
    <a class="nav-role-tab" href="/company/jobs/new">+ Post New Position</a>
    <a class="nav-role-tab" href="/company/applicants">&larr; Candidate Review</a>
    <a class="nav-role-tab" href="/company/talent">Talent Discovery</a>
  </div>

  <?php
    $app = $candidateBundle['application'] ?? [];
    $candProfile = $candidateBundle['profile'] ?? [];
    $candResume = $candidateBundle['resume'] ?? null;
    $candAchievements = $candidateBundle['achievements'] ?? [];
  ?>

  <section class="hero">
    <p class="eyebrow">Application Dossier &middot; <?= escape($org['org_name'] ?? 'Company') ?></p>
    <h1><?= escape($candProfile['name'] ?? 'Candidate') ?></h1>
    <p style="margin-top:4px;">
      Applied for <strong><?= escape($app['job_title'] ?? '') ?></strong> &middot;
      Status: <span class="badge <?= ($app['status'] ?? '') === 'shortlisted' ? 'badge-observed' : 'muted' ?>"><?= escape(ucfirst($app['status'] ?? '')) ?></span> &middot;
      Submitted: <?= escape(date('Y-m-d H:i', (int) ($app['created_at'] ?? time()))) ?>
    </p>
  </section>

  <div class="card" style="padding:16px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
      <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Candidate Email:</span>
      <span style="font-size:0.95rem;color:var(--text-primary);margin-left:6px;"><?= escape($candProfile['email'] ?? '') ?></span>
      <?php if (!empty($candProfile['github_username'])): ?>
      <span style="margin:0 8px;color:var(--border-subtle);">|</span>
      <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">GitHub:</span>
      <code>github.com/<?= escape($candProfile['github_username']) ?></code>
      <?php endif; ?>
    </div>

    <form action="/company/applicants/status" method="post" style="display:flex;gap:8px;margin:0;">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <input type="hidden" name="application_id" value="<?= (int) ($app['application_id'] ?? 0) ?>">
      <?php if (($app['status'] ?? '') !== 'shortlisted'): ?>
      <button class="button" type="submit" name="status" value="shortlisted" style="padding:6px 14px;font-size:0.85rem;background:var(--emerald-600);">Shortlist Candidate</button>
      <?php endif; ?>
      <?php if (($app['status'] ?? '') !== 'rejected'): ?>
      <button class="button secondary" type="submit" name="status" value="rejected" style="padding:6px 14px;font-size:0.85rem;color:var(--rose-400);border-color:var(--rose-500);">Decline Application</button>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!empty($app['notes'])): ?>
  <div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="margin-top:0;font-size:1.05rem;">Candidate Statement / Cover Note</h3>
    <p style="font-size:0.9rem;color:var(--text-secondary);margin:0;line-height:1.5;"><?= escape($app['notes']) ?></p>
  </div>
  <?php endif; ?>

  <?php if ($candResume): ?>
  <div class="card" style="padding:24px;margin-bottom:24px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
      <div>
        <h2 style="font-size:1.3rem;margin:0 0 4px;"><?= escape($candResume['title'] ?? 'Resume') ?></h2>
        <span style="font-size:0.85rem;color:var(--text-muted);">Updated <?= escape(date('Y-m-d', (int) ($candResume['updated_at'] ?? time()))) ?></span>
      </div>
      <?php if (!empty($candResume['assessment_repo'])): ?>
      <div style="text-align:right;">
        <span class="badge badge-observed" style="font-size:0.8rem;">Verified GitHub Assessment</span><br>
        <code style="font-size:0.75rem;"><?= escape($candResume['assessment_repo']) ?>@<?= escape(substr($candResume['assessment_sha'] ?? '', 0, 7)) ?></code>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($candResume['summary'])): ?>
    <p style="font-size:0.95rem;color:var(--text-secondary);line-height:1.5;margin-bottom:20px;"><?= escape($candResume['summary']) ?></p>
    <?php endif; ?>

    <?php if (!empty($candResume['education'])): ?>
    <h3 style="font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-top:20px;border-bottom:1px solid var(--border-subtle);padding-bottom:6px;">Education</h3>
    <div style="margin-bottom:16px;">
      <?php foreach ($candResume['education'] as $edu): ?>
      <div style="margin-bottom:10px;">
        <strong><?= escape($edu['institution'] ?? '') ?></strong> &middot; <?= escape($edu['degree'] ?? '') ?>
        <span style="float:right;font-size:0.85rem;color:var(--text-muted);"><?= escape($edu['year'] ?? '') ?></span>
        <?php if (!empty($edu['gpa'])): ?>
        <div style="font-size:0.85rem;color:var(--text-muted);">GPA/Marks: <?= escape($edu['gpa']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($candResume['experience'])): ?>
    <h3 style="font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-top:20px;border-bottom:1px solid var(--border-subtle);padding-bottom:6px;">Experience</h3>
    <div style="margin-bottom:16px;">
      <?php foreach ($candResume['experience'] as $exp): ?>
      <div style="margin-bottom:12px;">
        <strong><?= escape($exp['role'] ?? '') ?></strong> &middot; <?= escape($exp['company'] ?? '') ?>
        <span style="float:right;font-size:0.85rem;color:var(--text-muted);"><?= escape($exp['start'] ?? '') ?> - <?= escape($exp['end'] ?? '') ?></span>
        <?php if (!empty($exp['desc'])): ?>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin:4px 0 0;line-height:1.4;"><?= escape($exp['desc']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($candResume['projects'])): ?>
    <h3 style="font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-top:20px;border-bottom:1px solid var(--border-subtle);padding-bottom:6px;">Technical Projects</h3>
    <div style="margin-bottom:16px;">
      <?php foreach ($candResume['projects'] as $proj): ?>
      <div style="margin-bottom:12px;">
        <strong><?= escape($proj['name'] ?? '') ?></strong>
        <?php if (!empty($proj['url'])): ?>
        <a href="<?= escape($proj['url']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:0.8rem;color:var(--brand-accent);margin-left:6px;">[Code / Link]</a>
        <?php endif; ?>
        <?php if (!empty($proj['desc'])): ?>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin:4px 0 0;line-height:1.4;"><?= escape($proj['desc']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($candResume['assessment_tasks'])): ?>
    <h3 style="font-size:1rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-top:20px;border-bottom:1px solid var(--border-subtle);padding-bottom:6px;">Verified Portfolio Engineering Observations</h3>
    <div style="margin-bottom:16px;">
      <?php foreach ($candResume['assessment_tasks'] as $t): ?>
      <div style="margin-bottom:8px;font-size:0.85rem;">
        <span class="badge" style="font-size:0.75rem;margin-right:6px;">Priority <?= (int)$t['priority'] ?></span>
        <strong><?= escape($t['title']) ?></strong>
        <div style="color:var(--text-muted);margin-left:24px;margin-top:2px;">Acceptance: <?= escape($t['acceptance']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <section class="card" style="margin-bottom:32px;">
    <h3 style="font-size:1.1rem;margin-top:0;margin-bottom:14px;">Verified Credentials &amp; Achievements (<?= count($candAchievements) ?>)</h3>
    <?php if (!empty($candAchievements)): ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($candAchievements as $ca): ?>
      <div style="padding:12px;background:var(--bg-surface);border-radius:var(--radius-sm);border:1px solid var(--border-subtle);">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span class="badge" style="font-size:0.75rem;"><?= escape($ca['category']) ?></span>
          <strong><?= escape($ca['title']) ?></strong>
          <?php if (!empty($ca['issuer'])): ?>
          <span style="font-size:0.85rem;color:var(--text-muted);">&middot; <?= escape($ca['issuer']) ?></span>
          <?php endif; ?>
          <?php if (!empty($ca['issue_date'])): ?>
          <span style="font-size:0.85rem;color:var(--text-muted);">(<?= escape($ca['issue_date']) ?>)</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($ca['description'])): ?>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin:6px 0 0;"><?= escape($ca['description']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="font-size:0.85rem;color:var(--text-muted);margin:0;">No additional achievements submitted by candidate.</p>
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
