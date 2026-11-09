# Deployment decision — EIDOLON PATHWAY

## Current state

The application is a **standalone PHP + PDO SQLite, server-rendered** prototype. Its launcher and controller intentionally restrict access to `127.0.0.1`. PHP session files and the SQLite database rely on **persistent local storage**.

**Do not** deploy the local launcher as a public web service or remove loopback checks without a complete production-mode security review.

## What works with Vercel?

Vercel is suitable for an optional **landing page, product documentation, and future web frontend**. Its first-party Functions do not provide a native persistent PHP/SQLite hosting model. PHP support is available through an **unofficial community runtime**, which is not equivalent to first-party support. Vercel Functions use read-only deployment files and temporary scratch space; a bundled SQLite database cannot safely serve as persistent multi-user storage there.

References:
- [Vercel runtime documentation](https://vercel.com/docs/functions/runtimes)
- [Community PHP runtime and its maintenance warning](https://github.com/vercel-community/php)

**Recommended release architecture:**
1. Deploy the existing PHP application on a suitable persistent PHP host after a release security review; use trusted HTTPS ingress.
2. Keep a protected persistent data store (SQLite with durable volume for a tightly constrained pilot, or a carefully migrated server database for scale).
3. Optionally host an independent brochure/landing frontend on Vercel and link it to the real app domain.
4. Do **not** convert the backend to Next.js or a community runtime right before the deadline merely to obtain a Vercel URL.

## Production security blockers

Before external access, verify all of the following against the deployed system:

- HTTPS, trusted host/proxy configuration, HSTS and correct security headers
- `Secure`, `HttpOnly`, `SameSite` session cookies and proper session rotation/expiry
- Privileged MFA, secure bootstrap/recovery and revocable sessions
- Verified email ownership, controlled organizational invitations and anti-abuse limits
- Identity-provider OAuth/OIDC configuration, if social sign-in is enabled
- Server-side authorization, tenant isolation, privacy/consent withdrawal and audit integrity
- Persistent encrypted backups, restoration testing, incident logging and secret management
- No demo account, demo seeder or seeded administrator accessible from the public deployment
- Independent security review, checked dependency inventory and real HTTP end-to-end tests
- Privacy policy, consent notices, retention/deletion policy and a contact for security disclosures

No completed test matrix constitutes a guarantee of unhackability or formal ASVS certification.

## Deadline and submission

The Code for a Billion hackathon requests an **open-source repository and a project deployed on a cloud by 15 November 2026**; provide a 2–3-minute demo video or accessible link in the GitHub submission. Verify the exact submission instructions before sending the entry.

Do not confuse a Vercel marketing page with a fully deployed PATHWAY product. If only a video or a limited demo is ready, describe that limitation accurately.
