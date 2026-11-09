<?php
declare(strict_types=1);
namespace Pathway;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $screen === 'dashboard' ? 'Student dashboard' : ($screen === 'assessment' ? 'Assessment results' : 'Student access') ?> · EIDOLON PATHWAY</title>
  <link rel="stylesheet" href="/assets/pathway.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<nav class="navbar" aria-label="Main navigation">
  <a class="logo" href="/">EIDOLON <strong>PATHWAY</strong></a>
  <span class="environment">Local student pilot</span>
  <?php if ($screen === 'dashboard' || $screen === 'assessment'): ?>
  <form action="/logout" method="post">
    <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
    <button class="button secondary" type="submit">Sign out</button>
  </form>
  <?php endif; ?>
</nav>
<main id="main" class="container">
<?php if ($screen === 'error'): ?>
  <section class="auth-panel" aria-labelledby="error-title">
    <p class="eyebrow">Request not completed</p>
    <h1 id="error-title">Let's get you back on track.</h1>
    <p role="alert"><?= escape($message) ?></p>
    <div class="actions"><a class="button" href="/login">Return to sign in</a><a href="/register">Create a local account</a></div>
  </section>
<?php elseif ($screen === 'login' || $screen === 'register'): ?>
  <div class="auth-layout">
    <section class="hero">
      <p class="eyebrow">InternHunt · Student portal</p>
      <h1>Your next step starts with a clear view.</h1>
      <p>Build your pathway from project evidence to practical improvement.</p>
      <div class="notice"><strong>Local demonstration</strong><p>This slice supports student accounts, a dashboard and GitHub project assessments. Real listings and applications are not available yet.</p></div>
    </section>
    <section class="auth-panel" aria-labelledby="form-title">
      <h2 id="form-title"><?= $screen === 'register' ? 'Create your local account' : 'Welcome back' ?></h2>
      <p>Use a demonstration name and email. No email is sent or verified.</p>
      <?php if (is_string($flash)): ?><p class="notice" role="status"><?= escape($flash) ?></p><?php endif; ?>
      <form action="/<?= $screen ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
        <?php if ($screen === 'register'): ?>
        <label for="name">Demonstration name</label>
        <input id="name" name="name" autocomplete="name" maxlength="100" required>
        <?php endif; ?>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="username" maxlength="254" placeholder="student@example.test" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="<?= $screen === 'register' ? 'new-password' : 'current-password' ?>" <?= $screen === 'register' ? 'minlength="12"' : '' ?> maxlength="72" aria-describedby="password-help" required>
        <p class="hint" id="password-help">Use 12–72 bytes. Choose a password you do not use elsewhere.</p>
        <button class="button" type="submit"><?= $screen === 'register' ? 'Create student account' : 'Sign in' ?></button>
      </form>
      <p class="switch"><?= $screen === 'register' ? 'Already have an account?' : 'New to this local pilot?' ?> <a href="/<?= $screen === 'register' ? 'login' : 'register' ?>"><?= $screen === 'register' ? 'Sign in' : 'Create an account' ?></a></p>
    </section>
  </div>
<?php elseif ($screen === 'assessment'): ?>
  <section class="hero">
    <p class="eyebrow">Assessment results</p>
    <h1>Assessment #<?= (int) $assessment['id'] ?></h1>
    <p><a href="/dashboard">&larr; Back to dashboard</a></p>
  </section>

  <section class="account" aria-labelledby="assess-meta">
    <div>
      <h2 id="assess-meta">Repository</h2>
      <p><code><?= escape($assessment['repository']) ?></code></p>
    </div>
    <div>
      <p><span class="badge"><?= escape(ucfirst($assessment['target_role'])) ?></span>
      <span class="badge <?= $assessment['state'] === 'completed' ? '' : 'muted' ?>"><?= escape(ucfirst($assessment['state'])) ?></span></p>
      <?php if ($assessment['commit_sha']): ?>
      <p class="hint">Commit <code><?= escape(substr($assessment['commit_sha'], 0, 12)) ?></code> · Rubric <?= escape($assessment['rubric_version']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($assessment['state'] === 'failed'): ?>
  <section class="notice" role="alert" style="border-left-color:#b44">
    <h2>Assessment did not complete</h2>
    <p>Reason: <?= escape($assessment['error_code'] ?? 'unknown') ?></p>
  </section>
  <?php endif; ?>

  <?php if (!empty($assessment['result'])): ?>
  <section aria-labelledby="findings-title">
    <h2 id="findings-title">Evidence findings</h2>
    <?php foreach ($assessment['result']['findings'] ?? [] as $finding): ?>
    <div style="border-left:3px solid <?= $finding['state'] === 'observed' ? '#447c66' : ($finding['state'] === 'claimed' ? '#ba8c1b' : '#8a9196') ?>;padding:12px 20px;margin:12px 0;background:#f8faf7">
      <p style="margin-bottom:4px"><span class="badge <?= $finding['state'] === 'observed' ? '' : 'muted' ?>"><?= escape(ucfirst($finding['state'])) ?></span> <strong><?= escape($finding['label']) ?></strong></p>
      <p style="font-size:.92rem"><?= escape($finding['explanation']) ?></p>
      <?php if (!empty($finding['citations'])): ?>
      <ul style="font-size:.85rem;margin:4px 0 0">
        <?php foreach ($finding['citations'] as $cite): ?>
        <li><a href="<?= escape($cite['url']) ?>" rel="noopener noreferrer" target="_blank"><?= escape($cite['path']) ?>:<?= (int) $cite['line'] ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>

  <?php if (!empty($assessment['result']['limitations'])): ?>
  <section class="notice" aria-labelledby="limits-title">
    <h2 id="limits-title">Limitations and disclaimers</h2>
    <ul>
      <?php foreach ($assessment['result']['limitations'] as $limitation): ?>
      <li><?= escape($limitation) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($assessment['tasks'])): ?>
  <section aria-labelledby="tasks-title">
    <h2 id="tasks-title">Improvement tasks</h2>
    <div class="journey">
      <?php foreach ($assessment['tasks'] as $task): ?>
      <article>
        <span class="step">Priority <?= (int) $task['priority'] ?></span>
        <h2><?= escape($task['title']) ?></h2>
        <p><?= escape($task['acceptance']) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

<?php else: ?>
  <section class="hero">
    <p class="eyebrow">Student portal</p>
    <h1>Welcome back, <?= escape($user['name']) ?>.</h1>
    <p>Your local account is active. Assess a GitHub project or review your history.</p>
  </section>

  <?php
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashSuccess = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash']);
  ?>
  <?php if (is_string($flashError)): ?>
  <p class="notice" role="alert" style="border-left-color:#b44"><?= escape($flashError) ?></p>
  <?php elseif (is_string($flashSuccess)): ?>
  <p class="notice" role="status"><?= escape($flashSuccess) ?></p>
  <?php endif; ?>

  <section class="account" aria-labelledby="account-title">
    <div><h2 id="account-title">Your account</h2><p><?= escape($user['email']) ?></p></div>
    <p><span class="badge">Student</span> Saved in local SQLite</p>
  </section>

  <section class="auth-panel" aria-labelledby="assess-title" style="margin-bottom:32px">
    <h2 id="assess-title">Assess a GitHub project</h2>
    <p>Submit a public GitHub repository to receive evidence-based findings and improvement tasks.</p>
    <form action="/assess" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <label for="repository">Public GitHub repository</label>
      <input id="repository" name="repository" type="text" maxlength="180" placeholder="owner/repository or https://github.com/owner/repo" required aria-describedby="repo-help">
      <p class="hint" id="repo-help">Enter a public repository. Private or organization-internal repositories cannot be assessed.</p>
      <label for="target_role">Target developer role</label>
      <select id="target_role" name="target_role" required style="width:100%;padding:11px 13px;border:1px solid #9daea9;border-radius:6px;background:#fff;color:#183237;font:inherit">
        <option value="frontend">Frontend</option>
        <option value="backend" selected>Backend</option>
        <option value="fullstack">Fullstack</option>
      </select>
      <button class="button" type="submit">Run assessment</button>
    </form>
  </section>

  <?php if (!empty($assessments)): ?>
  <section aria-labelledby="history-title">
    <h2 id="history-title">Assessment history</h2>
    <table style="width:100%;border-collapse:collapse;margin:16px 0">
      <thead>
        <tr style="text-align:left;border-bottom:2px solid #ccd9d4">
          <th style="padding:8px 12px">ID</th>
          <th style="padding:8px 12px">Repository</th>
          <th style="padding:8px 12px">Role</th>
          <th style="padding:8px 12px">Status</th>
          <th style="padding:8px 12px">Date</th>
          <th style="padding:8px 12px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assessments as $a): ?>
        <tr style="border-bottom:1px solid #e0e6e2">
          <td style="padding:8px 12px">#<?= (int) $a['id'] ?></td>
          <td style="padding:8px 12px"><code><?= escape($a['repository']) ?></code></td>
          <td style="padding:8px 12px"><span class="badge muted"><?= escape(ucfirst($a['target_role'])) ?></span></td>
          <td style="padding:8px 12px"><span class="badge <?= $a['state'] === 'completed' ? '' : 'muted' ?>"><?= escape(ucfirst($a['state'])) ?></span></td>
          <td style="padding:8px 12px"><?= escape(date('Y-m-d H:i', (int) $a['created_at'])) ?></td>
          <td style="padding:8px 12px"><?php if ($a['state'] === 'completed'): ?><a href="/assess?id=<?= (int) $a['id'] ?>">View results</a><?php elseif ($a['state'] === 'failed'): ?><a href="/assess?id=<?= (int) $a['id'] ?>">View details</a><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php else: ?>
  <section class="notice" aria-labelledby="no-assess-title">
    <h2 id="no-assess-title">No assessments yet</h2>
    <p>Submit your first GitHub repository above to receive evidence-based findings and prioritized improvement tasks.</p>
  </section>
  <?php endif; ?>

  <section class="notice" aria-labelledby="demo-title">
    <h2 id="demo-title">Demonstration content — illustrative, not assessed</h2>
    <p>The examples below are static product previews. They are not live internships, personal skill assessments, or completed applications.</p>
  </section>
  <section class="journey" aria-label="Example student journey">
    <article><span class="step">01</span><h2>Build your profile</h2><p>Example task: describe one project, your contribution and the problem it solves.</p><span class="badge muted">Not migrated yet</span></article>
    <article><span class="step">02</span><h2>Explore opportunities</h2><p>Example role: frontend internship. No live job listings are connected in this slice.</p><span class="badge muted">Preview only</span></article>
    <article><span class="step">03</span><h2>Track your progress</h2><p>Future application tracking will show recorded statuses. No application metrics are available yet.</p><span class="badge muted">Not migrated yet</span></article>
  </section>
<?php endif; ?>
</main>
<footer class="container">EIDOLON PATHWAY · Local-first student prototype · Built on the InternHunt workflow</footer>
</body>
</html>
