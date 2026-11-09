# Judge demo — EIDOLON PATHWAY

This walkthrough covers **the repository checkout**, using entirely synthetic records. It does not imply that DSATM, any employer, or any real student is affiliated with the project.

## Setup

Requirements: PHP 8.2+ with PDO SQLite and PHP sessions (PHP 8.4 recommended). Python 3 is optional for HTTP smoke tests. Public GitHub assessment requires internet access.

```bash
git clone https://github.com/skandamp-sudo/eidolon-pathway.git
cd eidolon-pathway
php bin/init.php
php bin/seed_demo.php --confirm-demo-seed --demo-db=var/pathway_demo.sqlite
php bin/serve.php 8080 --demo
```

Open [http://127.0.0.1:8080](http://127.0.0.1:8080) on **the same computer**.

- The command above seeds `var/pathway_demo.sqlite` rather than your normal `var/pathway.sqlite`.
- Locally generated passwords are written into `var/demo_credentials.json`; **never publish or record that file**.
- Only use demo credentials in the isolated localhost walkthrough. Never use the seeded admin on an internet-connected deployment.

## Guided role tour

| Role | Local sign-in | What to demonstrate |
| --- | --- | --- |
| Student | `/login` | Profile, ATS-friendly resume, public GitHub assessment, sample job browsing, application history |
| Company recruiter | `/company/login` | Own company's demo vacancies, applicants, application status changes |
| College placement officer | `/college/login` | Authorized college dashboard; consenting versus confidential student records |
| Platform admin | `/admin/login` | Organization status and audit records; authorized governance |

The seeder currently creates synthetic student accounts for Aarav (consenting) and Priya (consent withheld), a DSATM-labeled *unaffiliated demo college*, and three fictional employers. Demo vacancies are **not real jobs**.

### Suggested 2–3-minute video script

**00:00–00:20 — The problem.** First-time developers have difficulty demonstrating project evidence, identifying specific improvement opportunities and connecting that evidence to applications.

**00:20–00:55 — Student and evidence.** Open an example student account. Show the GitHub evidence assessment (commit SHA, scoped observations, source citations, explicit limitations) and three improvement tasks. Show the resume preview.

**00:55–01:25 — Jobs and applications.** Browse explicitly marked sample opportunities, open a posting and show a synthetic application and its recorded status.

**01:25–01:50 — Employer and privacy.** Sign in as the fictional employer and inspect only authorized applicants. Briefly show the college officer's allowed view and a privacy-gated student record.

**01:50–02:10 — Admin and trust.** Show organization approvals and audit logs. Do not show password files or other secrets.

**02:10–02:30 — Evidence of impact.** Show only real test outputs and any genuinely collected pilot feedback. Clearly identify pending deployment, OAuth, analytics or ATS-validation work.

## Verification

The publication README lists the automated suite totals recorded at synchronization. Re-run the tests against the exact commit being submitted before quoting totals in the video:

```bash
php tests/sqlite/core_test.php
php tests/evidence/agent_test.php
php tests/organizations/organization_test.php
php tests/employment/employment_test.php
php tests/employment/resume_test.php
php tests/employment/company_workflow_test.php
php tests/employment/college_workflow_test.php
php tests/employment/e2e_demo_test.php

python3 tests/sqlite/http_smoke.py
python3 tests/employment/employment_http_smoke.py
python3 tests/employment/resume_http_smoke.py
python3 tests/employment/company_http_smoke.py
python3 tests/employment/college_http_smoke.py
```

Some HTTP tests require a suitable PHP binary on `PATH`; consult test script CLI options if it differs.

## Authentic screenshots

After the final theme/accessibility pass, capture real screens using **synthetic data only**, in a fixed browser viewport, and place them under `docs/screenshots/`. Suggested shots:

- `student-dashboard.png`
- `resume-preview.png`
- `job-discovery.png`
- `company-applications.png`
- `college-consent.png`
- `admin-overview.png`

Remove personal data, passwords, tokens, and session identifiers. Add actual image links to the main README **only when the files exist**.

## Honest limitations

- This is a localhost-only prototype, not yet a publicly deployed production service.
- The current GitHub evidence assessment is bounded, deterministic and explanation-oriented, not validated as a hiring prediction or fully autonomous AI decision-maker.
- ATS-friendly formatting does not guarantee acceptance by every applicant tracking system.
- Synthetic jobs, applicants, organizations and employment outcomes are not evidence of real-world impact.
- College-specific affiliation requests, revocable field-level permissions, OAuth login and privacy-preserving product analytics are planned for Mission 009.
