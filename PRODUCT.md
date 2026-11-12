# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

**Students** (primary): Final-year engineering and professional-degree students preparing for campus placements. They build ATS-optimized resumes, manage privacy consent over their academic data, track applications, and present GitHub-verified technical evidence to recruiters — all within their college's institutional placement workflow.

**Companies/Recruiters**: Employers who post internship and full-time positions through the platform, review applicant profiles with verified evidence, manage application pipelines, and update candidate statuses.

**College Placement Officers**: Institutional administrators who affiliate students, manage consent-restricted access to student data, approve organizational requests, and oversee the placement pipeline for their institution.

**Platform Administrators**: Manage organization registration requests, approve or reject companies and colleges, and oversee system-wide operations.

## Product Purpose

EIDOLON PATHWAY is an evidence-to-employment career platform for institutional campus placements. It replaces the fragmented process of resume PDFs, spreadsheet tracking, and unverified skill claims with a single system where students build verified professional profiles, colleges manage compliant data sharing, and companies evaluate candidates through authenticated evidence rather than self-reported claims.

Success means: students get placed through a transparent, privacy-respecting process; colleges maintain institutional oversight; companies receive verified, consent-gated candidate data.

## Positioning

The evidence-verified placement pipeline: GitHub contributions are authenticated and scored by a built-in analysis agent, producing quantified technical evidence that a traditional resume or job board cannot provide. Combined with institutional affiliation, privacy-first consent management, and ATS-format resume generation, PATHWAY is the only platform that connects verifiable student work to employer decisions within the college placement framework.

## Operating Context

Used during college placement seasons (typically 6th–8th semester). Students log in from personal devices (laptops, phones). Placement officers operate from campus systems. Companies access from corporate environments. All roles interact through a web browser — no native app. The platform runs as a self-hosted PHP application with SQLite, deployable on a single server without external dependencies.

## Capabilities and Constraints

**Confirmed capabilities:**
- Four-role portal system (student, company, college, admin) with isolated authentication
- ATS-optimized resume builder with real-time preview and PDF generation
- GitHub Evidence Agent: automated repository analysis producing contribution scores
- Privacy & consent management: granular data-sharing controls per student
- Job posting and application pipeline with status tracking
- College affiliation and organizational approval workflows
- Dark/light/system theme support
- CSRF protection, CSP headers, tenant isolation

**Technical constraints:**
- PHP 8.4 + SQLite — no framework, no ORM, no external runtime dependencies
- Single monolithic template file (`app/views/page.php`, ~3748 lines)
- CSP `script-src 'self'` — no inline scripts permitted
- All JavaScript must be in external files referenced by `data-*` attributes
- Demo mode with isolated database for testing

**Undecided:**
- Production deployment target and hosting strategy
- Whether to decompose the monolithic template into partials
- Public deployment security gate (currently development-only)

## Brand Commitments

- **Name**: EIDOLON PATHWAY (evolved from InternHunt)
- **Voice**: Professional, institutional, trustworthy — not startup-casual or gamified
- **Identity constraints**: The EIDOLON brand identity must be maintained. No playful, gamified, or consumer-social aesthetics.
- **Color direction (user-specified)**: Sophisticated midnight-navy and emerald dark theme; elegant off-white light theme
- **Privacy-first**: Consent management and data protection are core product values, not afterthoughts

## Evidence on Hand

- Working four-role application with demo data and credentials
- 12 existing screenshots (docs/images/) showing current light/dark states
- 619 passing automated checks (286 PHP + 333 Python)
- GitHub repository analysis agent producing real contribution scores
- ATS resume templates with live preview
- No real testimonials, customer logos, or case studies exist — these must not be fabricated

## Product Principles

1. **Evidence over claims**: Every skill assertion should be backed by verifiable work, not self-reported checkboxes.
2. **Institutional trust**: The platform operates within college placement frameworks, not as a consumer marketplace.
3. **Privacy as a feature**: Students control what data is shared, with whom, and when — consent is granular and auditable.
4. **Operational transparency**: Every role sees exactly what they need: students see their pipeline, companies see consented data, colleges see compliant oversight.
5. **Accessible by default**: The platform must work for all users regardless of ability, device, or network conditions.

## Accessibility & Inclusion

WCAG 2.1 AA compliance is the target. The platform must support keyboard navigation, screen readers, sufficient color contrast in both themes, and responsive layouts from mobile (375px) through desktop. No motion-dependent interactions.
