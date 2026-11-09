# Application-management regression tests

Run from the Desktop InternHunt development directory:

```sh
/Applications/XAMPP/xamppfiles/bin/php -n tests/application_management_test.php
```

Requires PHP with mysqli, PDO SQLite, sessions and `proc_open` available. No
packages, server or database credentials are needed. The local XAMPP PHP binary
provides these extensions even with `-n` (php.ini disabled).

The suite runs the actual endpoints in CLI subprocesses, using a private temporary
copy with a fixture `config.php`. It never reads or loads the real `config.php`.
Prepared SQL executes against SQLite in-memory fixtures through a small mysqli
adapter. Session files and copied test artifacts are removed after the run.

Covered behavior: authentication, company role, tenant-scoped listing and writes,
method and ID validation, malicious inputs, session-bound CSRF, escaped applicant
content, redirects, allowed transitions, replay of an already-applied update,
unknown state rejection, simulated concurrent status/ownership/role changes, and
database failures. Fixture names and addresses are synthetic; tokens are generated
at runtime and never printed.

Successful actions retain the 302 redirect to `view_applicants.php`. Rejections use
401 (no valid identity), 403 (wrong role/CSRF), 400 (invalid fields), 404 (missing or
foreign application), 405 (method), 409 (unknown/concurrently changed state), or
503 (database failure). The page remains company-only; administrator sessions do
not bypass this boundary.

The allowed target states are `applied`, `shortlisted` and `rejected`. Any known
current state can move to these targets, preserving accept, reject and return to
pending actions. Repeating the current state succeeds without writing. Legacy
`pending` rows are recognized; a submitted `pending` target aliases to `applied`,
matching `Database/schema.sql`. No schema changes are performed.

Limitations: SQLite is not MySQL. MySQL enum/SQL-mode behavior, real concurrent
connections, HTTP headers/cookies and browser operation still require separate
integration verification. Check the deployed status enum before using the reset
to pending action: a database that only supports `pending`, not `applied`, differs
from the checked-in schema. Other endpoints and password hashing are outside this
batch. Do not deploy the entire application based on this suite alone.
