# EIDOLON PATHWAY

**Evidence to improvement. Improvement to opportunity.**

EIDOLON PATHWAY is an open-source, security-conscious career-readiness and internship platform evolving from **InternHunt**. It is being built for the **Code for a Billion — Bharat Agentic-AI Hackathon 2026**, with a focus on youth employment.

> **Repository status:** Private development snapshot. The version currently committed here contains the student portal and GitHub evidence assessment. Additional resume, employment, organization, and administration capabilities have been implemented and tested **locally** but are **not yet synchronized to this repository**. The sections below distinguish published functionality from the local development track. This README will be updated again when the verified integration is pushed.

## Why PATHWAY?

A student should be able to do more than display a list of skills. PATHWAY aims to connect **demonstrable project evidence → practical improvement tasks → suitable opportunities → applications → verifiable progress**. The goal is not to promise a job or invent a candidate score; it is to make preparation and decisions more transparent.

## Who is it for?

| User | Intended workflow | Availability in this repository |
| --- | --- | --- |
| **Student** | Create an account, assess public GitHub work, review evidence and improvement steps | **Included** |
| **Student** | Build a resume, complete a profile, browse jobs, apply and track applications | **Implemented locally; pending reviewed sync** |
| **Company** | Approved recruiter login, publish jobs, review applicants and update status | **Implemented locally; pending reviewed sync** |
| **College** | Authorized placement staff and consent-based student insights | **In progress; not a completed portal** |
| **Platform admin** | Create or approve organizations, invite staff and audit actions | **Implemented partially locally; pending reviewed sync and security gates** |

### GitHub evidence assessment (included)

- Accepts a **public** GitHub repository and a frontend, backend, or full-stack target role.
- Inspects a **bounded sample** of repository evidence; does not execute downloaded repository code.
- Pins evidence citations to a specific Git commit.
- Distinguishes observations, claims, and things **not observed in the inspected sample**. Absence of evidence is **not** proof of missing skills.
- Produces **three prioritized improvement tasks** with acceptance criteria.
- Stores assessment history under the owning student account.

This is currently a deterministic, explainable workflow. **Do not interpret it as an independently validated hiring predictor or a claim of LLM-powered decision making.**

## Quick start: version currently on GitHub

**Requirements:** PHP 8.4 CLI (PHP 8.2+ supported by the runtime check), PDO SQLite, PHP sessions, and command-line `curl` for GitHub assessment. No Docker, XAMPP, MySQL server, npm, or external frontend build is required.

```bash
git clone https://github.com/skandamp-sudo/eidolon-pathway.git
cd eidolon-pathway

php bin/init.php
php bin/serve.php 8080
```

Open **http://127.0.0.1:8080** on the **same computer**.

1. Select **Create an account**, using a demonstration name and an email/password you do not use elsewhere.
2. Sign in and open the **student dashboard**.
3. Assess a public repository such as **`php-fig/log`** with target role **Backend**.
4. Open the saved result, inspect the evidence provenance, and review the three improvement tasks.
5. Return to the dashboard to see your assessment history.

**Local only:** The current app deliberately rejects non-loopback requests and uses HTTP-only localhost settings. **Do not expose the PHP development server on the internet.** A public HTTPS deployment requires a separate security-reviewed production configuration.

### Local workspace versus published code

The original InternHunt development workspace contains additional, newer work:

- A separate organization model with permission checks and invitations.
- Student profile, job discovery, job applications and application history.
- Resume editing and print-friendly preview.
- Company and admin login and workflows.
- Automated unit, integration, HTTP, and demonstration tests.

These features were reported tested in the local development environment but **are not available from this repository checkout yet**. They must be synchronized, reviewed for secrets, regression-tested, and committed before the README can truthfully label them as publicly reproducible.

## Architecture

| Layer | Technology / approach |
| --- | --- |
| Server and routes | PHP 8.4, server-rendered HTML |
| Data | PDO SQLite, migrations with SHA-256 checksums |
| Frontend | HTML and CSS; no third-party runtime JavaScript for the published student slice |
| Evidence collection | Bounded read-only access to public GitHub resources |
| Evidence evaluation | Versioned deterministic rules with commit-pinned citations |
| Security baseline | Prepared statements, password hashing, session rotation, CSRF validation, output escaping, restrictive content security policy, request limits |
| Execution | Local-first; no Docker |

Key folders:

```text
app/                  Application logic, routes, migrations and views
app/evidence/         GitHub inspection and evidence evaluation
bin/init.php          Initialize or migrate the SQLite database
bin/serve.php         Start the loopback-only development server
public/               Public document root and static assets
tests/                Available automated checks
var/                  Local database, sessions and runtime (gitignored)
```

The legacy InternHunt MySQL/PHP project and its credentials/history are **not** included in this clean publishing repository.

## Tests

With the application initialized and a suitable PHP binary:

```bash
php tests/sqlite/core_test.php
php tests/evidence/agent_test.php
```

An HTTP smoke suite is also available at `tests/sqlite/http_smoke.py`; it starts a local server and requires Python 3. Actual test counts should be recorded for the **specific published commit**. Do not treat a successful test suite as a comprehensive penetration test or a security certification.

## Security and privacy

- **Never** commit `.env` files, credentials, a local SQLite database, generated sessions, private resumes or real applicant data.
- Use demonstration identities when testing; this snapshot does not provide verified email delivery or production-grade account recovery.
- Keep the `var/` directory outside the public document root.
- Source repository inspection is read-only and does **not** execute untrusted code.
- The production roadmap includes MFA for administrators, verified invitation delivery, independent authorization tests, HTTPS-only secure sessions, backups, monitoring and a formal release security review.
- **Do not use real student or applicant data in an unreviewed local/public demo.**

Security contact: please use the repository's private maintainer communication channel rather than disclosing suspected vulnerabilities in public issues.

## Judge walkthrough (published student slice)

1. **Problem:** student projects and career preparation lack traceable, actionable feedback.
2. **Input:** use a public GitHub repository (e.g. `php-fig/log`) and select a target developer role.
3. **Evidence:** review the inspected commit, findings, citations and explicit limitations.
4. **Action:** inspect the three prioritized tasks and their acceptance criteria.
5. **Persistence:** confirm that the assessment is accessible from the same student's history.

**Broader employment demo (pending repository sync):** profile → resume → approved company job → student application → employer review → status update. No actual placement or employment outcome is claimed. Demonstration colleges and vacancies, when seeded, must be unmistakably labeled synthetic and unaffiliated.

## Development tools and transparency

AgentFoundry was used in the hackathon development workflow. Other coding assistants, including Google Antigravity, were also used. Automated changes were reviewed through local tests and manual browser checks. Tool use and limitations will be described concretely in the final hackathon submission; no tool is claimed to provide security guarantees.

## What remains before a public release?

- Synchronize verified local resume, employment, organization and admin code into this clean repository.
- Finish the college portal and safe isolated demonstration records, including an **unaffiliated DSATM demo record** if used.
- Re-run tests against the exact publishing checkout and conduct independent security testing.
- Prepare an HTTPS deployment with appropriate cookie, host, authentication, data protection and operational controls.
- Capture screenshots or a walkthrough video, document real pilot feedback, and provide deployment/demo links.
- Change repository visibility only after secret scanning and release review.

## Project identity

**InternHunt** is the original foundation. **EIDOLON PATHWAY** is its evolution—not a replacement—and the GitHub evidence agent is one component of the larger employment platform.

**EIDOLON — Built on evidence. Earned through trust.**
