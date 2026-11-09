# EIDOLON PATHWAY

**Evidence to improvement. Improvement to opportunity.**

EIDOLON PATHWAY is an open-source, security-conscious career-readiness and internship platform evolving from **InternHunt**. It is being built for the **Code for a Billion — Bharat Agentic-AI Hackathon 2026**, with a focus on youth employment.

> **Repository status:** Release Candidate Synchronization (Mission 008). This repository contains the complete, standalone EIDOLON PATHWAY application: Student Portal with GitHub Evidence Assessment & ATS Resume Builder, Employer Hub with Applicant Pipeline & Shortlisting, College Placement Hub with Consent-First Privacy, Platform Administration Console, and isolated Demonstration Seeder (`bin/seed_demo.php`). All 363 automated test checks pass directly against this checkout with zero external service dependencies.

---

## Why PATHWAY?

A student should be able to do more than display an unverified list of keywords. PATHWAY connects:
$$\text{Project Evidence} \longrightarrow \text{Targeted Improvement Tasks} \longrightarrow \text{Matched Opportunities} \longrightarrow \text{Verifiable Applications}$$

The goal is not to promise a job or generate arbitrary automated candidate rankings; it is to make preparation, skills evidence, and hiring decisions transparent and accountable.

---

## Who is it for?

| User Persona | Workflow Capabilities | Availability in this Repository |
| :--- | :--- | :--- |
| **Student (Evidence)** | Submit public GitHub work, inspect commit-pinned evidence findings, receive 3 prioritized improvement tasks | **Included & Verified** |
| **Student (Employment)** | Build an ATS-friendly resume citing verified evidence, complete profile, set academic consent, browse jobs, apply & track status | **Included & Verified** |
| **Employer / Company** | Onboard through invitation, post job vacancies, review applicant resumes & evidence citations, update candidate status | **Included & Verified** |
| **College / University** | Authorized placement staff login, campus placement intelligence dashboard, and consent-guarded student records view | **Included & Verified** |
| **Platform Administrator**| Organization approval/suspension lifecycle, audit logging, multi-tenant RBAC enforcement | **Included & Verified** |

### GitHub Evidence Assessment
- Accepts a **public** GitHub repository and a frontend, backend, or full-stack target role.
- Inspects a **bounded sample** of repository evidence; does **not** execute downloaded repository code.
- Pins evidence citations to a specific Git commit SHA.
- Distinguishes observations, claims, and items **not observed in the inspected sample**. Absence of evidence in a sample is **not** proof of missing skills.
- Produces **three prioritized improvement tasks** with explicit acceptance criteria.
- Persists assessment history strictly under the owning student account.

*Note:* This is a deterministic, explainable rule-based evaluation workflow. It is **not** an independently validated psychometric hiring predictor and does not claim black-box LLM decision-making.

---

## Quick Start: Local Demonstration

**Requirements:**
- PHP 8.4 CLI (or PHP 8.2+ with PDO SQLite and session extensions enabled)
- SQLite 3
- Python 3 (only for running optional HTTP smoke test scripts)
- Command-line `curl` (only for live GitHub repository inspection)
- **Zero dependencies:** No Docker, No XAMPP, No MariaDB/MySQL Server, No npm, and no frontend build steps.

### 1. Initialize Clean Storage
```bash
git clone https://github.com/skandamp-sudo/eidolon-pathway.git
cd eidolon-pathway

# Initialize SQLite database (schema version 5)
php bin/init.php
```

### 2. Populate Isolated Demonstration Data
To safely explore all four roles with realistic records without entering real user data, generate an isolated demonstration database:

```bash
php bin/seed_demo.php --confirm-demo-seed --demo-db=var/pathway_demo.sqlite
```

> **Security Note:** The seeder generates cryptographically random, non-reusable demo passwords for each execution and saves them locally to `var/demo_credentials.json` with `0600` permissions (never committed to git). The seeder strictly refuses to run on the primary `var/pathway.sqlite` database.

### 3. Launch Local Server
```bash
# Launch server pointed at the isolated demonstration database
php bin/serve.php 8080 --demo

# Or launch against the clean, unseeded database:
# php bin/serve.php 8080
```

Open **http://127.0.0.1:8080** in your browser (Safari, Chrome, or Firefox).

> **Localhost Only:** The application strictly enforces loopback-only requests (`127.0.0.1` / `localhost`) and rejects external hosts. **Do not expose the PHP development server on the public internet.** A public production deployment requires HTTPS, reverse-proxy headers, and production cookie policies.

---

## Guided Demonstration Walkthrough

When `bin/seed_demo.php` runs, it outputs dynamic passwords to the terminal and stores them in `var/demo_credentials.json`. Below are the credentials and paths to explore each role:

| Persona | Portal Login | Email | Generated Password | Demonstration Focus |
| :--- | :--- | :--- | :--- | :--- |
| **Student (Consenting)** | `/login` | `demo.student.aarav@dsatm.test` | *(See terminal / `demo_credentials.json`)* | Evidence assessment, ATS resume with citations, shortlisted application |
| **Student (Confidential)** | `/login` | `demo.student.priya@dsatm.test` | *(See terminal / `demo_credentials.json`)* | Confidential profile, consent withheld from college |
| **Company Recruiter** | `/company/login` | `demo.recruiter@eidolon-labs.test` | *(See terminal / `demo_credentials.json`)* | Job vacancies, candidate applicant review, shortlisting |
| **College Placement Officer** | `/college/login` | `demo.placement@dsatm.test` | *(See terminal / `demo_credentials.json`)* | Placement KPI dashboard, consented vs confidential roster |
| **Platform Administrator** | `/admin/login` | `demo.admin@eidolon-pathway.test` | *(See terminal / `demo_credentials.json`)* | Organization approvals, suspensions, and immutable audit logs |

### Step-by-Step Flow

1. **Student Experience (`/login`)**:
   - Sign in as `demo.student.aarav@dsatm.test`.
   - On the student dashboard (`/dashboard`), notice the **Shortlisted** status for *Python Developer Intern* at EIDOLON Research Labs.
   - Open `/resume` to review the ATS resume citing GitHub commit `7fd1a60b...` and 3 prioritized tasks. Click **Print / ATS PDF** for a print-ready preview.
   - Open `/profile` to view the college affiliation and the **Placement Record Sharing Consent** checkbox.

2. **Employer Workflow (`/company/login`)**:
   - Sign in as `demo.recruiter@eidolon-labs.test`.
   - On `/company/dashboard`, view active job postings.
   - On `/company/applicants`, inspect Aarav Patel's application, view his cover note, and update applicant statuses (`applied`, `shortlisted`, `rejected`).

3. **College Placement Intelligence (`/college/login`)**:
   - Sign in as `demo.placement@dsatm.test`.
   - On `/college/dashboard`, examine placement metrics (Associated Students, Consented, Shortlisted, Consent Rate).
   - In the student roster:
     * **Aarav Patel** has an emerald `Consented` badge, visible email, and active application count. Click **View Records** to view full history.
     * **Priya Sharma** has a `Confidential` badge, email masked as `Consent Withheld`, and masked metrics. Attempting to access her details directly fails closed with HTTP 403.

4. **Platform Administration (`/admin/login`)**:
   - Sign in as `demo.admin@eidolon-pathway.test`.
   - On `/admin/dashboard`, inspect organization approval statuses and immutable audit events recording all administrative actions.

5. **Negative Security Verification**:
   - **Cross-Employer Tampering:** Competitor companies cannot view or alter rival candidate applications (returns HTTP 404).
   - **Cross-College Isolation:** Staff from other colleges cannot view DSATM student rosters (returns HTTP 403).
   - **Role Escalation:** Students attempting to open `/company/dashboard` or `/admin/dashboard` are rejected with HTTP 403.

---

## Synthetic Demonstration Entities & Disclaimers

To enable reproducible evaluation without using private personal data, the demo seeder uses synthetic records:

- **College:** `Dayananda Sagar Academy of Technology and Management (DSATM), Bengaluru — DEMO DATA`.
  > **Explicit Disclaimer:** This is synthetic demonstration data created solely for hackathon evaluation. It does not represent an actual partnership, endorsement, affiliation, or verified institutional deployment with DSATM.
- **Employers (3):** `EIDOLON Research Labs`, `Bangalore Software Studio`, and `NextGen Cloud Systems`.
  > **Explicit Disclaimer:** These are fictional demonstration organizations created for platform testing.
- **Opportunities (6):** All 6 job listings are explicitly marked with `[DEMO OPPORTUNITY - NOT A REAL VACANCY]`. They do not represent real employment offers.
- **Email Addresses:** All demonstration accounts use the `.test` top-level domain reserved by RFC 2606 for testing.

---

## Architecture & Schema Migrations

```text
app/
  bootstrap.php             Runtime validation, SQLite connection (0600 mode), schema verification
  http.php                  Central HTTP router, security headers, localhost enforcement, CSRF
  router.php                Built-in PHP web server dispatcher
  evidence/
    agent.php               GitHub evidence assessment engine & state machine
    github.php              Bounded GitHub API client with rate budgeting (40 req/window)
    rubric.php              Deterministic rubric scoring rules
  employment/
    jobs.php                Profile builder, job listings, application processing, ATS resume
  organizations/
    organization.php        Multi-tenant RBAC, invitations, approval lifecycle, audit logging
  migrations/
    001_student.sql         Users & student profiles baseline
    002_evidence.sql        Evidence assessments, improvement tasks, request budget
    003_organizations.sql   Organizations, memberships, invitations, audit logs
    004_employment.sql      Jobs, applications with duplicate constraints, resumes
    005_college.sql         College role expansion & student placement sharing consent
  views/
    page.php                WCAG-conscious semantic HTML views with dark midnight theme
bin/
  init.php                  Initializes or verifies SQLite schema
  serve.php                 Launches localhost-only PHP development server (supports --demo)
  seed_demo.php             Idempotent, isolated demonstration data seeder
  bootstrap_admin.php       CLI-only administrative user creation
  demo_walkthrough.php      Command-line end-to-end multi-role verification runner
public/
  index.php                 Public document root entry point
  assets/pathway.css        Zero-dependency, accessible responsive CSS system
tests/
  sqlite/                   Core database, session, and HTTP smoke tests
  evidence/                 Evidence agent, commit pinning, and rubric tests
  organizations/            RBAC, invitation token hashing, and audit log tests
  employment/               Resume IDOR, job applications, company/college workflows
```

---

## Automated Verification

All automated tests run against disposable, isolated SQLite databases:

```bash
# Run all PHP unit test suites
for t in tests/*_test.php tests/**/*_test.php; do
    php "$t"
done

# Run Python HTTP smoke test suites (requires Python 3 and php on PATH)
python3 tests/sqlite/http_smoke.py --php $(which php)
python3 tests/employment/employment_http_smoke.py --php $(which php)
python3 tests/employment/resume_http_smoke.py --php $(which php)
python3 tests/employment/company_http_smoke.py --php $(which php)
python3 tests/employment/college_http_smoke.py --php $(which php)
```

### Verified Test Matrix (Exact Published Commit)

| Suite File | Category | Passed | Failed | Status |
| :--- | :--- | :--- | :--- | :--- |
| `tests/sqlite/core_test.php` | PHP Unit | 35 | 0 | **PASS** |
| `tests/evidence/agent_test.php` | PHP Unit | 58 | 0 | **PASS** |
| `tests/organizations/organization_test.php` | PHP Unit | 26 | 0 | **PASS** |
| `tests/employment/employment_test.php` | PHP Unit | 43 | 0 | **PASS** |
| `tests/employment/resume_test.php` | PHP Unit | 14 | 0 | **PASS** |
| `tests/employment/company_workflow_test.php` | PHP Unit | 17 | 0 | **PASS** |
| `tests/employment/college_workflow_test.php` | PHP Unit | 14 | 0 | **PASS** |
| `tests/employment/e2e_demo_test.php` | PHP Unit | 15 | 0 | **PASS** |
| **PHP Unit Subtotal** | | **222** | **0** | **100% PASS** |
| `tests/sqlite/http_smoke.py` | HTTP Smoke | 53 | 0 | **PASS** |
| `tests/employment/employment_http_smoke.py` | HTTP Smoke | 17 | 0 | **PASS** |
| `tests/employment/resume_http_smoke.py` | HTTP Smoke | 18 | 0 | **PASS** |
| `tests/employment/company_http_smoke.py` | HTTP Smoke | 32 | 0 | **PASS** |
| `tests/employment/college_http_smoke.py` | HTTP Smoke | 21 | 0 | **PASS** |
| **Python HTTP Smoke Subtotal** | | **141** | **0** | **100% PASS** |
| **GRAND TOTAL VERIFICATION** | | **363** | **0** | **100% PASS** |

*Disclaimer:* Automated test suites confirm that implemented security controls behave as specified; they do **not** constitute a formal third-party penetration test or an absolute security guarantee.

---

## Security & Privacy Controls

- **Database Protection:** Stored in `var/` outside the web root with `0600` permissions. SQLite foreign key enforcement is verified on every connection.
- **SQL Injection Defense:** 100% of queries use PDO prepared statements with parameterized inputs. Dynamic SQL string concatenation is forbidden.
- **Cross-Site Scripting (XSS):** All dynamic view outputs are escaped using UTF-8 `htmlspecialchars()`. The Content Security Policy disables inline scripts (`script-src 'none'`) and disallows object embeds.
- **Cross-Site Request Forgery (CSRF):** 64-character cryptographic tokens are generated per session and verified on every state-altering POST request.
- **Session Security:** Native PHP sessions with `HttpOnly`, `SameSite=Strict`, session ID regeneration upon privilege changes, and strict database role validation on each request.
- **Multi-Tenant Isolation:** Resource queries enforce tenant ownership (`WHERE organization_id = ?`). Attempting to view or mutate another tenant's records fails closed.
- **No Plaintext Passwords:** Password hashing uses `password_hash()` with native default bcrypt/Argon2. Generated demonstration credentials are saved locally in `var/demo_credentials.json` (`0600` permissions) and are gitignored.

---

## Development Tools & Transparency

EIDOLON PATHWAY's evolution from InternHunt used the **AgentFoundry** workflow with coding assistants including Codex and Google DeepMind's **Antigravity**. All architecture decisions, schema migrations, and code changes were evaluated through automated test matrices, boundary-value tests, and manual verification in Safari and Chrome.

---

## Remaining Limitations Before Public Deployment

This repository represents a tested local prototype release candidate. Before deploying on the public internet, the following items are required:
1. **Production Transport:** Enforcement of HTTPS with HTTP Strict Transport Security (HSTS) and Secure cookie flags.
2. **Email Infrastructure:** Integration of a transactional email provider (e.g. Amazon SES, Postmark) for verified invitation and account recovery delivery.
3. **Multi-Factor Authentication (MFA):** Implementation of TOTP or WebAuthn for administrator and recruiter accounts.
4. **Third-Party Security Audit:** Formal independent penetration testing and vulnerability disclosure program.
5. **Operational Backups:** Automated WAL-mode SQLite snapshotting and disaster recovery procedures.

---

## Project Identity

**InternHunt** is the original foundation. **EIDOLON PATHWAY** is its security-first, multi-role evolution—integrating verifiable GitHub evidence analysis, ATS resumes, employer pipelines, and consent-guarded campus placement into a unified, lightweight platform.

**EIDOLON — Built on evidence. Earned through trust.**
