# EIDOLON PATHWAY

**Evidence to improvement. Improvement to opportunity.**

EIDOLON PATHWAY is an open-source, security-first career-readiness, verified skills, and institutional placement prototype evolved from **InternHunt**. It is architected as a self-contained, reproducible software prototype for the **Code for a Billion — Bharat Agentic-AI Hackathon 2026**, directly addressing youth employability, institutional friction, and credential integrity.

> **Project Status:** Mission 009.1 — Complete Hotfix Release: Authentication, OpenID Connect & Content Security Policy (CSP). Contains the complete standalone EIDOLON PATHWAY application with 4 distinct role portals, self-service institutional onboarding, unified governance administration, deterministic GitHub portfolio evidence assessment, ATS resume intelligence with keyword alignment, granular student privacy sovereignty, adaptive 3-theme design with zero FOUC, and standards-compliant OAuth 2.0 / OpenID Connect federation. **722 automated tests pass directly against this checkout with zero external server dependencies.**
>
> **Prototype Notice:** EIDOLON PATHWAY is a locally tested prototype and reference architecture for hackathon evaluation. Demonstration colleges (e.g. DSATM), employer organizations, vacancies, and candidate profiles are entirely synthetic test fixtures for reproducible demonstration. The portfolio assessment evaluates observable repository structures and practices deterministically; it is an educational feedback and evidence-structuring tool, not an autonomous hiring decision-maker.

---

## 1. Why PATHWAY? The Employability Crisis & Research Foundation

In youth hiring, conventional platforms suffer from three systemic failures:
1. **Unverified Claims & Resume Fluff:** Candidates list laundry lists of skills ("Full Stack, Python, Kubernetes, Microservices") without verifiable provenance. Recruiters waste thousands of hours sifting through inflated resumes.
2. **Generic LLM Hallucinations:** Generative AI tools provide generic, ungrounded career advice ("use strong action verbs", "quantify impact") without inspecting actual code or providing verifiable proof.
3. **Institutional Disconnect & Privacy Invasions:** Colleges lack real-time visibility into student career preparation, while existing portals routinely violate student sovereignty by broadcasting private contact details and academic records to third parties without consent.

### The PATHWAY Closed-Loop Architecture

EIDOLON PATHWAY introduces a mathematically grounded, verifiable closing loop:

$$\text{Public Code Evidence (Commit SHA)} \longrightarrow \text{Deterministic Rubric AST} \longrightarrow \text{Targeted Improvement Roadmap} \longrightarrow \text{ATS Verified Resume} \longrightarrow \text{Institutional Consent Gate} \longrightarrow \text{Recruiter Candidate Dossier}$$

```
   ┌───────────────────────────────────────────────────────────────┐
   │                  STUDENT SOVEREIGNTY PLATFORM                 │
   └───────────────────────────────┬───────────────────────────────┘
                                   │
              ┌────────────────────┴────────────────────┐
              ▼                                         ▼
   ┌───────────────────────┐                 ┌───────────────────────┐
   │ GitHub Portfolio AST  │                 │  ATS Resume Builder   │
   │ Bounded Commit Pinned │                 │  Keyword Benchmarks   │
   │ Deterministic Rubric  │                 │  Evidence Citations   │
   └──────────┬────────────┘                 └──────────┬────────────┘
              │                                         │
              └────────────────────┬────────────────────┘
                                   ▼
   ┌───────────────────────────────────────────────────────────────┐
   │              GRANULAR PRIVACY & CONSENT GATEWAY               │
   │   Master Placement Gate · Masked Emails · Immutable Audit     │
   └───────────────────┬───────────────────────┬───────────────────┘
                       │                       │
                       ▼                       ▼
           ┌───────────────────────┐ ┌───────────────────┐
           │ College Placement Hub │ │ Employer Hub      │
           │ Cohort Analytics      │ │ Verified Vacancies│
           │ Affiliation Queue     │ │ Candidate Dossier │
           └───────────────────────┘ └───────────────────┘
```

---

## 2. Comparative Research Evaluation: Deterministic Evidence vs. Generic Advice

EIDOLON PATHWAY was systematically benchmarked against standard industry approaches:

| Evaluation Dimension | Traditional Portals / Generic LLM Advice | EIDOLON PATHWAY Deterministic Architecture |
| :--- | :--- | :--- |
| **Code Verification** | Zero verification; relies on unvetted student self-attestations or external resume URLs. | **Commit-pinned GitHub inspection**; checks branch tips, pins immutable commit SHA, enforces 40-request limits, and scans bounded trees with strict SSRF protection. |
| **Feedback Repeatability** | Stochastic LLM generation; running twice yields contradictory recommendations and hallucinated scores. | **Deterministic AST & Pattern Rubric**; identical commit input produces identical 0–100 category scores and exact citations across runs. |
| **Improvement Roadmaps** | Vague suggestions ("work on scalable architecture", "learn Docker"). | **Exactly three prioritized improvement tasks** derived directly from unobserved rubric findings with file paths, concrete acceptance criteria, and step-by-step guidance. |
| **ATS Scoring & Match** | Black-box percentage score with no explainable breakdown. | **Explainable 6-category weighted score (0–100)**: Contact info, Professional Summary, Structured Education, Action Verbs, Technical Skills, and Verified GitHub Evidence citation. |
| **Student Privacy** | PII broadcasted to campus recruiters by default without opt-out mechanisms. | **Deny-by-default granular consent**: Master college placement toggle, field-level masks (`[Consent Withheld]`), and explicit opt-in for talent discovery. |
| **Access Transparency** | Zero visibility into who looked at student records. | **Immutable Sensitive Record Access Log**: records timestamp, organization, staff identity, and access outcome for every query. |
| **Social Sign-In Safety** | Leaked client credentials, fake UI buttons, or naive auto-linking by email alone. | **RFC 7636 PKCE S256 with 256-bit CSPRNG states**, unconfigured fail-closed UI, anti-account-takeover protection, and locked initial passwords. |

---

## 3. Platform Role Portals

| Persona | Portal Endpoint | Core Capabilities |
| :--- | :--- | :--- |
| **Student** | `/login` / `/dashboard` | GitHub evidence assessment, 3 prioritized improvement tasks, ATS resume builder, vacancy search, application tracking, achievements credentials, college affiliation, granular privacy controls, and federated social sign-in. |
| **Employer / Recruiter** | `/company/login` | Company profile management, job posting, applicant pipeline management, candidate dossiers with verified evidence citations, candidate shortlisting, and talent discovery directory. |
| **College Placement Officer** | `/college/login` | Institutional cohort intelligence, associated student rosters, consented student record inspection, affiliation request review queue, and unaffiliation management. |
| **Platform Administrator** | `/admin/login` / `/admin/dashboard` | **EIDOLON CONTROL Unified Center**: 8 governance tabs (Overview, Access Requests review queue, Organizations management, User Directory search, Staff Invitations & Revocation, Access Reviews audit, Audit Trail, and Security Posture). |

---

## 4. Security Architecture & Threat Model

Security is the foundational design principle of EIDOLON PATHWAY:

1. **Zero External Runtime Dependencies:** Runs on standalone PHP 8.4 CLI with PDO SQLite. No Apache/Nginx, No Docker, No XAMPP, and No external database servers required.
2. **Localhost & Loopback Isolation:** Strictly enforces `127.0.0.1` / `localhost` loopback requests (`HTTP_HOST` / `REMOTE_ADDR`), immediately rejecting foreign network hosts.
3. **Role-Based Access Control & Tenant Isolation (RBAC):** Deny-by-default access policies across all four roles. Session roles and organization memberships are verified against the database on every single request.
4. **Strict Content-Security-Policy (CSP):** Emits `script-src 'self'` without `'unsafe-inline'` or `'unsafe-eval'`. External first-party script `/assets/theme.js` powers the theme controller synchronously with zero flash of unstyled content (FOUC). Headless Chrome actively verifies zero CSP violations and blocking of inline scripts.
5. **Single-Use Hashed Invitations:** Staff onboarding uses 256-bit entropy raw tokens stored as SHA-256 hashes with 7-day expiration and instant one-click revocation.
6. **OpenID Connect & OAuth Security:** 
   - Cryptographic RS256 signature verification of OIDC ID-tokens (Google, LinkedIn) against provider JWKS using standard X.509 SubjectPublicKeyInfo conversions.
   - Strict issuer, audience, nonce, and expiration checking.
   - Browser session-bound state validation to block Login CSRF attacks.
   - Strict primary and verified email requirement for GitHub identity.
   - Role escalation prevention: self-registration strictly assigns `'student'` role.
   - Anti-account-takeover: linking an existing password-authenticated account requires explicit credential authentication.
7. **Ephemeral Demo Storage:** The seeder generates cryptographically random passwords per execution, storing them strictly in local `0600`-mode `var/demo_credentials.json` (strictly ignored by Git).
8. **Zero-FOUC Accessible Design:** Pure CSS dark, light, and system themes with instant head execution preventing visual flash, full WCAG AA contrast compliance, and skip links.

---

## 5. Quick Start: Reproducible Local Run

### Prerequisites
- PHP 8.4 CLI (or PHP 8.2+ with PDO SQLite, session, filter, and ctype extensions enabled)
- SQLite 3
- Python 3 (standard library only, for executing automated HTTP smoke test suites)
- Command-line `curl` (for optional live GitHub repository assessments)
- Google Chrome (optional, for headless browser CSP and DOM verification)

### 1. Initialize Clean Database (Schema Version 8)
```bash
git clone https://github.com/skandamp-sudo/eidolon-pathway.git
cd eidolon-pathway

# Initialize SQLite database outside public document root
php bin/init.php
```

### 2. Populate Isolated Demonstration Data
Generate an isolated demonstration database with synthetic student, college, and employer records:

```bash
php bin/seed_demo.php --confirm-demo-seed --demo-db=var/pathway_demo.sqlite
```

> The seeder outputs unique random passwords to your terminal and saves them locally to `var/demo_credentials.json` (`-rw-------`).

### 3. Launch Local Development Server
```bash
# Launch server pointed at the isolated demonstration database
php bin/serve.php 8080 --demo

# Or launch against a clean, unseeded database:
# php bin/serve.php 8080
```

Open **http://127.0.0.1:8080** in any modern web browser (Safari, Chrome, Firefox).

---

## 6. Guided Demonstration Walkthrough

Use the dynamic credentials printed by `bin/seed_demo.php` (stored in `var/demo_credentials.json`):

| Persona | Login Route | Email | Key Features to Inspect |
| :--- | :--- | :--- | :--- |
| **Student (Aarav)** | `/login` | `demo.student.aarav@dsatm.test` | Evidence assessment report, ATS resume builder with keyword alignment, Shortlisted application tracking. |
| **Student (Priya)** | `/login` | `demo.student.priya@dsatm.test` | Confidential profile with college sharing withheld; granular privacy toggles and sensitive access log. |
| **Company Recruiter** | `/company/login` | `demo.recruiter@eidolon-labs.test` | Manage job postings, review applicant pipeline, inspect candidate dossier citing verified GitHub evidence. |
| **College Placement Officer** | `/college/login` | `demo.placement@dsatm.test` | Cohort placement KPI cards, consented vs confidential student roster, student affiliation review queue. |
| **Platform Administrator** | `/admin/login` | `demo.admin@eidolon-pathway.test` | **EIDOLON CONTROL Center**: Access Requests queue, Organizations management, Staff invitation revocation, and security posture. |

---

## 7. Automated Test Suite (722 Automated Checks)

EIDOLON PATHWAY maintains a 100% test pass rate across 13 PHP unit test suites and 10 Python HTTP smoke test suites:

### Running All PHP Unit Tests (395 Checks)
```bash
php tests/sqlite/core_test.php
php tests/evidence/agent_test.php
php tests/organizations/organization_test.php
php tests/employment/employment_test.php
php tests/employment/resume_test.php
php tests/employment/college_workflow_test.php
php tests/employment/company_workflow_test.php
php tests/employment/e2e_demo_test.php
php tests/organizations/affiliation_workflow_test.php
php tests/organizations/org_request_workflow_test.php
php tests/employment/resume_intelligence_test.php
php tests/auth/oauth_workflow_test.php
php tests/application_management_test.php
```

### Running All Python HTTP Smoke Tests (327 Checks)
```bash
python3 tests/sqlite/http_smoke.py --php ./var/runtime/bin/php
python3 tests/employment/employment_http_smoke.py
python3 tests/employment/resume_http_smoke.py
python3 tests/employment/company_http_smoke.py
python3 tests/employment/college_http_smoke.py
python3 tests/employment/affiliation_http_smoke.py
python3 tests/organizations/org_request_http_smoke.py
python3 tests/employment/theme_and_intelligence_http_smoke.py
python3 tests/auth/oauth_http_smoke.py
python3 tests/csp/browser_csp_test.py
```

### Comprehensive Verification Matrix

```
=======================================================================================================
Test Suite File                                  Category        Checks Passed   Failed  Status
=======================================================================================================
tests/sqlite/core_test.php                       PHP Unit        35              0       100% PASS
tests/evidence/agent_test.php                    PHP Unit        58              0       100% PASS
tests/organizations/organization_test.php        PHP Unit        24              0       100% PASS
tests/employment/employment_test.php             PHP Unit        23              0       100% PASS
tests/employment/resume_test.php                 PHP Unit        16              0       100% PASS
tests/employment/college_workflow_test.php       PHP Unit        16              0       100% PASS
tests/employment/company_workflow_test.php       PHP Unit        17              0       100% PASS
tests/employment/e2e_demo_test.php               PHP Unit        12              0       100% PASS
tests/organizations/affiliation_workflow_test.php PHP Unit       22              0       100% PASS
tests/organizations/org_request_workflow_test.php PHP Unit       19              0       100% PASS
tests/employment/resume_intelligence_test.php    PHP Unit        26              0       100% PASS
tests/auth/oauth_workflow_test.php               PHP Unit        33              0       100% PASS
tests/application_management_test.php            PHP Unit        94              0       100% PASS
-------------------------------------------------------------------------------------------------------
PHP UNIT TEST SUBTOTAL                                           395             0       100% PASS
-------------------------------------------------------------------------------------------------------
tests/sqlite/http_smoke.py                       HTTP Smoke      53              0       100% PASS
tests/employment/employment_http_smoke.py        HTTP Smoke      17              0       100% PASS
tests/employment/resume_http_smoke.py            HTTP Smoke      18              0       100% PASS
tests/employment/company_http_smoke.py           HTTP Smoke      32              0       100% PASS
tests/employment/college_http_smoke.py           HTTP Smoke      21              0       100% PASS
tests/employment/affiliation_http_smoke.py       HTTP Smoke      35              0       100% PASS
tests/organizations/org_request_http_smoke.py    HTTP Smoke      45              0       100% PASS
tests/employment/theme_and_intelligence_http_smoke.py HTTP Smoke 36              0       100% PASS
tests/auth/oauth_http_smoke.py                   HTTP Smoke      40              0       100% PASS
tests/csp/browser_csp_test.py                    HTTP/Browser    30              0       100% PASS
-------------------------------------------------------------------------------------------------------
PYTHON HTTP SMOKE & BROWSER CSP SUBTOTAL                         327             0       100% PASS
=======================================================================================================
TOTAL AUTOMATED TEST BASELINE                                    722             0       100% PASS
=======================================================================================================
```

---

## 8. License & Acknowledgments

Built for the **Code for a Billion — Bharat Agentic-AI Hackathon 2026**.
Developed with an uncompromising commitment to student data sovereignty, deterministic skills assessment, and high-assurance multi-tenant security.
Licensed under the Apache License, Version 2.0.
