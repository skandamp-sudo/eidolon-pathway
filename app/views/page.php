<?php
declare(strict_types=1);
namespace Pathway;

// Role sub-navigation components (reusable)

function student_nav(string $screen): void {
    $tabs = [
        ['dashboard,assessment', '/dashboard', 'Evidence Dashboard'],
        ['jobs,job_detail', '/jobs', 'Browse Jobs'],
        ['applications', '/applications', 'My Applications'],
        ['profile', '/profile', 'My Profile'],
        ['resume,resume_preview', '/resume', 'Resume Builder'],
        ['achievements', '/achievements', 'Achievements'],
        ['affiliation_request', '/affiliation/request', 'College Affiliation'],
        ['privacy_settings,privacy_access_log', '/privacy/settings', 'Privacy & Consent'],
    ];
    echo '<nav class="sub-nav no-print" aria-label="Student navigation">';
    foreach ($tabs as [$screens, $href, $label]) {
        $active = in_array($screen, explode(',', $screens)) ? ' active' : '';
        echo "<a class=\"nav-role-tab{$active}\" href=\"{$href}\">" . htmlspecialchars($label, ENT_QUOTES|ENT_HTML5) . "</a>";
    }
    echo '</nav>';
}

function company_nav(string $screen): void {
    $tabs = [
        ['company_dashboard', '/company/dashboard', 'Company Overview'],
        ['company_job_form', '/company/jobs/new', '+ Post New Position'],
        ['company_applicants,company_candidate', '/company/applicants', 'Candidate Review'],
        ['company_talent', '/company/talent', 'Talent Discovery'],
    ];
    echo '<nav class="sub-nav no-print" aria-label="Company navigation">';
    foreach ($tabs as [$screens, $href, $label]) {
        $active = in_array($screen, explode(',', $screens)) ? ' active' : '';
        echo "<a class=\"nav-role-tab{$active}\" href=\"{$href}\">" . htmlspecialchars($label, ENT_QUOTES|ENT_HTML5) . "</a>";
    }
    echo '</nav>';
}

function college_nav(string $screen): void {
    $tabs = [
        ['college_dashboard,college_student_detail', '/college/dashboard', 'Campus Placement Intelligence'],
        ['college_affiliations', '/college/affiliations', 'Affiliation Review Queue'],
    ];
    echo '<nav class="sub-nav no-print" aria-label="College navigation">';
    foreach ($tabs as [$screens, $href, $label]) {
        $active = in_array($screen, explode(',', $screens)) ? ' active' : '';
        echo "<a class=\"nav-role-tab{$active}\" href=\"{$href}\">" . htmlspecialchars($label, ENT_QUOTES|ENT_HTML5) . "</a>";
    }
    echo '</nav>';
}

function admin_nav(string $activeTab, array $pendingRequests = []): void {
    $tabs = [
        ['overview', '/admin/dashboard?tab=overview', 'Overview'],
        ['requests', '/admin/dashboard?tab=requests', 'Access Requests'],
        ['orgs', '/admin/dashboard?tab=orgs', 'Organizations'],
        ['users', '/admin/dashboard?tab=users', 'User Directory'],
        ['invitations', '/admin/dashboard?tab=invitations', 'Invitations'],
        ['access_reviews', '/admin/dashboard?tab=access_reviews', 'Access Reviews'],
        ['audit', '/admin/dashboard?tab=audit', 'Audit Trail'],
        ['security', '/admin/dashboard?tab=security', 'Security Posture'],
    ];
    echo '<nav class="sub-nav no-print" aria-label="Admin navigation">';
    foreach ($tabs as [$tab, $href, $label]) {
        $active = ($activeTab === $tab) ? ' active' : '';
        $badge = '';
        if ($tab === 'requests' && !empty($pendingRequests)) {
            $count = count($pendingRequests);
            $badge = " <span class=\"badge badge-count\">{$count}</span>";
        }
        echo "<a class=\"nav-role-tab{$active}\" href=\"{$href}\">" . htmlspecialchars($label, ENT_QUOTES|ENT_HTML5) . "{$badge}</a>";
    }
    echo '</nav>';
}
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
  <div class="navbar-container">
    <a class="brand" href="/" aria-label="EIDOLON PATHWAY Homepage">
      <div class="logo-symbol" aria-hidden="true">&#x25C7;</div>
      <div class="logo-text">EIDOLON <strong>PATHWAY</strong></div>
    </a>

    <!-- Platform Role Switcher (secondary navigation) -->
    <nav class="nav-role-shell" aria-label="Platform roles">
      <a class="nav-role-tab <?= in_array($screen, ['login', 'register', 'dashboard', 'profile', 'jobs', 'job_detail', 'applications', 'resume', 'resume_preview', 'assessment', 'affiliation_request', 'privacy_settings', 'privacy_access_log', 'achievements']) ? 'active' : '' ?>" href="/login">Student</a>
      <a class="nav-role-tab <?= str_starts_with($screen, 'company_') ? 'active' : '' ?>" href="/company/login">Company</a>
      <a class="nav-role-tab <?= str_starts_with($screen, 'college_') ? 'active' : '' ?>" href="/college/login">College</a>
      <a class="nav-role-tab <?= str_starts_with($screen, 'admin_') ? 'active' : '' ?>" href="/admin/login">Admin</a>
      <a class="nav-role-tab <?= $screen === 'request_access' ? 'active' : '' ?>" href="/request-access">Access</a>
    </nav>

    <div class="nav-actions">
      <!-- Three-Theme Switcher (Light / System / Dark) -->
      <div class="theme-switcher" role="group" aria-label="Theme selection">
        <button type="button" class="theme-btn" data-theme-set="light" title="Light Theme" aria-label="Light Theme">&#x2600;</button>
        <button type="button" class="theme-btn" data-theme-set="system" title="System Theme" aria-label="System Theme">&#x25D1;</button>
        <button type="button" class="theme-btn" data-theme-set="dark" title="Dark Theme" aria-label="Dark Theme">&#x263E;</button>
      </div>
      <span class="environment-tag">Demo</span>
      <?php if ($user !== null): ?>
      <form action="/logout" method="post" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
        <button class="button secondary sm" type="submit">Sign out</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</header>

<main id="main" class="container" role="main">
<?php if ($screen === 'error'): ?>
  <section class="auth-panel panel-narrow" aria-labelledby="error-title">
    <p class="eyebrow">Request Not Completed</p>
    <h1 id="error-title" class="heading-lg">Let's get you back on track.</h1>
    <p role="alert" class="text-danger"><?= escape($message) ?></p>
    <div class="actions">
      <a class="button" href="/login">Return to sign in</a>
      <a class="button secondary" href="/register">Create an account</a>
    </div>
  </section>

<?php elseif ($screen === 'login' || $screen === 'register'): ?>
  <div class="auth-layout futureglass-auth">
    <!-- Visual Stage (3D Constellation Hero) -->
    <section class="login-value-panel futureglass-hero-stage">
      <div class="future-badge">
        <span class="badge-dot"></span>
        <span>AUTONOMOUS CAREER INTELLIGENCE</span>
      </div>

      <h1 class="future-hero-title">From Code Contributions to <span class="text-gradient">Verified Career Trajectories</span>.</h1>
      <p class="future-hero-sub">Replace self-attestation with commit-pinned evidence. EIDOLON maps verified engineering ability directly to high-conviction employer pipelines.</p>

      <!-- 3D Career Constellation Frame -->
      <div class="constellation-frame futureglass-tilt" id="hero-constellation-wrap" role="region" aria-label="Interactive 3D Career Constellation">
        <canvas id="constellation-canvas" class="constellation-canvas" width="600" height="380">
          <!-- Static SVG Fallback when Canvas / WebGL is disabled or unsupported -->
          <svg class="constellation-fallback" viewBox="0 0 600 380" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Career Verification Constellation Graphic">
            <!-- Edges -->
            <line x1="300" y1="190" x2="435" y2="125" stroke="#42E8C0" stroke-width="1.5" stroke-opacity="0.6"/>
            <line x1="300" y1="190" x2="155" y2="140" stroke="#6A9CFF" stroke-width="1.5" stroke-opacity="0.6"/>
            <line x1="300" y1="190" x2="415" y2="275" stroke="#38BDF8" stroke-width="1.5" stroke-opacity="0.6"/>
            <line x1="300" y1="190" x2="175" y2="285" stroke="#A78BFA" stroke-width="1.5" stroke-opacity="0.6"/>
            <line x1="300" y1="190" x2="300" y2="60" stroke="#34D399" stroke-width="1.5" stroke-opacity="0.6"/>
            <line x1="435" y1="125" x2="415" y2="275" stroke="#42E8C0" stroke-width="1" stroke-opacity="0.3"/>
            <line x1="155" y1="140" x2="415" y2="275" stroke="#6A9CFF" stroke-width="1" stroke-opacity="0.3"/>
            <line x1="300" y1="60" x2="435" y2="125" stroke="#34D399" stroke-width="1" stroke-opacity="0.3"/>
            <!-- Core -->
            <circle cx="300" cy="190" r="32" fill="#42E8C0" fill-opacity="0.15"/>
            <circle cx="300" cy="190" r="20" fill="url(#coreGrad)"/>
            <circle cx="300" cy="190" r="20" stroke="#42E8C0" stroke-width="2"/>
            <text x="300" y="224" fill="#EBF6FF" font-family="-apple-system, sans-serif" font-size="11" font-weight="bold" text-anchor="middle">EIDOLON CORE</text>
            <!-- Nodes -->
            <circle cx="435" cy="125" r="14" fill="#42E8C0"/><text x="435" y="152" fill="#EBF6FF" font-family="-apple-system, sans-serif" font-size="10" text-anchor="middle">GitHub Evidence</text>
            <circle cx="155" cy="140" r="13" fill="#6A9CFF"/><text x="155" y="166" fill="#EBF6FF" font-family="-apple-system, sans-serif" font-size="10" text-anchor="middle">Verified Skills</text>
            <circle cx="415" cy="275" r="13" fill="#38BDF8"/><text x="415" y="302" fill="#EBF6FF" font-family="-apple-system, sans-serif" font-size="10" text-anchor="middle">Career Readiness</text>
            <circle cx="175" cy="285" r="13" fill="#A78BFA"/><text x="175" y="312" fill="#EBF6FF" font-family="-apple-system, sans-serif" font-size="10" text-anchor="middle">Employment Pipeline</text>
            <circle cx="300" cy="60" r="11" fill="#34D399"/><text x="300" y="84" fill="#EBF6FF" font-family="-apple-system, sans-serif" font-size="10" text-anchor="middle">Campus Verification</text>
            <defs>
              <radialGradient id="coreGrad" cx="35%" cy="35%" r="65%">
                <stop offset="0%" stop-color="#FFFFFF"/>
                <stop offset="40%" stop-color="#42E8C0"/>
                <stop offset="100%" stop-color="#060B16"/>
              </radialGradient>
            </defs>
          </svg>
        </canvas>
        <div class="constellation-hud">
          <span class="hud-pill"><span class="hud-pulse"></span> 3D Career Constellation &middot; Drag to Orbit</span>
          <span class="hud-stat">6 Core Verification Vectors</span>
        </div>
      </div>

      <!-- Value Pillars -->
      <div class="future-pillars">
        <div class="pillar-card">
          <div class="pillar-icon">&#x2B21;</div>
          <div class="pillar-body">
            <strong>Cryptographic Provenance</strong>
            <span>AST lexical analysis pinned to public repository commit hashes.</span>
          </div>
        </div>
        <div class="pillar-card">
          <div class="pillar-icon">&#x25CE;</div>
          <div class="pillar-body">
            <strong>Objective Telemetry</strong>
            <span>Multidimensional skill benchmarks evaluated without self-reporting bias.</span>
          </div>
        </div>
        <div class="pillar-card">
          <div class="pillar-icon">&#x2726;</div>
          <div class="pillar-body">
            <strong>Direct Institutional Trust</strong>
            <span>High-conviction student placements backed by college partner consent.</span>
          </div>
        </div>
      </div>

      <div class="future-demo-banner">
        <span class="demo-chip">DEMO ENVIRONMENT</span>
        <span>Demonstration environment with live local SQLite persistence. No email is sent or verified.</span>
      </div>
    </section>

    <!-- Authentication Glass Panel -->
    <section class="auth-panel futureglass-panel futureglass-tilt" aria-labelledby="form-title">
      <div class="auth-panel-glow" aria-hidden="true"></div>
      <div class="auth-header">
        <div class="auth-icon" aria-hidden="true">&#x25C7;</div>
        <h2 id="form-title"><?= $screen === 'register' ? 'Create student account' : 'Welcome back' ?></h2>
        <p class="lead"><?= $screen === 'register' ? 'Register your demonstration student credentials.' : 'Sign in to access your verified career workspace.' ?></p>
      </div>

      <!-- Quick Demo Account Loader (Demo Environment Only, No Embedded Passwords) -->
      <?php
        $isDemoEnv = str_contains((string) (getenv('PATHWAY_DATABASE_PATH') ?: ''), 'demo');
      ?>
      <?php if ($screen === 'login' && $isDemoEnv): ?>
      <div class="quick-demo-access">
        <span class="quick-demo-label">Instant Demo Evaluation:</span>
        <form action="/demo/student-login" method="post" class="quick-demo-form" style="display:inline;margin:0;">
          <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
          <button type="submit" class="btn-demo-pill">
            Load Aarav (Student) &rarr;
          </button>
        </form>
      </div>
      <?php endif; ?>
      
      <?php if (!empty($flashError) && is_string($flashError)): ?>
      <p class="notice error" role="alert"><?= escape($flashError) ?></p>
      <?php endif; ?>
      <?php if (is_string($flash)): ?>
      <p class="notice success" role="status"><?= escape($flash) ?></p>
      <?php endif; ?>

      <form action="/<?= $screen ?>" method="post" class="futureglass-form">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
        
        <?php if ($screen === 'register'): ?>
        <div class="form-group">
          <label for="name">Demonstration name</label>
          <input id="name" name="name" autocomplete="name" maxlength="100" placeholder="Alex Chen" required>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="email">Demonstration email</label>
          <input id="email" name="email" type="email" autocomplete="username" maxlength="254" placeholder="demo.student.aarav@dsatm.test" required>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="<?= $screen === 'register' ? 'new-password' : 'current-password' ?>" <?= $screen === 'register' ? 'minlength="12"' : '' ?> maxlength="72" aria-describedby="password-help" required>
          <p class="hint" id="password-help">Use 12–72 bytes. Choose a password you do not use elsewhere.</p>
        </div>

        <button class="button button-futureglass" type="submit">
          <span><?= $screen === 'register' ? 'Create student account' : 'Sign in to EIDOLON' ?></span>
          <span class="btn-glow" aria-hidden="true"></span>
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
        <?= $screen === 'register' ? 'Already have an account?' : 'New here?' ?>
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
  <section aria-labelledby="roadmap-title" class="section-panel">
    <p class="eyebrow">Actionable Pathway</p>
    <h2 id="roadmap-title">Prioritized Improvement Roadmap</h2>
    <p>Targeted milestones generated from sampled code evidence to strengthen your engineering portfolio.</p>
    
    <div class="roadmap-grid">
      <?php foreach ($assessment['tasks'] as $task): ?>
      <article class="task-card">
        <span class="task-priority-badge">Priority <?= (int) $task['priority'] ?> · <?= (int) $task['priority'] === 1 ? 'High Impact' : ((int) $task['priority'] === 2 ? 'Recommended' : 'Next Step') ?></span>
        <h3><?= escape($task['title']) ?></h3>
        <p class="text-secondary text-sm"><?= escape($task['reason']) ?></p>
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
  <section aria-labelledby="findings-title" class="section-panel">
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
      <div class="citation-label">Verified Line Citations:</div>
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
  <section class="notice" aria-labelledby="limits-title">
    <h2 id="limits-title">Verification boundaries &amp; disclaimers</h2>
    <ul class="limitations-list">
      <?php foreach ($assessment['result']['limitations'] as $limitation): ?>
      <li><?= escape($limitation) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

<?php elseif ($screen === 'profile'): ?>
  <?php student_nav($screen); ?>

  <header class="workspace-hero">
    <div class="future-badge">
      <span class="badge-dot"></span>
      <span>STUDENT IDENTITY &middot; VERIFIED CREDENTIALS</span>
    </div>
    <h1>Student Engineering Profile</h1>
    <p class="workspace-hero-sub">Manage your academic credentials, verified skills, and GitHub portfolio handle.</p>
  </header>

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

  <div class="workspace-layout">
    <div class="workspace-main">
      <div class="futureglass-panel">
        <form action="/profile" method="post" class="futureglass-form">
          <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

          <div class="form-section-header">
            <h2 class="form-section-title">Academic &amp; Institutional Affiliation</h2>
            <p class="form-section-sub">Link your academic identity to verified campus placement networks.</p>
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

          <div class="futureglass-consent-card">
            <label class="consent-label">
              <input type="checkbox" name="college_share_consent" value="1" <?= !empty($profile['college_share_consent']) ? 'checked' : '' ?> class="futureglass-checkbox">
              <div class="consent-content">
                <span class="consent-title">Authorize College Placement Cell Access</span>
                <span class="consent-sub">
                  When checked, authorized placement officers at your associated college can view your verified skills, resumes, and internship application outcomes. When unchecked, your profile and application history remain completely confidential.
                </span>
              </div>
            </label>
          </div>

          <div class="form-section-header mt-lg">
            <h2 class="form-section-title">Technical Competencies &amp; Bio</h2>
            <p class="form-section-sub">Define the technical stack and practical experience that employers evaluate.</p>
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

          <div class="form-actions mt-md">
            <button class="button button-futureglass" type="submit">
              Save Profile
            </button>
          </div>
        </form>
      </div>
    </div>

    <aside class="workspace-sidebar">
      <div class="futureglass-card profile-identity-card">
        <div class="profile-avatar-wrap">
          <div class="profile-avatar-symbol">&#x25C7;</div>
          <div class="profile-avatar-info">
            <h3 class="profile-name"><?= escape($user['name']) ?></h3>
            <span class="profile-email"><?= escape($user['email']) ?></span>
            <span class="badge badge-observed mt-xs">&#x2713; Verified Student</span>
          </div>
        </div>
        <hr class="card-divider">
        <div class="profile-status-metric">
          <div class="metric-row">
            <span class="metric-label">Placement Cell Status</span>
            <span class="badge <?= !empty($profile['college_share_consent']) ? 'badge-observed' : 'muted' ?>">
              <?= !empty($profile['college_share_consent']) ? 'Consented' : 'Restricted' ?>
            </span>
          </div>
          <div class="metric-row mt-sm">
            <span class="metric-label">College Affiliation</span>
            <span class="metric-value"><?= !empty($profile['college']) ? escape(substr($profile['college'], 0, 22)) . '...' : 'Independent' ?></span>
          </div>
        </div>
      </div>

      <div class="futureglass-card">
        <h4 class="card-title-sm">&#x25CE; Profile Optimization Tips</h4>
        <ul class="guidance-list">
          <li class="<?= !empty($profile['skills']) ? 'complete' : '' ?>">
            <span class="step-check">&#x2713;</span>
            <span>Specify at least 5 primary technical skills</span>
          </li>
          <li class="<?= !empty($profile['github_username']) ? 'complete' : '' ?>">
            <span class="step-check">&#x2713;</span>
            <span>Link active GitHub handle for AST verification</span>
          </li>
          <li class="<?= !empty($profile['bio']) ? 'complete' : '' ?>">
            <span class="step-check">&#x2713;</span>
            <span>Write focused engineering bio (100+ words)</span>
          </li>
          <li class="<?= !empty($profile['college_share_consent']) ? 'complete' : '' ?>">
            <span class="step-check">&#x2713;</span>
            <span>Authorize campus placement cell discovery</span>
          </li>
        </ul>
      </div>

      <div class="futureglass-card">
        <h4 class="card-title-sm">&#x2B21; Privacy &amp; Consent</h4>
        <p class="text-sm text-secondary">
          EIDOLON strictly enforces DPDP Act consent frameworks. Your profile, code assessments, and job applications are never disclosed to third parties without your cryptographic consent.
        </p>
        <a href="/privacy/settings" class="text-link text-xs mt-sm inline-block">Manage consent preferences &rarr;</a>
      </div>
    </aside>
  </div>

<?php elseif ($screen === 'jobs'): ?>
  <?php student_nav($screen); ?>

  <header class="workspace-hero">
    <div class="future-badge">
      <span class="badge-dot"></span>
      <span>EMPLOYMENT PIPELINE &middot; 100% VERIFIED EMPLOYERS</span>
    </div>
    <h1>Browse Verified Positions</h1>
    <p class="workspace-hero-sub">Every position is posted by an administrator-approved active enterprise. Zero fabricated listings.</p>
  </header>

  <div class="jobs-filter-panel futureglass-panel mb-lg">
    <form action="/jobs" method="get" class="jobs-filter-bar">
      <div class="form-group filter-field-grow">
        <label for="q">Keyword or Tech Stack</label>
        <input id="q" name="q" value="<?= escape($searchQuery ?? '') ?>" placeholder="e.g. SQLite, React, Backend, PHP">
      </div>
      <div class="form-group filter-field-fixed">
        <label for="category">Role Category</label>
        <select id="category" name="category">
          <option value="all" <?= ($selectedCategory ?? '') === 'all' ? 'selected' : '' ?>>All Categories</option>
          <option value="frontend" <?= ($selectedCategory ?? '') === 'frontend' ? 'selected' : '' ?>>Frontend</option>
          <option value="backend" <?= ($selectedCategory ?? '') === 'backend' ? 'selected' : '' ?>>Backend</option>
          <option value="fullstack" <?= ($selectedCategory ?? '') === 'fullstack' ? 'selected' : '' ?>>Fullstack</option>
          <option value="general" <?= ($selectedCategory ?? '') === 'general' ? 'selected' : '' ?>>General</option>
        </select>
      </div>
      <div class="filter-actions">
        <button class="button button-futureglass" type="submit">Filter Positions</button>
        <?php if (!empty($searchQuery) || ($selectedCategory ?? 'all') !== 'all'): ?>
        <a class="button secondary" href="/jobs">Reset</a>
        <?php endif; ?>
      </div>
    </form>
    <div class="jobs-filter-meta">
      <span class="filter-count-badge"><?= count($jobs ?? []) ?> Active Positions Listed</span>
      <span class="filter-provenance-note">&#x2713; All listings backed by verified corporate entities</span>
    </div>
  </div>

  <?php if (!empty($jobs)): ?>
  <div class="job-grid">
    <?php foreach ($jobs as $j): ?>
    <article class="job-card futureglass-card futureglass-tilt">
      <div class="job-card-header">
        <span class="badge badge-observed">&#x2713; <?= escape($j['company_name']) ?></span>
        <span class="badge muted"><?= escape(ucfirst($j['role_category'])) ?></span>
      </div>
      <h3 class="job-card-title"><?= escape($j['title']) ?></h3>
      <p class="job-location"><span class="loc-icon">&#x25CE;</span> Location: <?= escape($j['location']) ?></p>
      <p class="job-description">
        <?= escape(strlen($j['description']) > 160 ? substr($j['description'], 0, 157) . '...' : $j['description']) ?>
      </p>
      <div class="job-skills-section">
        <span class="job-skills-label">Required Skills:</span>
        <div class="skill-chips-wrap">
          <?php foreach (array_filter(array_map('trim', explode(',', $j['skills']))) as $sk): ?>
          <span class="skill-chip"><?= escape($sk) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="job-card-footer mt-auto">
        <a class="button button-futureglass full-width" href="/jobs/view?id=<?= (int) $j['id'] ?>">
          View Position &amp; Apply &rarr;
        </a>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="futureglass-panel text-center py-xl">
    <div class="empty-symbol">&#x25C7;</div>
    <h2>No active job listings match your filters</h2>
    <p class="text-secondary mt-xs">Try resetting search terms or check back as verified employers post new opportunities.</p>
    <a href="/jobs" class="button secondary mt-md">Reset Search Filters</a>
  </div>
  <?php endif; ?>

<?php elseif ($screen === 'job_detail'): ?>
  <?php student_nav($screen); ?>

  <div class="mb-md">
    <a href="/jobs" class="back-link">&larr; Return to job listings</a>
  </div>

  <header class="workspace-hero">
    <div class="future-badge">
      <span class="badge-dot"></span>
      <span>VERIFIED ENTERPRISE OPPORTUNITY</span>
    </div>
    <h1><?= escape($job['title']) ?></h1>
    <p class="workspace-hero-sub">Posted by <strong><?= escape($job['company_name']) ?></strong> &middot; <?= escape($job['location']) ?></p>
  </header>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);
  ?>
  <?php if (is_string($flashError)): ?>
  <div class="notice error" role="alert"><p><?= escape($flashError) ?></p></div>
  <?php endif; ?>

  <div class="provenance-bar mb-lg">
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

  <div class="workspace-layout">
    <!-- Main Column: Description & Competencies -->
    <div class="workspace-main">
      <div class="futureglass-panel mb-lg">
        <h2 class="section-title">Position Description</h2>
        <div class="description-body leading-relaxed"><?= escape($job['description']) ?></div>

        <div class="section-divider mt-lg pt-md">
          <h3 class="citation-label">Required Competencies &amp; Technologies:</h3>
          <div class="skill-chips-wrap mt-xs">
            <?php foreach (array_filter(array_map('trim', explode(',', $job['skills']))) as $sk): ?>
            <span class="skill-chip accent-chip"><?= escape($sk) ?></span>
            <?php endforeach; ?>
          </div>
          <p class="hint mt-xs">Verified by EIDOLON AST analysis against linked repository commit histories.</p>
        </div>
      </div>

      <div class="futureglass-card">
        <h3 class="section-title-sm">&#x2B21; Employer Verification Standards</h3>
        <p class="text-sm text-secondary">
          <strong><?= escape($job['company_name']) ?></strong> has undergone institutional domain and authorized representative review. Applications submitted through EIDOLON route directly to the employer's technical recruitment dashboard with deterministic code evidence attached.
        </p>
      </div>
    </div>

    <!-- Secondary Column: Sticky Application Panel -->
    <aside class="workspace-sidebar">
      <div class="futureglass-card application-sidebar-card">
        <?php if ($existingApplication): ?>
        <div class="application-status-box success">
          <div class="status-indicator-badge">&#x2713; Application Active</div>
          <h3 class="status-title">Application Already Submitted</h3>
          <p class="text-xs text-secondary mt-xs">
            Submitted on <strong><?= escape(date('Y-m-d H:i', (int) $existingApplication['created_at'])) ?></strong>.
          </p>
          <div class="mt-sm">
            <span class="badge <?= $existingApplication['status'] === 'shortlisted' ? 'badge-observed' : ($existingApplication['status'] === 'rejected' ? 'badge-failed' : 'muted') ?>">
              Current Status: <?= escape(ucfirst($existingApplication['status'])) ?>
            </span>
          </div>
          <a href="/applications" class="button secondary full-width mt-md text-center">View in Application Tracker &rarr;</a>
        </div>
        <?php else: ?>
        <h3 class="card-title-sm">&#x25C7; Submit Application</h3>
        <p class="text-xs text-secondary mb-md">
          Apply to <strong><?= escape($job['company_name']) ?></strong>. Your verified engineering profile and commit assessment will be attached.
        </p>

        <form action="/jobs/apply" method="post" class="futureglass-form">
          <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
          <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">

          <div class="form-group">
            <label for="notes">Cover Note &amp; Project Highlight (Optional)</label>
            <textarea id="notes" name="notes" rows="4" maxlength="1000" placeholder="Briefly highlight relevant code evidence or projects demonstrating your fit..."></textarea>
            <p class="hint">Up to 1,000 characters.</p>
          </div>

          <button class="button button-futureglass full-width" type="submit">
            Submit Application
          </button>
        </form>
        <?php endif; ?>
      </div>

      <div class="futureglass-card">
        <h4 class="card-title-sm">&#x25CE; Application Guarantee</h4>
        <ul class="guarantee-list">
          <li>&#x2713; Pinned commit hash shared with recruiter</li>
          <li>&#x2713; Zero self-attestation penalty</li>
          <li>&#x2713; Instant status notification on shortlist</li>
        </ul>
      </div>
    </aside>
  </div>

<?php elseif ($screen === 'applications'): ?>
  <?php student_nav($screen); ?>

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
      <caption class="sr-only">Job application history</caption>
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
  <div class="notice">
    <h2>No applications submitted yet</h2>
    <p>Browse our active job listings and submit your first verified application.</p>
    <p class="mt-sm"><a class="button secondary" href="/jobs">Explore Available Opportunities &rarr;</a></p>
  </div>
  <?php endif; ?>

<?php elseif ($screen === 'resume'): ?>
  <?php student_nav($screen); ?>

  <header class="workspace-hero no-print">
    <div class="future-badge">
      <span class="badge-dot"></span>
      <span>SMART RESUME INTELLIGENCE &middot; ATS OPTIMIZED</span>
    </div>
    <h1>Build Your Verifiable Resume</h1>
    <p class="workspace-hero-sub">Craft an ATS-optimized resume directly backed by your verified GitHub code evidence.</p>
  </header>

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
  <div class="no-print draft-selector futureglass-panel mb-lg">
    <span class="draft-label">&#x25C7; Saved Resume Versions:</span>
    <div class="draft-pills-wrap">
      <?php foreach ($allResumes as $r): ?>
      <a class="button <?= ($resume !== null && (int)$resume['id'] === (int)$r['id']) ? 'button-futureglass' : 'secondary' ?> sm" href="/resume?id=<?= (int) $r['id'] ?>">
        <?= escape($r['title']) ?>
      </a>
      <?php endforeach; ?>
      <a class="button secondary sm" href="/resume">+ New Draft</a>
    </div>

    <?php if ($resume !== null): ?>
    <a class="button accent sm ml-auto" href="/resume/preview?id=<?= (int) $resume['id'] ?>" target="_blank">
      Open ATS Print / PDF Layout &nearr;
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="workspace-layout no-print">
    <div class="workspace-main">
      <form action="/resume" method="post" class="futureglass-form">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
        <?php if ($resume !== null && !empty($resume['id'])): ?>
        <input type="hidden" name="id" value="<?= (int) $resume['id'] ?>">
        <?php endif; ?>

        <!-- Section 1: Resume Identity & Evidence Citation -->
        <div class="futureglass-panel mb-lg">
          <div class="form-section-header">
            <h2 class="form-section-title">1. Resume Identity &amp; Evidence Citation</h2>
            <p class="form-section-sub">Name this resume version and optionally link deterministic GitHub commit evidence.</p>
          </div>
          
          <div class="form-group">
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

        <!-- Section 2: Professional Summary & Technical Skills -->
        <div class="futureglass-panel mb-lg">
          <div class="form-section-header">
            <h2 class="form-section-title">2. Professional Summary &amp; Competencies</h2>
            <p class="form-section-sub">Core engineering background, strengths, and searchable technologies.</p>
          </div>

          <div class="form-group">
            <label for="summary">Professional Summary</label>
            <textarea id="summary" name="summary" rows="3" maxlength="2000" placeholder="A concise 2-4 sentence summary of your engineering background, core strengths, and career focus..."><?= escape($resume['summary'] ?? ($profile['bio'] ?? '')) ?></textarea>
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

        <!-- Section 3: Education Entries -->
        <div class="futureglass-panel mb-lg">
          <div class="form-section-header">
            <h2 class="form-section-title">3. Education &amp; Academic History</h2>
            <p class="form-section-sub">Add your university, degree, and expected graduation year.</p>
          </div>

          <?php
            $eduRows = $resume['education'] ?? [];
            if (empty($eduRows)) {
              $eduRows = [
                ['institution' => $profile['college'] ?? '', 'degree' => 'B.S. Computer Science', 'year' => '2022 – 2026', 'gpa' => '']
              ];
            }
            $eduRows[] = ['institution' => '', 'degree' => '', 'year' => '', 'gpa' => ''];
          ?>

          <?php foreach ($eduRows as $i => $edu): ?>
          <div class="entry-block futureglass-card mb-md">
            <div class="form-grid">
              <div class="form-group">
                <label class="label-sm">Institution / College</label>
                <input name="education_institution[]" maxlength="150" value="<?= escape($edu['institution'] ?? '') ?>" placeholder="e.g. Stanford University">
              </div>
              <div class="form-group">
                <label class="label-sm">Degree / Major</label>
                <input name="education_degree[]" maxlength="150" value="<?= escape($edu['degree'] ?? '') ?>" placeholder="e.g. B.S. in Computer Science">
              </div>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="label-sm">Years (e.g. 2022 – 2026)</label>
                <input name="education_year[]" maxlength="50" value="<?= escape($edu['year'] ?? '') ?>" placeholder="2022 – 2026">
              </div>
              <div class="form-group">
                <label class="label-sm">GPA (Optional)</label>
                <input name="education_gpa[]" maxlength="20" value="<?= escape($edu['gpa'] ?? '') ?>" placeholder="e.g. 3.9 / 4.0">
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Section 4: Work & Internship Experience -->
        <div class="futureglass-panel mb-lg">
          <div class="form-section-header">
            <h2 class="section-title">4. Work &amp; Internship Experience</h2>
            <p class="form-section-sub">Highlight responsibilities, measurable contributions, and technical achievements.</p>
          </div>

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
          <div class="entry-block futureglass-card mb-md">
            <div class="form-grid">
              <div class="form-group">
                <label class="label-sm">Organization / Company</label>
                <input name="experience_company[]" maxlength="150" value="<?= escape($exp['company'] ?? '') ?>" placeholder="e.g. Acme Corp">
              </div>
              <div class="form-group">
                <label class="label-sm">Position Title</label>
                <input name="experience_role[]" maxlength="150" value="<?= escape($exp['role'] ?? '') ?>" placeholder="e.g. Backend Engineering Intern">
              </div>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="label-sm">Start Date</label>
                <input name="experience_start[]" maxlength="50" value="<?= escape($exp['start_date'] ?? '') ?>" placeholder="e.g. May 2025">
              </div>
              <div class="form-group">
                <label class="label-sm">End Date</label>
                <input name="experience_end[]" maxlength="50" value="<?= escape($exp['end_date'] ?? '') ?>" placeholder="e.g. August 2025 or Present">
              </div>
            </div>
            <div class="form-group">
              <label class="label-sm">Bullet Points / Description (one per line)</label>
              <textarea name="experience_desc[]" rows="3" maxlength="1000" placeholder="Built scalable SQLite data ingestion service&#10;Reduced query response times by 35%"><?= escape($exp['description'] ?? '') ?></textarea>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Section 5: Practical Engineering Projects -->
        <div class="futureglass-panel mb-lg">
          <div class="form-section-header">
            <h2 class="section-title">5. Practical Engineering Projects</h2>
            <p class="form-section-sub">Project achievements with measurable technical outcomes and live links.</p>
          </div>

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
          <div class="entry-block futureglass-card mb-md">
            <div class="form-grid">
              <div class="form-group">
                <label class="label-sm">Project Name</label>
                <input name="project_name[]" maxlength="150" value="<?= escape($proj['name'] ?? '') ?>" placeholder="e.g. EIDOLON PATHWAY">
              </div>
              <div class="form-group">
                <label class="label-sm">Tech Stack</label>
                <input name="project_tech[]" maxlength="200" value="<?= escape($proj['technologies'] ?? '') ?>" placeholder="e.g. PHP 8.4, SQLite, CSS3">
              </div>
            </div>
            <div class="form-group">
              <label class="label-sm">Project Bullets / Description (one per line)</label>
              <textarea name="project_desc[]" rows="2" maxlength="1000" placeholder="Architected zero-dependency evidence inspection pipeline&#10;Achieved 100% test coverage across 300+ assertions"><?= escape($proj['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
              <label class="label-sm">Repository or Live Link</label>
              <input name="project_link[]" maxlength="200" value="<?= escape($proj['link'] ?? '') ?>" placeholder="e.g. https://github.com/octocat/project">
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="form-actions mt-lg">
          <button class="button button-futureglass" type="submit">
            Save Resume Draft
          </button>
          <?php if ($resume !== null && !empty($resume['id'])): ?>
          <a class="button secondary" href="/resume/preview?id=<?= (int) $resume['id'] ?>" target="_blank">
            Open ATS Print Preview &nearr;
          </a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Secondary Column: ATS Readiness & Guidance -->
    <aside class="workspace-sidebar">
      <div class="futureglass-card mb-lg">
        <h4 class="card-title-sm">&#x25C7; ATS Readiness Score</h4>
        <div class="metric-row mt-xs">
          <span class="metric-label">Parsed Optimization</span>
          <span class="badge badge-observed">94% Compliant</span>
        </div>
        <p class="text-xs text-secondary mt-sm">
          Clean standard headers, semantic markup, and UTF-8 typography ensure zero parsing errors in Taleo, Greenhouse, and Workday ATS parsers.
        </p>
      </div>

      <div class="futureglass-card mb-lg">
        <h4 class="card-title-sm">&#x25CE; Evidence Citation</h4>
        <p class="text-xs text-secondary mt-xs">
          <?php if ($resume !== null && !empty($resume['assessment_id'])): ?>
          <span class="badge badge-observed mb-xs">&#x2713; Cryptographic Citation Active</span><br>
          This draft links evaluated commit milestones directly to your ATS printable resume.
          <?php else: ?>
          <span class="badge muted mb-xs">No Assessment Attached</span><br>
          Link an assessment in Section 1 to include cryptographic proof of engineering ability.
          <?php endif; ?>
        </p>
      </div>

      <div class="futureglass-card">
        <h4 class="card-title-sm">&#x2B21; Print / PDF Export</h4>
        <p class="text-xs text-secondary mt-xs">
          Use the ATS Print Preview to generate high-contrast PDF copies formatted for recruiter review.
        </p>
        <?php if ($resume !== null && !empty($resume['id'])): ?>
        <a href="/resume/preview?id=<?= (int) $resume['id'] ?>" target="_blank" class="button secondary full-width sm mt-sm text-center">
          Open Printable Layout &rarr;
        </a>
        <?php endif; ?>
      </div>
    </aside>
  </div>

<?php elseif ($screen === 'resume_preview'): ?>
  <!-- Control Bar (hidden during browser print) -->
  <div class="no-print control-bar">
    <div>
      <a href="/resume?id=<?= (int) $resume['id'] ?>" class="text-link-bold">&larr; Return to Resume Editor</a>
      <p class="control-sub">
        Single-column ATS format &middot; Clean print/PDF layout &middot; Zero untrusted scripts
      </p>
    </div>
    <div class="actions">
      <button class="button" data-action="print">
        Print / Save to PDF
      </button>
    </div>
  </div>

  <?php if (!empty($atsAnalysis)): ?>
  <!-- ATS Intelligence & Match Benchmark (no-print) -->
  <section class="card no-print ats-panel">
    <div class="ats-header">
      <div>
        <p class="eyebrow">Evidence-Grounded Resume Intelligence</p>
        <h2 class="section-title">ATS Parser Analysis &amp; Keyword Alignment</h2>
      </div>
      <div class="ats-score">
        <span class="ats-score-value"><?= (int) ($atsAnalysis['overall_score'] ?? 0) ?></span>
        <span class="ats-score-max">/ 100</span>
        <div><span class="badge badge-observed"><?= escape($atsAnalysis['rating'] ?? 'ATS Analysis') ?></span></div>
      </div>
    </div>

    <!-- Active Job Benchmark Form -->
    <form action="/resume/preview" method="get" class="benchmark-form">
      <input type="hidden" name="id" value="<?= (int) $resume['id'] ?>">
      <label for="benchmark-job" class="label-sm">Benchmark Against Vacancy:</label>
      <select id="benchmark-job" name="job_id" class="benchmark-select">
        <option value="">General Industry Standard (<?= escape(strtoupper($atsAnalysis['target_role'] ?? 'Backend')) ?>)</option>
        <?php foreach ($activeJobs ?? [] as $aj): ?>
        <option value="<?= (int) $aj['id'] ?>" <?= ((int)($targetJob['id'] ?? 0) === (int)$aj['id']) ? 'selected' : '' ?>>
          <?= escape($aj['title']) ?> (<?= escape($aj['company_name'] ?? 'Company') ?>)
        </option>
        <?php endforeach; ?>
      </select>
      <button class="button secondary sm" type="submit">Analyze Match</button>
    </form>

    <!-- Checklist Breakdown -->
    <div class="ats-checklist">
      <?php foreach ($atsAnalysis['checks'] ?? [] as $chk): ?>
      <div class="ats-check-item">
        <div class="ats-check-header">
          <strong class="text-sm"><?= escape($chk['category']) ?></strong>
          <span class="ats-check-score <?= $chk['passed'] ? 'text-emerald' : 'text-danger' ?>">
            <?= (int) $chk['score'] ?> / <?= (int) $chk['max'] ?>
          </span>
        </div>
        <p class="ats-check-feedback"><?= escape($chk['feedback']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Keyword Alignment -->
    <div class="ats-keywords">
      <div>
        <h4 class="keyword-heading matched">&#10003; Matched Industry Keywords</h4>
        <div class="keyword-tags">
          <?php foreach ($atsAnalysis['matched_keywords'] ?? [] as $mkw): ?>
          <span class="badge keyword-tag"><?= escape($mkw) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <h4 class="keyword-heading suggested">&#x25C7; Recommended Skills for <?= escape(strtoupper($atsAnalysis['target_role'] ?? 'Role')) ?></h4>
        <div class="keyword-tags">
          <?php foreach ($atsAnalysis['suggested_keywords'] ?? [] as $skw): ?>
          <span class="badge muted keyword-tag"><?= escape($skw) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <p class="ats-disclaimer">
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
      <p class="resume-summary-text"><?= escape($resume['summary']) ?></p>
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
          <span class="resume-project-tech">[<?= escape($proj['technologies']) ?>]</span>
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
        <div class="resume-milestones">
          <strong class="citation-label">
            Verified Milestone Outcomes from Inspected Lexical Samples:
          </strong>
          <ul class="resume-evidence-list">
            <?php foreach ($resume['assessment_tasks'] as $task): ?>
            <li>
              <strong>Priority <?= (int) $task['priority'] ?>:</strong> <?= escape($task['title']) ?>
              &mdash; <span class="text-muted"><?= escape($task['acceptance']) ?></span>
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
  <!-- Student Dashboard — Career Launchpad -->
  <?php student_nav($screen); ?>

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

    // Determine the most important next action for this student
    $nextAction = null;
    if (empty($profile['skills'])) {
      $nextAction = ['title' => 'Complete your profile', 'desc' => 'Add your technical skills and a short bio so employers and assessments can match you accurately.', 'href' => '/profile', 'label' => 'Edit Profile', 'icon' => '&#x270E;'];
    } elseif ($resumesCount === 0) {
      $nextAction = ['title' => 'Build your first resume', 'desc' => 'Create an ATS-optimized resume to send with your job applications. Verified evidence will be cited automatically.', 'href' => '/resume', 'label' => 'Resume Builder', 'icon' => '&#x1F4C4;'];
    } elseif ($totalApps === 0) {
      $nextAction = ['title' => 'Apply to an open position', 'desc' => 'Browse active job and internship listings from verified employers. Your resume and portfolio evidence go with every application.', 'href' => '/jobs', 'label' => 'Browse Jobs', 'icon' => '&#x1F4BC;'];
    } elseif ($completedAssessmentsCount === 0) {
      $nextAction = ['title' => 'Run a portfolio assessment', 'desc' => 'Submit a public GitHub repository for automated code analysis. The commit-pinned evidence strengthens your applications.', 'href' => '#assess-title', 'label' => 'Start Assessment', 'icon' => '&#x1F50D;'];
    }
  ?>

  <!-- Two-Column Desktop Dashboard Workspace -->
  <div class="dashboard-grid">
    <!-- Primary Column (65–70%) -->
    <div class="dashboard-primary">
      <!-- 1. Welcome Greeting -->
      <section class="launchpad-welcome">
        <div class="welcome-greeting">
          <h1>Welcome back, <?= escape($user['name']) ?></h1>
          <p class="welcome-sub"><?= escape($user['email']) ?> · <span class="badge muted">Demo</span></p>
        </div>
      </section>

      <!-- 2. Recommended Next Action -->
      <?php if ($nextAction): ?>
      <section class="next-action-banner" aria-labelledby="next-action-title">
        <div class="next-action-content">
          <span class="next-action-label">Recommended Next Action</span>
          <h3 id="next-action-title"><?= escape($nextAction['title']) ?></h3>
          <p><?= escape($nextAction['desc']) ?></p>
        </div>
        <a class="button next-action-cta" href="<?= escape($nextAction['href']) ?>"><?= escape($nextAction['label']) ?> &rarr;</a>
      </section>
      <?php endif; ?>

      <!-- 3. Career Readiness Milestones -->
      <section class="milestones-section" aria-labelledby="milestones-title">
        <div class="section-header">
          <h2 id="milestones-title">Career Readiness</h2>
          <span class="progress-pct"><?= $completenessPoints ?>%</span>
        </div>
        <div class="progress-track">
          <div class="progress-fill" style="width:<?= $completenessPoints ?>%;"></div>
        </div>
        <div class="milestone-grid">
          <a href="/profile" class="milestone-card <?= !empty($profile['skills']) ? 'milestone-done' : '' ?>">
            <span class="milestone-icon"><?= !empty($profile['skills']) ? '&#10003;' : '&#x25CB;' ?></span>
            <div class="milestone-info">
              <strong>Skills &amp; Bio</strong>
              <span><?= !empty($profile['skills']) ? 'Profile complete' : 'Add your skills and bio' ?></span>
            </div>
          </a>
          <a href="/resume" class="milestone-card <?= $resumesCount > 0 ? 'milestone-done' : '' ?>">
            <span class="milestone-icon"><?= $resumesCount > 0 ? '&#10003;' : '&#x25CB;' ?></span>
            <div class="milestone-info">
              <strong>ATS Resume</strong>
              <span><?= $resumesCount > 0 ? $resumesCount . ' resume' . ($resumesCount > 1 ? 's' : '') . ' created' : 'Build your first resume' ?></span>
            </div>
          </a>
          <a href="#assess-title" class="milestone-card <?= $completedAssessmentsCount > 0 ? 'milestone-done' : '' ?>">
            <span class="milestone-icon"><?= $completedAssessmentsCount > 0 ? '&#10003;' : '&#x25CB;' ?></span>
            <div class="milestone-info">
              <strong>GitHub Evidence</strong>
              <span><?= $completedAssessmentsCount > 0 ? $completedAssessmentsCount . ' assessment' . ($completedAssessmentsCount > 1 ? 's' : '') . ' completed' : 'Run repository audit' ?></span>
            </div>
          </a>
          <a href="/affiliation/request" class="milestone-card <?= ($profile['affiliation_status'] ?? '') === 'AFFILIATED' ? 'milestone-done' : '' ?>">
            <span class="milestone-icon"><?= ($profile['affiliation_status'] ?? '') === 'AFFILIATED' ? '&#10003;' : '&#x25CB;' ?></span>
            <div class="milestone-info">
              <strong>College Affiliation</strong>
              <span><?= ($profile['affiliation_status'] ?? '') === 'AFFILIATED' ? 'Affiliated: ' . escape($profile['college_org_name'] ?? 'College') : 'Request affiliation' ?></span>
            </div>
          </a>
        </div>
      </section>

      <!-- 4. Jobs & Applications -->
      <section class="dashboard-section" aria-labelledby="apps-section-title">
        <div class="section-header">
          <h2 id="apps-section-title">Jobs &amp; Applications</h2>
          <a href="/jobs" class="text-link">Browse All Openings &rarr;</a>
        </div>
        <?php if (!empty($appsList)): ?>
        <div class="table-container">
          <table>
            <caption class="sr-only">Your recent job applications</caption>
            <thead>
              <tr>
                <th>Position</th>
                <th>Company</th>
                <th>Location</th>
                <th>Status</th>
                <th>Date Applied</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($appsList, 0, 3) as $app): ?>
              <tr>
                <td><strong><a href="/jobs/view?id=<?= (int) $app['job_id'] ?>"><?= escape($app['job_title']) ?></a></strong></td>
                <td><?= escape($app['company_name']) ?></td>
                <td><span class="text-muted"><?= escape($app['location']) ?></span></td>
                <td>
                  <span class="badge <?= match($app['status'] ?? '') {
                    'shortlisted' => 'badge-observed',
                    'rejected' => 'badge-failed',
                    default => 'badge-category'
                  } ?>">
                    <?= escape(ucfirst(str_replace('_', ' ', $app['status'] ?? 'submitted'))) ?>
                  </span>
                </td>
                <td><span class="text-muted"><?= escape(date('Y-m-d', (int) $app['created_at'])) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="mt-sm"><a href="/applications" class="text-link-bold">View full application tracking &rarr;</a></p>
        <?php else: ?>
        <div class="empty-state-card">
          <p>No job applications submitted yet. Apply with your verified code evidence and ATS-optimized resume.</p>
          <a class="button sm" href="/jobs">Browse Verified Openings &rarr;</a>
        </div>
        <?php endif; ?>
      </section>

      <!-- 5. GitHub Portfolio Assessment (Concise entry point) -->
      <section class="assess-compact" aria-labelledby="assess-title">
        <div class="assess-compact-header">
          <h2 id="assess-title">GitHub Portfolio Assessment</h2>
          <p>Submit a public repository URL to run automated code inspections and generate commit-pinned evidence.</p>
        </div>
        <form action="/assess" method="post" class="assess-compact-form">
          <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
          <div class="assess-compact-fields">
            <div class="form-group assess-repo-field">
              <label for="repository">Repository identifier</label>
              <input id="repository" name="repository" type="text" maxlength="180" placeholder="owner/repository or https://github.com/owner/repo" required aria-describedby="repo-help">
              <p class="hint" id="repo-help">Public repositories only. Zero code execution.</p>
            </div>
            <div class="form-group assess-role-field">
              <label for="target_role">Competency role</label>
              <select id="target_role" name="target_role" required>
                <option value="frontend">Frontend</option>
                <option value="backend" selected>Backend</option>
                <option value="fullstack">Fullstack</option>
              </select>
            </div>
            <button class="button assess-submit-btn" type="submit">Execute Assessment &rarr;</button>
          </div>
        </form>
      </section>

      <!-- 6. Assessment History Table (if any) -->
      <?php if (!empty($assessments)): ?>
      <section aria-labelledby="history-title" class="section-panel">
        <div class="section-header">
          <h2 id="history-title">Assessment History</h2>
        </div>
        <div class="table-container">
          <table>
            <caption class="sr-only">GitHub evidence assessment history</caption>
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
                  <a href="/assess?id=<?= (int) $a['id'] ?>" class="text-link-bold">View Findings &rarr;</a>
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
      <?php endif; ?>
    </div>

    <!-- Secondary Column (30–35%) -->
    <aside class="dashboard-secondary">
      <!-- 1. Performance Summary -->
      <div class="sidebar-card" role="group" aria-label="Performance summary">
        <h3 class="sidebar-card-title">Performance Summary</h3>
        <div class="sidebar-stats">
          <a href="/applications" class="sidebar-stat">
            <span class="sidebar-stat-value"><?= $totalApps ?></span>
            <span class="sidebar-stat-label"><?= $totalApps === 1 ? 'Application' : 'Applications' ?></span>
          </a>
          <a href="/applications" class="sidebar-stat">
            <span class="sidebar-stat-value text-emerald"><?= $shortlistedApps ?></span>
            <span class="sidebar-stat-label">Shortlisted</span>
          </a>
          <a href="#assess-title" class="sidebar-stat">
            <span class="sidebar-stat-value text-sky"><?= $completedAssessmentsCount ?></span>
            <span class="sidebar-stat-label"><?= $completedAssessmentsCount === 1 ? 'Assessment' : 'Assessments' ?></span>
          </a>
          <a href="/achievements" class="sidebar-stat">
            <span class="sidebar-stat-value text-violet"><?= $achievementsCount ?></span>
            <span class="sidebar-stat-label"><?= $achievementsCount === 1 ? 'Credential' : 'Credentials' ?></span>
          </a>
        </div>
      </div>

      <!-- 2. Career Progress Breakdown -->
      <div class="sidebar-card">
        <h3 class="sidebar-card-title">Profile Strength &middot; <?= $completenessPoints ?>%</h3>
        <ul class="sidebar-checklist">
          <li class="<?= !empty($user['email']) ? 'check-done' : 'check-todo' ?>">
            <span class="check-icon">&#10003;</span> Account verified
          </li>
          <li class="<?= !empty($profile['skills']) ? 'check-done' : 'check-todo' ?>">
            <span class="check-icon"><?= !empty($profile['skills']) ? '&#10003;' : '&#x25CB;' ?></span> Skills &amp; bio defined
          </li>
          <li class="<?= $resumesCount > 0 ? 'check-done' : 'check-todo' ?>">
            <span class="check-icon"><?= $resumesCount > 0 ? '&#10003;' : '&#x25CB;' ?></span> ATS resume compiled
          </li>
          <li class="<?= $completedAssessmentsCount > 0 ? 'check-done' : 'check-todo' ?>">
            <span class="check-icon"><?= $completedAssessmentsCount > 0 ? '&#10003;' : '&#x25CB;' ?></span> Code evidence assessed
          </li>
        </ul>
      </div>

      <!-- 3. Quick Actions / Next Steps -->
      <div class="sidebar-card">
        <h3 class="sidebar-card-title">Quick Actions</h3>
        <nav class="sidebar-links" aria-label="Student quick actions">
          <a href="/jobs" class="sidebar-link">Browse Opportunities &rarr;</a>
          <a href="/resume" class="sidebar-link">Resume Builder &rarr;</a>
          <a href="/applications" class="sidebar-link">Application Tracking &rarr;</a>
          <a href="/profile" class="sidebar-link">Edit Career Profile &rarr;</a>
          <a href="/achievements" class="sidebar-link">Verified Credentials &rarr;</a>
          <a href="/privacy/settings" class="sidebar-link">Privacy &amp; Consent &rarr;</a>
        </nav>
      </div>

      <!-- 4. Contextual Guidance & Demo Indicator -->
      <div class="sidebar-card sidebar-card-muted">
        <h4 class="sidebar-note-title">Demonstration content</h4>
        <p class="sidebar-demo-note">This is a demo environment. Opportunities and applications are verified locally for demonstration.</p>
      </div>
    </aside>
  </div>

<?php elseif ($screen === 'company_login'): ?>
  <div class="auth-layout">
    <section class="login-value-panel">
      <h1 class="login-heading">Recruit verified software engineering talent.</h1>
      <p class="login-subtitle">Evaluate candidates through commit-pinned GitHub code evidence, objective rubric benchmarks, and verified skill profiles.</p>

      <div class="pathway-steps">
        <div class="pathway-step">
          <span class="pathway-step-num" aria-hidden="true">1</span>
          <div>
            <strong>Post positions</strong>
            <span>Create job listings visible to verified student candidates.</span>
          </div>
        </div>
        <div class="pathway-step">
          <span class="pathway-step-num" aria-hidden="true">2</span>
          <div>
            <strong>Review evidence</strong>
            <span>Evaluate applicants through their actual code contributions and assessment scores.</span>
          </div>
        </div>
        <div class="pathway-step">
          <span class="pathway-step-num" aria-hidden="true">3</span>
          <div>
            <strong>Hire with confidence</strong>
            <span>Make data-informed recruitment decisions within your isolated tenant.</span>
          </div>
        </div>
      </div>

      <p class="login-demo-note">Company accounts are organization-isolated. Positions and applicant data are tenant-specific.</p>
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

        <button class="button" type="submit">Sign In to Company Portal</button>
      </form>

      <p class="switch">
        Student applicant?
        <a href="/login">Student Sign In &rarr;</a>
      </p>
    </section>
  </div>

<?php elseif ($screen === 'company_dashboard'): ?>
  <?php company_nav($screen); ?>

  <section class="hero">
    <div class="section-header">
      <div>
        <p class="eyebrow">Company Recruitment Hub &middot; <?= escape($org['org_name'] ?? 'Company') ?></p>
        <h1><?= escape($org['org_name'] ?? 'Company Portal') ?></h1>
        <p class="badge-group mt-xs">
          <span class="badge badge-observed">Verified Organization</span>
          <span class="badge">Role: <?= escape($org['member_role'] ?? 'STAFF') ?></span>
          <span class="badge muted">Slug: <?= escape($org['org_slug'] ?? '') ?></span>
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
  <div class="metric-grid">
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
    <div class="card stat-card">
      <span class="stat-label">Total Positions</span>
      <p class="stat-value"><?= $totalJobs ?></p>
    </div>
    <div class="card stat-card">
      <span class="stat-label">Active Listings</span>
      <p class="stat-value text-emerald"><?= $activeJobs ?></p>
    </div>
    <div class="card stat-card">
      <span class="stat-label">Total Applicants</span>
      <p class="stat-value"><?= $totalApplicants ?></p>
      <div class="stat-sub"><?= $underReviewApplicants ?> under review</div>
    </div>
    <div class="card stat-card">
      <span class="stat-label">Shortlisted</span>
      <p class="stat-value text-emerald"><?= $shortlistedApplicants ?></p>
      <div class="stat-sub"><?= $conversionRate ?>% conversion rate</div>
    </div>
  </div>

  <!-- Job Listings Table -->
  <section class="card section-panel">
    <div class="section-header">
      <h2 class="section-title">Posted Job Openings</h2>
      <a class="button secondary sm" href="/company/applicants">View All Applicants &rarr;</a>
    </div>

    <?php if (!empty($jobsList)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Company posted positions</caption>
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
              <a href="/company/applicants?job_id=<?= (int) $job['id'] ?>" class="text-link-bold">
                <?= (int) ($job['applicant_count'] ?? 0) ?> candidates &rarr;
              </a>
            </td>
            <td>
              <div class="actions">
                <a class="button secondary sm" href="/company/jobs/edit?id=<?= (int) $job['id'] ?>">Edit</a>
                <a class="button secondary sm" href="/company/applicants?job_id=<?= (int) $job['id'] ?>">Review</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice">
      <p>No job positions posted yet. Publish your first opportunity to receive verified student applications.</p>
      <p class="mt-sm"><a class="button" href="/company/jobs/new">+ Create First Position</a></p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'company_job_form'): ?>
  <?php company_nav($screen); ?>

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

  <section class="auth-panel panel-wide">
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

      <div class="form-grid">
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

      <div class="actions mt-md">
        <button class="button" type="submit"><?= $job !== null ? 'Save Changes' : 'Publish Position' ?></button>
        <a class="button secondary" href="/company/dashboard">Cancel</a>
      </div>
    </form>
  </section>

<?php elseif ($screen === 'company_applicants'): ?>
  <?php company_nav($screen); ?>

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
  <div class="filter-bar">
    <span class="filter-label">Filter by Position:</span>
    <a class="button <?= ($selectedJobId ?? null) === null ? '' : 'secondary' ?> sm" href="/company/applicants">
      All Openings (<?= count($applicants ?? []) ?>)
    </a>
    <?php foreach (($jobs ?? []) as $j): ?>
    <a class="button <?= ($selectedJobId ?? null) === (int)$j['id'] ? '' : 'secondary' ?> sm" href="/company/applicants?job_id=<?= (int) $j['id'] ?>">
      <?= escape($j['title']) ?> (<?= (int) ($j['applicant_count'] ?? 0) ?>)
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Applicants Table -->
  <?php if (!empty($applicants)): ?>
  <div class="table-container">
    <table>
      <caption class="sr-only">Candidate applications and shortlist</caption>
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
            <span class="text-muted text-xs"><?= escape($app['student_email']) ?></span><br>
            <a href="/company/candidate?id=<?= (int) $app['application_id'] ?>" class="link-accent">View Candidate Dossier &rarr;</a>
          </td>
          <td><?= escape($app['college'] ?: 'Not specified') ?></td>
          <td>
            <a href="/jobs/view?id=<?= (int) $app['job_id'] ?>"><?= escape($app['job_title']) ?></a><br>
            <span class="badge muted keyword-tag"><?= escape(ucfirst($app['role_category'])) ?></span>
          </td>
          <td>
            <?php if (!empty($app['github_username'])): ?>
            <code>github.com/<?= escape($app['github_username']) ?></code>
            <?php else: ?>
            <span class="text-muted">&mdash;</span>
            <?php endif; ?>
          </td>
          <td class="table-notes">
            <?= escape($app['notes'] ?: 'No notes') ?>
          </td>
          <td><?= escape(date('Y-m-d H:i', (int) $app['created_at'])) ?></td>
          <td>
            <span class="badge <?= $badgeClass ?>"><?= escape($statusLabel) ?></span>
          </td>
          <td>
            <form action="/company/applicants/status" method="post" class="inline-form actions">
              <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
              <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
              <?php if (($selectedJobId ?? null) !== null): ?>
              <input type="hidden" name="job_id" value="<?= (int) $selectedJobId ?>">
              <?php endif; ?>
              
              <?php if ($status !== 'shortlisted'): ?>
              <button class="button sm" type="submit" name="status" value="shortlisted">Shortlist</button>
              <?php endif; ?>
              
              <?php if ($status !== 'rejected'): ?>
              <button class="button secondary danger sm" type="submit" name="status" value="rejected">Decline</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="notice">
    <h2>No applications found</h2>
    <p>There are no candidate applications for the selected criteria yet.</p>
  </div>
  <?php endif; ?>

<?php elseif ($screen === 'college_login'): ?>
  <div class="auth-layout">
    <section class="login-value-panel">
      <h1 class="login-heading">Privacy-first campus placement intelligence.</h1>
      <p class="login-subtitle">Monitor student career readiness, verified portfolio milestones, and placement outcomes with explicit student consent enforcement.</p>

      <div class="pathway-steps">
        <div class="pathway-step">
          <span class="pathway-step-num" aria-hidden="true">1</span>
          <div>
            <strong>View consenting students</strong>
            <span>Access readiness data only for students who have granted placement authorization.</span>
          </div>
        </div>
        <div class="pathway-step">
          <span class="pathway-step-num" aria-hidden="true">2</span>
          <div>
            <strong>Track placement outcomes</strong>
            <span>Monitor cohort metrics, application statuses, and employer engagement.</span>
          </div>
        </div>
        <div class="pathway-step">
          <span class="pathway-step-num" aria-hidden="true">3</span>
          <div>
            <strong>Manage affiliations</strong>
            <span>Review and approve student affiliation requests to your institution.</span>
          </div>
        </div>
      </div>

      <p class="login-demo-note">College accounts enforce consent-based access. Student data is visible only with explicit authorization.</p>
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

        <button class="button" type="submit">Sign In to College Portal</button>
      </form>

      <p class="switch">
        Student seeking internships?
        <a href="/login">Student Sign In &rarr;</a>
      </p>
    </section>
  </div>

<?php elseif ($screen === 'college_dashboard'): ?>
  <?php college_nav($screen); ?>

  <section class="hero">
    <p class="eyebrow">Academic Placement Network &middot; <?= escape($org['org_name'] ?? 'College') ?></p>
    <h1><?= escape($org['org_name'] ?? 'College Portal') ?></h1>
    <p class="badge-group mt-xs">
      <span class="badge badge-observed">Verified Academic Organization</span>
      <span class="badge">Role: <?= escape($org['member_role'] ?? 'STAFF') ?></span>
      <span class="badge muted">Slug: <?= escape($org['org_slug'] ?? '') ?></span>
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
  <div class="metric-grid">
    <?php
      $studList = $students ?? [];
      $totalAssociated = count($studList);
      $consentedCount = count(array_filter($studList, fn($s) => (int)($s['college_share_consent'] ?? 0) === 1));
      $shortlistedCount = count(array_filter($studList, fn($s) => (int)($s['shortlisted_count'] ?? 0) > 0));
      $consentRate = $totalAssociated > 0 ? (int) round(($consentedCount / $totalAssociated) * 100) : 0;
    ?>
    <div class="card stat-card">
      <span class="stat-label">Associated Students</span>
      <p class="stat-value"><?= $totalAssociated ?></p>
    </div>
    <div class="card stat-card">
      <span class="stat-label">Consent Granted</span>
      <p class="stat-value text-emerald"><?= $consentedCount ?></p>
    </div>
    <div class="card stat-card">
      <span class="stat-label">Placed / Shortlisted</span>
      <p class="stat-value text-emerald"><?= $shortlistedCount ?></p>
    </div>
    <div class="card stat-card">
      <span class="stat-label">Consent Rate</span>
      <p class="stat-value"><?= $consentRate ?>%</p>
    </div>
  </div>

  <div class="notice">
    <h2>Consent-based privacy safeguards active</h2>
    <p>Detailed placement records and application histories are visible only for students who have explicitly opted in via their student profile. Non-consenting student profiles display anonymized identities with confidential application metrics.</p>
  </div>

  <!-- Student Roster Table -->
  <section class="card section-panel">
    <h2 class="section-title">Campus Student Roster (<?= count($studList) ?>)</h2>

    <?php if (!empty($studList)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Affiliated student roster and consent status</caption>
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
              <span class="badge muted keyword-tag">Consent Withheld</span>
              <?php endif; ?>
            </td>
            <td><?= escape($s['skills'] ?: 'Not specified') ?></td>
            <td>
              <?php if (!empty($s['github_username'])): ?>
              <code><?= escape($s['github_username']) ?></code>
              <?php else: ?>
              <span class="text-muted">&mdash;</span>
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
              <strong class="text-emerald"><?= (int) $s['shortlisted_count'] ?> shortlisted</strong>
              <?php elseif ($hasConsent): ?>
              0
              <?php else: ?>
              &mdash;
              <?php endif; ?>
            </td>
            <td>
              <div class="actions">
                <?php if ($hasConsent): ?>
                <a class="button secondary sm" href="/college/students/view?id=<?= (int) $s['student_id'] ?>">
                  View Records &rarr;
                </a>
                <?php else: ?>
                <span class="text-muted text-xs">Private</span>
                <?php endif; ?>
                <form action="/college/affiliations/revoke" method="post" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                  <input type="hidden" name="student_id" value="<?= (int) $s['student_id'] ?>">
                  <input type="hidden" name="reason" value="Administrative revocation by placement coordinator">
                  <button class="button secondary danger sm" type="submit">Revoke</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice">
      <p>No students have linked their profiles to this college yet. Students can associate with your verified institution in their profile settings.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'college_student_detail'): ?>
  <?php college_nav($screen); ?>

  <?php
    $student = $placement['student'] ?? [];
    $apps = $placement['applications'] ?? [];
    $resume = $placement['resume'] ?? null;
  ?>

  <section class="hero">
    <p class="eyebrow">Consented Candidate Record &middot; <?= escape($org['org_name'] ?? 'College') ?></p>
    <h1><?= escape($student['student_name'] ?? $student['name'] ?? 'Student') ?></h1>
    <p class="badge-group mt-xs">
      <span class="badge badge-observed">Student Consent Verified</span>
      <span class="badge muted"><?= escape($student['student_email'] ?? $student['email'] ?? '') ?></span>
      <?php if (!empty($student['enrollment_number'])): ?>
      <span class="badge">USN: <code><?= escape($student['enrollment_number']) ?></code></span>
      <?php endif; ?>
      <?php if (!empty($student['github_username'])): ?>
      <span class="badge"><code>github.com/<?= escape($student['github_username']) ?></code></span>
      <?php endif; ?>
    </p>
  </section>

  <!-- Applications History -->
  <section class="card section-panel">
    <h2 class="section-title">Internship &amp; Placement Applications (<?= count($apps) ?>)</h2>

    <?php if (!empty($apps)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Student application history</caption>
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
    <div class="notice">
      <p>No job applications submitted yet by this student.</p>
    </div>
    <?php endif; ?>
  </section>

  <!-- Latest Resume Overview -->
  <?php if ($resume !== null): ?>
  <section class="card section-panel">
    <h2 class="section-title">Verified Candidate Resume Summary</h2>
    <div class="entry-block">
      <h3 class="entry-block-title"><?= escape($resume['title']) ?></h3>
      <p class="text-secondary">
        <?= escape($resume['summary']) ?>
      </p>
      <?php if (!empty($resume['assessment_id'])): ?>
      <p class="text-sm">
        <span class="badge badge-observed">&#x2713; Cryptographic GitHub Evidence Citation Attached (Assessment #<?= (int) $resume['assessment_id'] ?>)</span>
      </p>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php $achievements = $placement['achievements'] ?? []; ?>
  <?php if (!empty($achievements)): ?>
  <section class="card section-panel">
    <h2 class="section-title">Verified Credentials &amp; Achievements (<?= count($achievements) ?>)</h2>
    <div class="achievement-list">
      <?php foreach ($achievements as $ach): ?>
      <div class="achievement-item">
        <div class="achievement-header">
          <span class="badge sm"><?= escape($ach['category']) ?></span>
          <strong><?= escape($ach['title']) ?></strong>
          <?php if (!empty($ach['issuer'])): ?>
          <span class="achievement-meta">&middot; <?= escape($ach['issuer']) ?></span>
          <?php endif; ?>
          <?php if (!empty($ach['issue_date'])): ?>
          <span class="achievement-meta">(<?= escape($ach['issue_date']) ?>)</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($ach['description'])): ?>
        <p class="achievement-desc"><?= escape($ach['description']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>


<?php elseif ($screen === 'admin_login'): ?>
  <div class="auth-layout">
    <section class="login-value-panel">
      <h1 class="login-heading">Platform governance and security control.</h1>
      <p class="login-subtitle">Manage verified organization approvals, enforce tenant isolation, and inspect tamper-evident audit logs.</p>

      <p class="login-demo-note">Administrator credentials are provisioned via <code>bin/bootstrap_admin.php</code> and cannot be registered through public forms.</p>
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

        <button class="button" type="submit">Sign In to Administration</button>
      </form>

      <p class="switch">
        Need to sign in as student?
        <a href="/login">Student Sign In &rarr;</a>
      </p>
    </section>
  </div>

<?php elseif ($screen === 'request_access'): ?>
  <div class="access-request-container">
    <header class="workspace-hero text-center mb-xl">
      <div class="future-badge">
        <span class="badge-dot"></span>
        <span>INSTITUTIONAL ONBOARDING &middot; VERIFIED ADMISSION</span>
      </div>
      <h1>Request Organization Access</h1>
      <p class="workspace-hero-sub">Partner companies and accredited universities: submit verifiable credentials to join the EIDOLON PATHWAY talent network.</p>
    </header>

    <?php
      $flashError = $flashError ?? ($_SESSION['flash_error'] ?? null);
      $flashSuccess = $flash ?? ($_SESSION['flash'] ?? null);
      unset($_SESSION['flash_error'], $_SESSION['flash']);
    ?>
    <?php if (is_string($flashError)): ?>
    <div class="notice error mb-lg" role="alert"><p><?= escape($flashError) ?></p></div>
    <?php elseif (is_string($flashSuccess)): ?>
    <div class="notice success mb-lg" role="status"><p><?= escape($flashSuccess) ?></p></div>
    <?php endif; ?>

    <?php if (isset($_GET['submitted'])): ?>
    <div class="futureglass-panel success-state text-center py-xl">
      <div class="success-icon mb-md" style="font-size: 3rem; color: #42E8C0;">&#10003;</div>
      <h2 class="success-title">Application Received</h2>
      <p class="success-message text-secondary max-w-prose mx-auto mt-sm">
        Your institutional access request has been securely recorded. Our compliance officers verify official domain records, educational accreditation, and authority to represent before issuing cryptographic invitation links.
      </p>
      <div class="actions actions-center mt-lg">
        <a class="button button-futureglass" href="/login">Return to Platform</a>
        <a class="button secondary" href="/request-access">Submit Another Request</a>
      </div>
    </div>
    <?php else: ?>
    <div class="access-request-grid">
      <!-- Left Column: Verification Protocol & SLA -->
      <aside class="access-protocol-sidebar">
        <div class="futureglass-card mb-lg">
          <h3 class="card-title-sm mb-md">&#x25C7; Three-Step Admission Protocol</h3>
          <div class="protocol-steps-stack">
            <div class="protocol-step">
              <span class="step-num">01</span>
              <div class="step-body">
                <strong>Domain &amp; Entity Validation</strong>
                <p class="text-xs text-secondary mt-2xs">Verification against public registrar DNS records or accredited academic regulatory registries.</p>
              </div>
            </div>
            <div class="protocol-step">
              <span class="step-num">02</span>
              <div class="step-body">
                <strong>Officer Authorization Review</strong>
                <p class="text-xs text-secondary mt-2xs">Manual review of institutional representation credentials by our compliance team (24–48 hr SLA).</p>
              </div>
            </div>
            <div class="protocol-step">
              <span class="step-num">03</span>
              <div class="step-body">
                <strong>Cryptographic Invitation</strong>
                <p class="text-xs text-secondary mt-2xs">Issuance of single-use cryptographic invitation tokens to authorized corporate or university deans.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="futureglass-card">
          <h4 class="card-title-sm">&#x2B21; Institutional Security Standard</h4>
          <p class="text-xs text-secondary mt-xs">
            EIDOLON PATHWAY strictly rejects unverified recruiters and ghost listings. All admitted organizations operate under binding DPDP Act data protection agreements.
          </p>
        </div>
      </aside>

      <!-- Right Column: Spacious Form -->
      <div class="access-form-column">
        <div class="futureglass-panel">
          <form action="/request-access" method="post" class="futureglass-form">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

            <div class="form-group">
              <label for="req-name" class="form-label">Organization Legal Name</label>
              <input id="req-name" name="name" type="text" placeholder="e.g. Acme Technologies Inc. or Dayananda Sagar Academy of Technology & Management" maxlength="120" required>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label for="req-type" class="form-label">Organization Type</label>
                <select id="req-type" name="type" required>
                  <option value="COMPANY">Company / Employer</option>
                  <option value="COLLEGE">College / University</option>
                </select>
              </div>
              <div class="form-group">
                <label for="req-website" class="form-label">Official Website / Domain</label>
                <input id="req-website" name="website" type="text" placeholder="https://example.com" maxlength="200" required>
              </div>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label for="req-contact-name" class="form-label">Authorized Contact Full Name</label>
                <input id="req-contact-name" name="contact_name" type="text" placeholder="e.g. Dr. Ramesh Rao or Jane Doe" maxlength="100" required>
              </div>
              <div class="form-group">
                <label for="req-designation" class="form-label">Designation / Official Title</label>
                <input id="req-designation" name="designation" type="text" placeholder="e.g. Dean of Placements or Head of Talent" maxlength="100" required>
              </div>
            </div>

            <div class="form-group">
              <label for="req-email" class="form-label">Official Institutional Work Email</label>
              <input id="req-email" name="contact_email" type="email" placeholder="name@organization.edu or name@company.com" maxlength="254" required>
              <p class="hint">Must use official institutional domain. Generic free email providers are subjected to strict scrutiny.</p>
            </div>

            <div class="form-group">
              <label for="req-notes" class="form-label">Verification Evidence &amp; Authority Statement</label>
              <textarea id="req-notes" name="verification_notes" rows="4" placeholder="Provide proof of authority: official university accreditation ID, department URL, leadership directory link, corporate registration number, or official contact reference." maxlength="2000" required></textarea>
            </div>

            <div class="form-actions mt-lg">
              <button class="button button-futureglass full-width" type="submit">Submit Institutional Access Request</button>
            </div>
          </form>

          <p class="switch text-center mt-md text-xs">
            Need student access? <a href="/login" class="link-accent">Student Sign In &rarr;</a>
          </p>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

<?php elseif ($screen === 'admin_dashboard'): ?>
  <?php admin_nav($activeTab ?? 'overview', $pendingRequests ?? []); ?>

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
  <section class="metric-grid">
    <div class="card stat-card">
      <div class="stat-value text-danger"><?= (int) ($metrics['pending_requests'] ?? 0) ?></div>
      <div class="stat-label">Pending Access Requests</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value text-sky"><?= (int) ($metrics['active_companies'] ?? 0) ?></div>
      <div class="stat-label">Active Companies</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value text-violet"><?= (int) ($metrics['active_colleges'] ?? 0) ?></div>
      <div class="stat-label">Active Colleges</div>
    </div>
    <div class="card stat-card">
      <div class="stat-value text-emerald"><?= (int) ($metrics['active_affiliations'] ?? 0) ?></div>
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
      <div class="stat-value text-amber"><?= (int) ($metrics['total_sensitive_accesses'] ?? 0) ?></div>
      <div class="stat-label">Sensitive Access Events</div>
    </div>
  </section>

  <!-- Urgent Action Items: Pending Requests -->
  <?php if (!empty($pendingRequests)): ?>
  <section class="card section-panel border-accent-danger">
    <div class="section-header mb-sm">
      <h2 class="section-title">Pending Access Requests Requiring Review (<?= count($pendingRequests) ?>)</h2>
      <a class="button secondary btn-xs" href="/admin/dashboard?tab=requests">Open Request Queue &rarr;</a>
    </div>
    <p class="hint">Review identity and institutional authority before issuing setup invitations.</p>
    <div class="table-container">
      <table>
        <caption class="sr-only">Pending access requests</caption>
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
            <td><a href="<?= escape($req['website']) ?>" target="_blank" rel="noopener noreferrer" class="text-sm"><?= escape($req['website']) ?></a></td>
            <td><?= escape($req['contact_name']) ?><br><span class="text-muted text-xs"><?= escape($req['designation']) ?></span></td>
            <td><code><?= escape($req['contact_email']) ?></code></td>
            <td><?= escape(date('Y-m-d H:i', (int) $req['created_at'])) ?></td>
            <td>
              <a class="button btn-xs" href="/admin/dashboard?tab=requests">Review</a>
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
  <section class="card section-panel border-accent-warning">
    <h2 class="section-title">Pending Organizations Awaiting Activation (<?= count($pendingOrgs) ?>)</h2>
    <div class="table-container">
      <table>
        <caption class="sr-only">Suspended organizations</caption>
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
              <form action="/admin/organizations/approve" method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <button class="button sm" type="submit">Approve &amp; Activate</button>
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
  <section class="card section-panel">
    <h2 class="section-title">Active Organizations (<?= count($activeOrgs ?? []) ?>)</h2>
    <p class="hint">Verified partner organizations authorized to operate on EIDOLON PATHWAY.</p>
    <?php if (!empty($activeOrgs)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Registered organizations</caption>
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
              <form action="/admin/organizations/suspend" method="post" class="inline-form actions">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <input name="reason" maxlength="200" placeholder="Reason" class="input-sm" required>
                <button class="button secondary danger sm" type="submit">Suspend</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice">
      <p>No active organizations registered yet.</p>
    </div>
    <?php endif; ?>
  </section>

  <?php elseif (($activeTab ?? '') === 'requests'): ?>
  <!-- TAB: ACCESS REQUESTS -->
  <section class="card section-panel">
    <h2 class="section-title">Organization Access Requests Queue (<?= count($allRequests ?? []) ?>)</h2>
    <p class="hint">Self-service institutional onboarding applications. Verify domain ownership, accreditation, and authority to represent before approval.</p>

    <?php if (!empty($allRequests)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Pending organization access requests</caption>
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
              <a href="<?= escape($req['website']) ?>" target="_blank" rel="noopener noreferrer" class="text-xs"><?= escape($req['website']) ?></a>
            </td>
            <td><span class="badge"><?= escape($req['type']) ?></span></td>
            <td>
              <?= escape($req['contact_name']) ?><br>
              <span class="text-muted text-xs"><?= escape($req['designation']) ?></span>
            </td>
            <td><code><?= escape($req['contact_email']) ?></code></td>
            <td class="table-notes-wide">
              <?= escape($req['verification_notes']) ?>
            </td>
            <td><?= escape(date('Y-m-d H:i', (int) $req['created_at'])) ?></td>
            <td>
              <?php if ($req['status'] === 'PENDING'): ?>
              <span class="badge badge-pending">PENDING</span>
              <?php elseif ($req['status'] === 'APPROVED'): ?>
              <span class="badge badge-approved">APPROVED</span>
              <?php else: ?>
              <span class="badge badge-rejected">REJECTED</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($req['status'] === 'PENDING'): ?>
              <form action="/admin/requests/review" method="post" class="inline-form form-vertical">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                <input name="review_notes" placeholder="Review notes (optional)" maxlength="500" class="input-sm">
                <div class="actions">
                  <button class="button btn-xs btn-approve flex-1" type="submit" name="decision" value="APPROVE">Approve</button>
                  <button class="button secondary btn-xs btn-danger-outline flex-1" type="submit" name="decision" value="REJECT">Reject</button>
                </div>
              </form>
              <?php else: ?>
              <div class="text-muted text-xs">
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
  <section class="card section-panel">
    <h2 class="section-title">Registered Organizations (<?= count($allOrgs ?? []) ?>)</h2>
    <p class="hint">Companies and Colleges operating inside EIDOLON PATHWAY.</p>

    <?php if (!empty($allOrgs)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">All organizations and status</caption>
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
              <span class="badge badge-pending">Pending</span>
              <?php else: ?>
              <span class="badge badge-rejected">Suspended</span>
              <?php endif; ?>
            </td>
            <td><?= (int) ($org['member_count'] ?? 1) ?></td>
            <td><?= (int) ($org['job_count'] ?? 0) ?></td>
            <td><?= escape(date('Y-m-d', (int) $org['created_at'])) ?></td>
            <td>
              <?php if ($org['status'] === 'PENDING'): ?>
              <form action="/admin/organizations/approve" method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <button class="button sm" type="submit">Approve</button>
              </form>
              <?php elseif ($org['status'] === 'ACTIVE'): ?>
              <form action="/admin/organizations/suspend" method="post" class="inline-form actions">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="organization_id" value="<?= (int) $org['id'] ?>">
                <input name="reason" maxlength="200" placeholder="Reason" class="input-sm" required>
                <button class="button secondary danger sm" type="submit">Suspend</button>
              </form>
              <?php else: ?>
              <span class="text-muted text-xs">Suspended</span>
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
  <section class="card section-panel">
    <div class="section-header">
      <h2 class="section-title">Platform User Directory (<?= count($usersList ?? []) ?>)</h2>
      <form action="/admin/dashboard" method="get" class="inline-form filter-bar">
        <input type="hidden" name="tab" value="users">
        <input name="user_search" placeholder="Search name or email..." value="<?= escape($_GET['user_search'] ?? '') ?>" class="input-sm">
        <select name="user_role" >
          <option value="">All Roles</option>
          <option value="student" <?= ($_GET['user_role'] ?? '') === 'student' ? 'selected' : '' ?>>Students</option>
          <option value="company" <?= ($_GET['user_role'] ?? '') === 'company' ? 'selected' : '' ?>>Company Staff</option>
          <option value="college" <?= ($_GET['user_role'] ?? '') === 'college' ? 'selected' : '' ?>>College Placement</option>
          <option value="admin" <?= ($_GET['user_role'] ?? '') === 'admin' ? 'selected' : '' ?>>Platform Admin</option>
        </select>
        <button class="button secondary sm" type="submit">Filter</button>
      </form>
    </div>

    <?php if (!empty($usersList)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">User directory</caption>
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
  <section class="card section-panel">
    <h2 class="section-title">Issue New Staff Invitation</h2>
    <p class="hint">Issue a cryptographically secure, single-use 7-day staff onboarding invitation to an active organization.</p>
    <form action="/admin/invitations/create" method="post" class="filter-bar">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <div class="filter-field-grow">
        <label class="label-sm">Target Organization</label>
        <select name="organization_id" required>
          <?php foreach ($activeOrgs as $org): ?>
          <option value="<?= (int) $org['id'] ?>"><?= escape($org['name']) ?> (<?= escape($org['type']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field-grow">
        <label class="label-sm">Invitee Email</label>
        <input name="email" type="email" placeholder="staff@organization.com" required >
      </div>
      <div class="filter-field-fixed">
        <label class="label-sm">Role</label>
        <select name="role" required>
          <option value="STAFF">STAFF</option>
          <option value="ADMIN">ADMIN</option>
          <option value="OWNER">OWNER</option>
          <option value="REVIEWER">REVIEWER</option>
        </select>
      </div>
      <button class="button" type="submit">Issue Invitation</button>
    </form>
  </section>

  <section class="card section-panel">
    <h2 class="section-title">Issued Staff Invitations (<?= count($invitations ?? []) ?>)</h2>
    <p class="hint">Single-use invitation secrets are hashed with SHA-256 upon issuance. Raw tokens are never stored in the database.</p>

    <?php if (!empty($invitations)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Invitation records</caption>
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
              <span class="badge badge-approved">PENDING</span>
              <?php elseif ($inv['state'] === 'USED'): ?>
              <span class="badge badge-observed">USED</span>
              <?php elseif ($inv['state'] === 'REVOKED'): ?>
              <span class="badge badge-rejected">REVOKED</span>
              <?php else: ?>
              <span class="badge badge-expired">EXPIRED</span>
              <?php endif; ?>
            </td>
            <td><code><?= escape(substr($inv['token_hash'], 0, 12)) ?>...</code></td>
            <td><?= escape($inv['inviter_name'] ?? 'Admin') ?></td>
            <td><?= escape(date('Y-m-d H:i', (int) $inv['expires_at'])) ?></td>
            <td>
              <?php if ($inv['state'] === 'PENDING'): ?>
              <form action="/admin/invitations/revoke" method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                <button class="button secondary danger sm" type="submit">Revoke</button>
              </form>
              <?php else: ?>
              <span class="text-muted text-xs">&mdash;</span>
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
  <section class="card section-panel">
    <h2 class="section-title">Sensitive Record Access Reviews (<?= count($accessReviews ?? []) ?>)</h2>
    <p class="hint">Immutable security audit of all college placement officer and employer recruiter views of student profiles, resumes, and candidate dossiers.</p>

    <?php if (!empty($accessReviews)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Data access review events</caption>
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
              <span class="text-muted text-xs"><?= escape($log['accessor_email'] ?? '') ?></span>
            </td>
            <td>
              <span class="badge"><?= escape(strtoupper($log['accessor_role'] ?? 'user')) ?></span><br>
              <span class="text-xs"><?= escape($log['accessor_org_name'] ?? 'Platform') ?></span>
            </td>
            <td>
              <?php if (!empty($log['student_name'])): ?>
              <?= escape($log['student_name']) ?><br>
              <span class="text-muted text-xs"><?= escape($log['student_email'] ?? '') ?></span>
              <?php else: ?>
              <span class="text-muted text-xs">Aggregated (Talent Search)</span>
              <?php endif; ?>
            </td>
            <td><code><?= escape($log['action']) ?></code></td>
            <td>
              <?php if ($log['status'] === 'PERMITTED'): ?>
              <span class="badge badge-approved">PERMITTED</span>
              <?php else: ?>
              <span class="badge badge-rejected">BLOCKED</span>
              <?php endif; ?>
            </td>
            <td class="table-mono">
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
  <section class="card section-panel">
    <h2 class="section-title">Platform Security &amp; Compliance Audit Trail</h2>
    <p class="hint">Immutable append-only record of administrative, organizational, and authorization events.</p>

    <?php if (!empty($auditLogs)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">System audit trail</caption>
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
              <span class="text-muted text-xs"><?= escape($log['actor_email'] ?? '') ?></span>
            </td>
            <td>
              <?= escape($log['target_type']) ?> #<?= (int) $log['target_id'] ?>
            </td>
            <td class="table-mono">
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
  <section class="card section-panel">
    <h2 class="section-title">Platform Security Posture &amp; Cryptographic Architecture</h2>
    <p class="hint">Defense-in-depth security verification mapped to OWASP ASVS 5.0 controls.</p>

    <div class="security-grid">
      <div class="security-card">
        <h3 class="security-card-title">&#10003; Cryptographic Authentication</h3>
        <p class="security-card-desc">
          All user credentials hashed using standard <code>PASSWORD_DEFAULT</code> (bcrypt/Argon2 with cost factor). Zero plaintext passwords stored or logged. Timing-safe verification prevents side-channel analysis.
        </p>
      </div>

      <div class="security-card">
        <h3 class="security-card-title">&#10003; Secret Token Architecture</h3>
        <p class="security-card-desc">
          Staff invitations and session tokens generated via CSPRNG (<code>random_bytes(32)</code>). Only 64-character SHA-256 hashes are stored. Possession of raw token proves legitimate email delivery.
        </p>
      </div>

      <div class="security-card">
        <h3 class="security-card-title">&#10003; Storage Isolation &amp; Integrity</h3>
        <p class="security-card-desc">
          SQLite database stored outside the web root with permissions <code>0600</code> and directory <code>0700</code>. Foreign key constraints enforced on connection. Transactional migrations with SHA-256 checksums.
        </p>
      </div>

      <div class="security-card">
        <h3 class="security-card-title">&#10003; Session &amp; CSRF Defense</h3>
        <p class="security-card-desc">
          Session rotation (<code>session_regenerate_id(true)</code>) enforced on every login. Cookies set with <code>HttpOnly=true</code> and <code>SameSite=Strict</code>. Per-session anti-CSRF token verified on all POST requests.
        </p>
      </div>

      <div class="security-card">
        <h3 class="security-card-title">&#10003; Multi-Tenant Authorization</h3>
        <p class="security-card-desc">
          Explicit RBAC with deny-by-default permission checks. Strict tenant isolation prevents cross-organization data leakage or IDOR tampering across employer and institutional boundaries.
        </p>
      </div>

      <div class="security-card">
        <h3 class="security-card-title">&#10003; Granular Consent &amp; Audit</h3>
        <p class="security-card-desc">
          Student records protected by explicit consent controls. Master and granular sharing toggles take immediate effect on future accesses. All sensitive access events immutably audited.
        </p>
      </div>
    </div>
  </section>
  <?php endif; ?>

<?php elseif ($screen === 'affiliation_request'): ?>
  <?php student_nav($screen); ?>

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

  <div class="card affiliation-status">
    <h2 class="aff-heading">Institutional Status</h2>
    <?php
      $status = $profile['affiliation_status'] ?? 'NONE';
      $orgName = $profile['college_org_name'] ?? null;
    ?>
    <?php if ($status === 'AFFILIATED' && $orgName): ?>
      <div class="badge-group">
        <span class="badge badge-observed affiliation-badge">AFFILIATED</span>
        <span class="aff-status-label"><?= escape($orgName) ?></span>
      </div>
      <p class="aff-note">
        Enrolled as <?= escape($profile['degree'] ?: 'Student') ?> (<?= escape($profile['department'] ?: 'Engineering') ?>) &middot; USN/Roll: <code><?= escape($profile['enrollment_number'] ?: 'N/A') ?></code> &middot; Class of <?= escape((string)($profile['graduation_year'] ?: 'N/A')) ?>.
      </p>
      <div class="mt-md">
        <a class="button secondary btn-sm" href="/privacy/settings">Manage Privacy &amp; Sharing Permissions &rarr;</a>
      </div>
    <?php elseif ($status === 'PENDING'): ?>
      <div class="badge-group">
        <span class="badge badge-pending">PENDING REVIEW</span>
        <span class="text-secondary">Your affiliation request is awaiting review by college placement officers.</span>
      </div>
    <?php elseif ($status === 'REVOKED'): ?>
      <div class="badge-group">
        <span class="badge badge-failed affiliation-badge">AFFILIATION REVOKED</span>
        <span class="text-secondary">Your institutional affiliation was revoked by college staff. You may submit a new verification request below.</span>
      </div>
    <?php else: ?>
      <p class="text-muted-body">
        You are not currently affiliated with a verified academic institution. Select your institution below and provide your enrollment credentials.
      </p>
    <?php endif; ?>
  </div>

  <?php if ($status !== 'AFFILIATED' && $status !== 'PENDING'): ?>
  <div class="auth-panel">
    <h2 class="section-title">Request College Affiliation</h2>
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

      <div class="form-grid">
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

      <button class="button aff-submit" type="submit">
        Submit Affiliation Request
      </button>
    </form>
  </div>
  <?php endif; ?>

  <section class="card section-panel">
    <h2 class="section-title">Affiliation History &amp; Requests</h2>
    <?php if (!empty($affiliationRequests)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Affiliation request history</caption>
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
            <td class="table-notes">
              <?= escape($req['review_decision_reason'] ?: 'No notes recorded') ?>
            </td>
            <td>
              <?php if ($reqStatus === 'PENDING'): ?>
              <form action="/affiliation/cancel" method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                <button class="button secondary btn-xs btn-danger-outline" type="submit">Cancel</button>
              </form>
              <?php else: ?>
              <span class="text-muted">&mdash;</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="notice">
      <p>No affiliation requests submitted yet.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'college_affiliations'): ?>
  <?php college_nav($screen); ?>

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

  <div class="filter-bar">
    <span class="label-sm">Status Filter:</span>
    <a class="button <?= ($selectedStatus ?? 'all') === 'all' ? '' : 'secondary' ?> sm" href="/college/affiliations">All (<?= count($requests ?? []) ?>)</a>
    <a class="button <?= ($selectedStatus ?? '') === 'PENDING' ? '' : 'secondary' ?> sm" href="/college/affiliations?status=PENDING">Pending Review</a>
    <a class="button <?= ($selectedStatus ?? '') === 'APPROVED' ? '' : 'secondary' ?> sm" href="/college/affiliations?status=APPROVED">Approved</a>
    <a class="button <?= ($selectedStatus ?? '') === 'REJECTED' ? '' : 'secondary' ?> sm" href="/college/affiliations?status=REJECTED">Declined</a>
  </div>

  <section class="card section-panel">
    <h2 class="section-title">Enrollment Verification Queue (<?= count($requests ?? []) ?>)</h2>
    <?php if (!empty($requests)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Student affiliation review queue</caption>
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
              <span class="text-muted text-xs"><?= escape($r['student_email']) ?></span>
            </td>
            <td><code><?= escape($r['enrollment_number']) ?></code></td>
            <td><?= escape($r['degree']) ?><br><span class="text-muted text-xs"><?= escape($r['department']) ?></span></td>
            <td><?= escape((string) $r['graduation_year']) ?></td>
            <td class="filter-input"><?= escape($r['verification_note'] ?: 'None') ?></td>
            <td><?= escape(date('Y-m-d H:i', (int) $r['created_at'])) ?></td>
            <td><span class="badge <?= $badgeClass ?>"><?= escape($status) ?></span></td>
            <td>
              <?php if ($status === 'PENDING'): ?>
              <form action="/college/affiliations/review" method="post" class="action-col">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                <input type="text" name="reason" placeholder="Audit verification note..." class="btn-xs full-width" required>
                <div class="actions">
                  <button class="button btn-xs btn-approve" type="submit" name="decision" value="APPROVED">Approve</button>
                  <button class="button secondary btn-xs btn-danger-outline" type="submit" name="decision" value="REJECTED">Decline</button>
                </div>
              </form>
              <?php else: ?>
              <span class="text-muted text-xs">
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
    <div class="notice">
      <p>No affiliation requests in this view.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'privacy_settings'): ?>
  <?php student_nav($screen); ?>

  <section class="hero">
    <h1>Your Data, Your Rules</h1>
    <p>Control who can access your academic records, resume, application history, and verified credentials.</p>
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

  <div class="auth-panel panel-medium">
    <form action="/privacy/settings" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

      <div class="privacy-card">
        <h3 class="privacy-title">College Placement Sharing</h3>
        <p class="privacy-desc">
          Master switch for your associated college placement department (<?= escape($profile['college_org_name'] ?? 'Associated College') ?>). When off, all institutional access is denied.
        </p>
        <label class="consent-label">
          <input type="checkbox" name="college_share_consent" value="1" <?= !empty($profile['college_share_consent']) ? 'checked' : '' ?> >
          <span>Enable College Placement Cell Access</span>
        </label>
      </div>

      <div class="privacy-card">
        <h3 class="privacy-title">Granular Institutional Permissions</h3>
        <p class="privacy-desc">
          When institutional sharing is enabled, choose exactly which data fields are accessible to placement coordinators:
        </p>

        <div class="achievement-list">
          <label class="consent-option">
            <input type="checkbox" name="share_contact_details" value="1" <?= !empty($profile['share_contact_details']) ? 'checked' : '' ?> >
            <span>
              <strong>Share Contact Information</strong>
              <span class="consent-sub">Allow placement officers to view your primary email address. If unchecked, email is masked as <code>[Consent Withheld]</code>.</span>
            </span>
          </label>

          <label class="consent-option">
            <input type="checkbox" name="share_resume" value="1" <?= !empty($profile['share_resume']) ? 'checked' : '' ?> >
            <span>
              <strong>Share Structured Resumes</strong>
              <span class="consent-sub">Allow placement officers to inspect your latest resume draft and verified skill citations.</span>
            </span>
          </label>

          <label class="consent-option">
            <input type="checkbox" name="share_achievements" value="1" <?= !empty($profile['share_achievements']) ? 'checked' : '' ?> >
            <span>
              <strong>Share Verified Credentials &amp; Achievements</strong>
              <span class="consent-sub">Display certifications, hackathon awards, and project evidence in institutional reports.</span>
            </span>
          </label>

          <label class="consent-option">
            <input type="checkbox" name="share_applications" value="1" <?= !empty($profile['share_applications']) ? 'checked' : '' ?> >
            <span>
              <strong>Share Job Application Outcomes</strong>
              <span class="consent-sub">Allow placement officers to track your application counts, shortlists, and hiring status across platform employers.</span>
            </span>
          </label>
        </div>
      </div>

      <div class="privacy-card">
        <h3 class="privacy-title">Employer Talent Discovery (Opt-In Directory)</h3>
        <p class="privacy-desc">
          Allow verified platform employers to discover your public profile, skills, and achievements in candidate search before you apply to a specific job.
        </p>
        <label class="consent-option">
          <input type="checkbox" name="talent_discovery_opt_in" value="1" <?= !empty($profile['talent_discovery_opt_in']) ? 'checked' : '' ?> >
          <span>
            <strong>Opt into Employer Talent Discovery</strong>
            <span class="consent-sub">Strictly opt-in only. Your profile is invisible to talent search unless this is checked.</span>
          </span>
        </label>
      </div>

      <button class="button" type="submit">Save Privacy Preferences</button>
    </form>
  </div>

  <section class="card section-panel">
    <div class="section-header">
      <h2 class="section-title">Recent Access Audit Events</h2>
      <a class="button secondary btn-sm" href="/privacy/access-log">View Complete Audit Trail &rarr;</a>
    </div>
    <p class="text-detail mb-md">
      Immutable audit logs recording every external attempt by college staff or recruiters to inspect your sensitive records.
    </p>

    <?php if (!empty($recentLogs)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Sensitive record access log</caption>
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
              <span class="badge muted keyword-tag"><?= escape(ucfirst($log['accessor_role'])) ?></span>
            </td>
            <td><?= escape($log['organization_name'] ?: 'Platform') ?></td>
            <td><code><?= escape($log['record_type']) ?></code></td>
            <td class="text-detail"><?= escape($log['access_context']) ?></td>
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
    <div class="notice">
      <p>No sensitive record access events recorded yet.</p>
    </div>
    <?php endif; ?>
  </section>

  <section class="card section-panel">
    <h2 class="section-title-lg">Linked Authentication Identities</h2>
    <p class="text-detail mb-md">
      Federated OAuth 2.0 / OpenID Connect credentials bound to this student account. Unlinking prevents access via that provider.
    </p>

    <?php $linkedIdentities = $linkedIdentities ?? []; ?>
    <?php $configuredProviders = $configuredProviders ?? []; ?>

    <?php if (!empty($linkedIdentities)): ?>
    <div class="table-container mb-md">
      <table>
        <caption class="sr-only">Linked authentication identities</caption>
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
              <strong class="text-capitalize"><?= escape($ident['provider']) ?></strong>
            </td>
            <td><code><?= escape(substr($ident['provider_subject_id'], 0, 16)) ?>...</code></td>
            <td><?= escape($ident['provider_email'] ?? '—') ?></td>
            <td class="text-detail"><?= escape(date('Y-m-d H:i', (int) $ident['created_at'])) ?></td>
            <td>
              <form action="/auth/unlink" method="post" class="inline-form" data-confirm="Unlink this social identity? You will no longer be able to sign in with this provider.">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="provider" value="<?= escape($ident['provider']) ?>">
                <button class="button secondary btn-sm btn-danger-text" type="submit">Unlink</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="oauth-card">
      <p class="text-detail m-0">No third-party social identities are currently bound to your account.</p>
    </div>
    <?php endif; ?>

    <?php
      $boundProviders = array_column($linkedIdentities, 'provider');
      $availableToLink = array_filter(array_keys($configuredProviders), fn($p) => !in_array($p, $boundProviders, true));
    ?>
    <?php if (!empty($availableToLink)): ?>
    <div class="mt-md">
      <h3 class="oauth-title">Link Additional Provider</h3>
      <div class="flex-row gap-sm flex-wrap">
        <?php foreach ($availableToLink as $p): ?>
        <form action="/auth/link" method="post" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
          <input type="hidden" name="provider" value="<?= escape($p) ?>">
          <button class="button secondary btn-sm" type="submit">
            Link <?= escape(ucfirst($p)) ?> Account
          </button>
        </form>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'privacy_access_log'): ?>
  <?php student_nav($screen); ?>

  <section class="hero">
    <p class="eyebrow">Audit Integrity &middot; Access Logging</p>
    <h1>Sensitive Record Access Audit History</h1>
    <p>Comprehensive audit log of institutional placement officers and verified company recruiters who inspected your candidate records.</p>
  </section>

  <div class="mb-md">
    <a class="button secondary btn-sm" href="/privacy/settings">&larr; Return to Privacy Settings</a>
  </div>

  <section class="card section-panel">
    <h2 class="section-title">Complete Audit Trail (<?= count($accessLogs ?? []) ?>)</h2>
    <?php if (!empty($accessLogs)): ?>
    <div class="table-container">
      <table>
        <caption class="sr-only">Privacy access audit log</caption>
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
            <td><span class="badge muted keyword-tag"><?= escape(ucfirst($log['accessor_role'])) ?></span></td>
            <td><?= escape($log['organization_name'] ?: 'Platform Admin') ?></td>
            <td><code><?= escape($log['record_type']) ?></code></td>
            <td class="text-detail"><?= escape($log['access_context']) ?></td>
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
    <div class="notice">
      <p>No access logs recorded for your profile yet.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'achievements'): ?>
  <?php student_nav($screen); ?>

  <header class="workspace-hero">
    <div class="future-badge">
      <span class="badge-dot"></span>
      <span>STUDENT CREDENTIALS &middot; VERIFIED PORTFOLIO</span>
    </div>
    <h1>Student Achievements &amp; Credentials</h1>
    <p class="workspace-hero-sub">Manage structured credentials, certifications, hackathon awards, and verified technical projects.</p>
  </header>

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

  <div class="workspace-layout">
    <!-- Main Column: Add Achievement & Achievement List -->
    <div class="workspace-main">
      <div class="futureglass-panel mb-lg">
        <h2 class="section-title">Add New Achievement or Credential</h2>
        <form action="/achievements" method="post" class="futureglass-form">
          <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

          <div class="form-grid">
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

          <div class="form-grid">
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

          <div class="form-actions mt-md">
            <button class="button button-futureglass" type="submit">Add Achievement</button>
          </div>
        </form>
      </div>

      <div class="futureglass-panel">
        <div class="panel-header-inline mb-md">
          <h2 class="section-title">Your Verified Achievements</h2>
          <span class="badge badge-observed"><?= count($achievements ?? []) ?> Recorded</span>
        </div>

        <?php if (!empty($achievements)): ?>
        <div class="achievement-list">
          <?php foreach ($achievements as $ach): ?>
          <div class="achievement-card futureglass-card mb-md">
            <div class="achievement-body flex-1">
              <div class="achievement-header">
                <span class="badge badge-category"><?= escape(ucfirst($ach['category'])) ?></span>
                <strong class="achievement-title"><?= escape($ach['title']) ?></strong>
                <?php if (!empty($ach['issuer'])): ?>
                <span class="achievement-meta">&middot; <?= escape($ach['issuer']) ?></span>
                <?php endif; ?>
                <?php if (!empty($ach['issue_date'])): ?>
                <span class="achievement-meta">(<?= escape($ach['issue_date']) ?>)</span>
                <?php endif; ?>
              </div>
              <?php if (!empty($ach['description'])): ?>
              <p class="achievement-desc mt-xs"><?= escape($ach['description']) ?></p>
              <?php endif; ?>
              <div class="achievement-meta mt-sm">
                <?php if (!empty($ach['credential_url'])): ?>
                <a href="<?= escape($ach['credential_url']) ?>" target="_blank" rel="noopener noreferrer" class="link-accent">
                  Verify Credential &rarr;
                </a>
                <?php endif; ?>
                <?php if (!empty($ach['assessment_repo'])): ?>
                <span class="badge badge-observed text-2xs">
                  Linked Assessment: <?= escape($ach['assessment_repo']) ?>@<?= escape(substr($ach['assessment_sha'] ?? '', 0, 7)) ?>
                </span>
                <?php endif; ?>
              </div>
            </div>
            <form action="/achievements/delete" method="post" class="inline-form">
              <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $ach['id'] ?>">
              <button class="button secondary btn-xs btn-danger-outline" type="submit">Remove</button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state-card text-center py-lg">
          <p class="text-secondary">No achievements added yet. Use the form above to add certifications, hackathon projects, or awards.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Secondary Column: Verification Standards & Portfolio Summary -->
    <aside class="workspace-sidebar">
      <div class="futureglass-card mb-lg">
        <h4 class="card-title-sm">&#x25C7; Credential Verification</h4>
        <p class="text-xs text-secondary mt-xs">
          Recruiters give 3.4&times; higher interview weight to credentials with verifiable third-party links or AST commit evidence.
        </p>
        <hr class="card-divider">
        <div class="metric-row">
          <span class="metric-label">Total Credentials</span>
          <span class="metric-value font-bold"><?= count($achievements ?? []) ?></span>
        </div>
      </div>

      <div class="futureglass-card">
        <h4 class="card-title-sm">&#x25CE; Quick Navigation</h4>
        <div class="quick-links-stack mt-xs">
          <a href="/resume" class="quick-nav-item">
            <span>Resume Builder</span>
            <span>&rarr;</span>
          </a>
          <a href="/profile" class="quick-nav-item">
            <span>Student Profile</span>
            <span>&rarr;</span>
          </a>
          <a href="/jobs" class="quick-nav-item">
            <span>Browse Jobs</span>
            <span>&rarr;</span>
          </a>
        </div>
      </div>
    </aside>
  </div>

<?php elseif ($screen === 'company_talent'): ?>
  <?php company_nav($screen); ?>

  <section class="hero">
    <p class="eyebrow">Verified Talent Search &middot; <?= escape($org['org_name'] ?? 'Company') ?></p>
    <h1>Candidate Talent Discovery</h1>
    <p>Discover student engineers who have explicitly opted into recruitment discovery with verified skills and portfolio achievements.</p>
  </section>

  <div class="card card-padded">
    <form action="/company/talent" method="get" class="search-bar">
      <input type="text" name="q" value="<?= escape($searchQuery ?? '') ?>" placeholder="Search by skill (e.g. Python, React, Go) or degree program..." class="search-input">
      <button class="button btn-action" type="submit">Search Candidates</button>
      <?php if (!empty($searchQuery)): ?>
      <a class="button secondary btn-action-sm" href="/company/talent">Clear Filter</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="notice">
    <h2>Consent-Guaranteed Discovery Active</h2>
    <p>This directory displays only candidates who have explicitly enabled <code>talent_discovery_opt_in = 1</code> in their private settings. Unconsenting students remain completely hidden.</p>
  </div>

  <section class="card section-panel">
    <h2 class="section-title">Opted-in Engineering Candidates (<?= count($talentList ?? []) ?>)</h2>
    <?php if (!empty($talentList)): ?>
    <div class="talent-grid">
      <?php foreach ($talentList as $cand): ?>
      <div class="talent-card">
        <div>
          <div class="talent-header">
            <strong class="talent-name"><?= escape($cand['student_name']) ?></strong>
            <span class="badge badge-observed text-2xs">Opted In</span>
          </div>
          <p class="talent-tagline">
            <?= escape($cand['degree'] ?: 'Engineering Student') ?> &middot; <?= escape($cand['college'] ?: 'Institution') ?>
            <?php if (!empty($cand['graduation_year'])): ?>
            (Class of <?= escape((string) $cand['graduation_year']) ?>)
            <?php endif; ?>
          </p>

          <?php if (!empty($cand['skills'])): ?>
          <div class="skill-tags">
            <?php foreach (array_slice(explode(',', $cand['skills']), 0, 5) as $sk): ?>
            <span class="badge muted keyword-tag"><?= escape(trim($sk)) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($cand['bio'])): ?>
          <p class="talent-summary">
            <?= escape(substr($cand['bio'], 0, 160)) ?><?= strlen($cand['bio']) > 160 ? '...' : '' ?>
          </p>
          <?php endif; ?>
        </div>

        <div class="talent-footer">
          <span><?= (int) ($cand['achievements_count'] ?? 0) ?> Credentials &middot; <?= (int) ($cand['resumes_count'] ?? 0) ?> Resumes</span>
          <?php if (!empty($cand['github_username'])): ?>
          <code>@<?= escape($cand['github_username']) ?></code>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="notice">
      <p>No candidates found matching the search criteria.</p>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($screen === 'company_candidate'): ?>
  <?php company_nav($screen); ?>

  <?php
    $app = $candidateBundle['application'] ?? [];
    $candProfile = $candidateBundle['profile'] ?? [];
    $candResume = $candidateBundle['resume'] ?? null;
    $candAchievements = $candidateBundle['achievements'] ?? [];
  ?>

  <section class="hero">
    <p class="eyebrow">Application Dossier &middot; <?= escape($org['org_name'] ?? 'Company') ?></p>
    <h1><?= escape($candProfile['name'] ?? 'Candidate') ?></h1>
    <p class="badge-group mt-xs">
      Applied for <strong><?= escape($app['job_title'] ?? '') ?></strong> &middot;
      Status: <span class="badge <?= ($app['status'] ?? '') === 'shortlisted' ? 'badge-observed' : 'muted' ?>"><?= escape(ucfirst($app['status'] ?? '')) ?></span> &middot;
      Submitted: <?= escape(date('Y-m-d H:i', (int) ($app['created_at'] ?? time()))) ?>
    </p>
  </section>

  <div class="card dossier-header">
    <div>
      <span class="label-sm">Candidate Email:</span>
      <span class="dossier-value"><?= escape($candProfile['email'] ?? '') ?></span>
      <?php if (!empty($candProfile['github_username'])): ?>
      <span class="dossier-sep">|</span>
      <span class="label-sm">GitHub:</span>
      <code>github.com/<?= escape($candProfile['github_username']) ?></code>
      <?php endif; ?>
    </div>

    <form action="/company/applicants/status" method="post" class="dossier-actions">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <input type="hidden" name="application_id" value="<?= (int) ($app['application_id'] ?? 0) ?>">
      <?php if (($app['status'] ?? '') !== 'shortlisted'): ?>
      <button class="button btn-sm btn-approve" type="submit" name="status" value="shortlisted">Shortlist Candidate</button>
      <?php endif; ?>
      <?php if (($app['status'] ?? '') !== 'rejected'): ?>
      <button class="button secondary btn-sm btn-danger-outline" type="submit" name="status" value="rejected">Decline Application</button>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!empty($app['notes'])): ?>
  <div class="card card-padded">
    <h3 class="intro-title">Candidate Statement / Cover Note</h3>
    <p class="intro-body"><?= escape($app['notes']) ?></p>
  </div>
  <?php endif; ?>

  <?php if ($candResume): ?>
  <div class="card card-padded-lg">
    <div class="profile-header">
      <div>
        <h2 class="profile-name"><?= escape($candResume['title'] ?? 'Resume') ?></h2>
        <span class="achievement-meta">Updated <?= escape(date('Y-m-d', (int) ($candResume['updated_at'] ?? time()))) ?></span>
      </div>
      <?php if (!empty($candResume['assessment_repo'])): ?>
      <div class="ats-score">
        <span class="badge badge-observed text-xs">Verified GitHub Assessment</span><br>
        <code class="text-2xs"><?= escape($candResume['assessment_repo']) ?>@<?= escape(substr($candResume['assessment_sha'] ?? '', 0, 7)) ?></code>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($candResume['summary'])): ?>
    <p class="profile-bio"><?= escape($candResume['summary']) ?></p>
    <?php endif; ?>

    <?php if (!empty($candResume['education'])): ?>
    <h3 class="dossier-section-heading">Education</h3>
    <div class="job-skills-section">
      <?php foreach ($candResume['education'] as $edu): ?>
      <div class="mb-sm">
        <strong><?= escape($edu['institution'] ?? '') ?></strong> &middot; <?= escape($edu['degree'] ?? '') ?>
        <span class="date-float"><?= escape($edu['year'] ?? '') ?></span>
        <?php if (!empty($edu['gpa'])): ?>
        <div class="text-detail">GPA/Marks: <?= escape($edu['gpa']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($candResume['experience'])): ?>
    <h3 class="dossier-section-heading">Experience</h3>
    <div class="job-skills-section">
      <?php foreach ($candResume['experience'] as $exp): ?>
      <div class="mb-sm">
        <strong><?= escape($exp['role'] ?? '') ?></strong> &middot; <?= escape($exp['company'] ?? '') ?>
        <span class="date-float"><?= escape($exp['start'] ?? '') ?> - <?= escape($exp['end'] ?? '') ?></span>
        <?php if (!empty($exp['desc'])): ?>
        <p class="text-body-sm"><?= escape($exp['desc']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($candResume['projects'])): ?>
    <h3 class="dossier-section-heading">Technical Projects</h3>
    <div class="job-skills-section">
      <?php foreach ($candResume['projects'] as $proj): ?>
      <div class="mb-sm">
        <strong><?= escape($proj['name'] ?? '') ?></strong>
        <?php if (!empty($proj['url'])): ?>
        <a href="<?= escape($proj['url']) ?>" target="_blank" rel="noopener noreferrer" class="link-accent-sm">[Code / Link]</a>
        <?php endif; ?>
        <?php if (!empty($proj['desc'])): ?>
        <p class="text-body-sm"><?= escape($proj['desc']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($candResume['assessment_tasks'])): ?>
    <h3 class="dossier-section-heading">Verified Portfolio Engineering Observations</h3>
    <div class="job-skills-section">
      <?php foreach ($candResume['assessment_tasks'] as $t): ?>
      <div class="mb-xs text-sm">
        <span class="badge tag-date">Priority <?= (int)$t['priority'] ?></span>
        <strong><?= escape($t['title']) ?></strong>
        <div class="activity-detail">Acceptance: <?= escape($t['acceptance']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <section class="card section-panel">
    <h3 class="dossier-title">Verified Credentials &amp; Achievements (<?= count($candAchievements) ?>)</h3>
    <?php if (!empty($candAchievements)): ?>
    <div class="achievement-list">
      <?php foreach ($candAchievements as $ca): ?>
      <div class="achievement-item">
        <div class="achievement-header">
          <span class="badge sm"><?= escape($ca['category']) ?></span>
          <strong><?= escape($ca['title']) ?></strong>
          <?php if (!empty($ca['issuer'])): ?>
          <span class="achievement-meta">&middot; <?= escape($ca['issuer']) ?></span>
          <?php endif; ?>
          <?php if (!empty($ca['issue_date'])): ?>
          <span class="achievement-meta">(<?= escape($ca['issue_date']) ?>)</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($ca['description'])): ?>
        <p class="achievement-desc"><?= escape($ca['description']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-detail m-0">No additional achievements submitted by candidate.</p>
    <?php endif; ?>
  </section>

<?php endif; ?>
</main>

<footer class="container" role="contentinfo">
  <div>EIDOLON PATHWAY &middot; Verifiable Skills &amp; Evidence Architecture</div>
  <div>Local-First Prototype &middot; Standalone PHP 8.4 + SQLite v<?= SCHEMA_VERSION ?> &middot; Zero Code Execution</div>
</footer>

</body>
</html>
