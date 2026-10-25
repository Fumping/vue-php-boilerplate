# Upgrade an existing app from boilerplate 0.1.0 to v1.0.0

**Audience:** an AI coding agent working in an app that was created from
[`Fumping/vue-php-boilerplate`](https://github.com/Fumping/vue-php-boilerplate)
with `scripts/create-app.sh`. Such an app is a copy without the boilerplate's
git history. It is already in use and has its own code on top of the
boilerplate.

**Goal:** bring the app's login security and dependency lockfile to the
boilerplate `v1.0.0` level without changing how the app looks or behaves. The
stack stays the same (Tailwind 3, vue-router 4, Vitest 4). Moving to Tailwind
4, vue-router 5 and Vitest 5 is a separate step: see `UPGRADE-v2.md`.

What changed between 0.1.0 and v1.0.0:

| Area | 0.1.0 | v1.0.0 |
|---|---|---|
| Login rate limiting | One bucket per IP+username, 5 attempts | Three buckets: IP+username (5), IP (30), username (50) |
| Attempt counting | Read, then write (racy under parallel requests) | One conditional `UPDATE` per bucket (atomic) |
| Successful login | Deletes the IP+username row | Deletes IP+username, refunds one attempt to the IP and username buckets |
| Old attempt rows | Never removed | Pruned on every login attempt |
| Unknown username | `password_hash('dummy…')` on every request (timing leak, extra CPU) | `password_verify()` against a fixed dummy hash at the runtime's default bcrypt cost |
| `package-lock.json` | Critical and high `npm audit` advisories | `npm audit fix` within the existing ranges; 3 moderate remain (Tailwind 3, fixed by v2) |
| Database schema | `auth_login_attempts.ip` is 64 characters | Unchanged; the column now holds a sha256 bucket key |

Reference source code: tag `v1.0.0` of the boilerplate (public repository).
The relevant commits there are `88e9ff6` (rate limiting and timing),
`40bd0b1` (dummy-hash cost) and `a7f15cd` (audit fixes).

---

## Rules for the agent

1. **Work on a new branch**, for example `fix/upgrade-boilerplate-v1`. Never on
   `main`. Do not push or merge unless the user asks.
2. **Make one commit per phase** (below), so each phase can be reverted on its
   own.
3. **Preserve the app's own code.** This is an upgrade, not a reset. Never
   replace a file wholesale with the boilerplate version. Port the specific
   change and keep the project's customizations.
4. **Never touch** `api/config.php`, `api/storage/`, `.env*`, a production
   database or deployment settings. Do not run anything against a live server.
5. **Do not install, add or remove packages.** Phase 2 only refreshes the
   lockfile within the ranges already in `package.json`.
6. **Stop and ask the user** in any of the situations marked **STOP** below. Do
   not guess.
7. Run each verification step and report the real output. If something fails,
   fix it or stop. Never skip a failing check.

---

## Phase 0: Preflight and inventory (no changes)

1. **Clean tree.** `git status` must be clean. Otherwise **STOP**.
2. **Baseline.** Run and record the results:
   ```bash
   npm ci
   npx vitest --run
   npm run build
   php tests/api/auth.spec.php                # if present
   php tests/api/production-schema.spec.php   # if present
   npm audit
   ```
   If the baseline already fails (apart from `npm audit` advisories), **STOP**
   and report it. Do not mix existing failures with upgrade changes.
3. **Is the upgrade needed?** Check `api/helpers/auth.php`:

   | Check | Present when the file contains |
   |---|---|
   | Three-bucket rate limiting | `function loginRateLimitBuckets` |
   | Atomic attempt counting | `function consumeLoginAttempt` |
   | Pruning of expired attempt rows | `function pruneLoginAttempts` |
   | Constant-cost login for unknown usernames | `function loginDummyPasswordHash` |

   - **All four present:** skip phase 1. Go to phase 2.
   - **Some present:** port only the missing parts in phase 1.
   - **No `api/helpers/auth.php` at all** (app predates authentication):
     **STOP**. Adding authentication is a feature, not an upgrade, so ask the
     user.
4. **Inventory.** Record these, because phase 1 depends on them:
   - Every caller of the functions that phase 1 removes:
     ```bash
     grep -rn "recordFailedLogin\|clearFailedLogins\|loginRateLimitKey\|assertLoginAllowed" api tests
     ```
     In 0.1.0 these are only used in `api/handlers/auth-login.php` and
     `tests/api/auth.spec.php`. If the app calls them anywhere else (a second
     login endpoint, an API-token login, an admin "unblock" action), list those
     places.
   - Project-specific changes in `api/handlers/auth-login.php`, such as extra
     fields in the response, audit logging, 2FA or "remember me".
   - The `auth_login_attempts` table in `db/schema.sqlite.sql` and
     `db/schema.mysql.sql`. The `ip` column must hold at least 64 characters
     (`VARCHAR(64)` / `CHAR(64)`). If the project shortened it or added
     columns, **STOP**.
   - Whether the app changed the old `LOGIN_MAX_ATTEMPTS`, `LOGIN_WINDOW_SECONDS`
     or `LOGIN_BLOCK_SECONDS` values. Keep custom window and block times; ask
     the user how a custom `LOGIN_MAX_ATTEMPTS` should map onto the new
     per-IP+username limit.
   - Which PHP versions are available locally (`php -v`, and `php8.2`/`php8.3`
     if installed). Production PHP version, if the README or notes mention it.

---

## Phase 1: Login rate limiting and timing hardening

1. **Get the reference:**
   ```bash
   git clone --depth 1 --branch v1.0.0 https://github.com/Fumping/vue-php-boilerplate.git ../boilerplate-v1
   diff -u ../boilerplate-v1/api/helpers/auth.php api/helpers/auth.php
   diff -u ../boilerplate-v1/api/handlers/auth-login.php api/handlers/auth-login.php
   ```
   The reference uses English messages. If the app's messages are in another
   language, keep the app's language.

2. **Constants** at the top of `api/helpers/auth.php`. Replace
   `LOGIN_MAX_ATTEMPTS` with the three per-bucket limits and add the dummy hash
   body. Keep `LOGIN_WINDOW_SECONDS` and `LOGIN_BLOCK_SECONDS`:
   ```php
   const LOGIN_WINDOW_SECONDS = 900;
   const LOGIN_BLOCK_SECONDS = 900;
   const LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME = 5;
   const LOGIN_MAX_ATTEMPTS_PER_IP = 30;
   const LOGIN_MAX_ATTEMPTS_PER_USERNAME = 50;

   // Salt and hash of a random password. loginDummyPasswordHash() prefixes the
   // runtime's default bcrypt cost, so unknown users cost as much as existing
   // ones (which are rehashed to that cost on login): only password_verify().
   const LOGIN_DUMMY_PASSWORD_HASH_BODY = 'ENlVjhW77QzZlX9WCr8HmegfiRwBVAJyncnZTrzwaBlKpyZMupUau';
   ```
   Do not hardcode a full `$2y$12$…` hash. PHP 8.4+ defaults to bcrypt cost
   12, older PHP to cost 10. A fixed cost makes unknown-user logins slower or
   faster than real ones on one of them, which brings the timing leak back.

3. **Functions.** From the reference `api/helpers/auth.php`, copy these into the
   project's file, after `logoutCurrentUser()`:
   - `loginDummyPasswordHash()` and `loginPasswordHash(?array $user)`;
   - `loginRateLimitBuckets($ip, $username)`: three sha256 keys with their
     limits. It uses the existing `normalizeUsername()`;
   - `pruneLoginAttempts($db, $now)`: deletes rows whose window and block have
     both expired;
   - `consumeLoginAttempt($db, $key, $limit, $now)`: inserts the row if needed
     (ignoring SQLSTATE `23000` duplicate-key errors), increments with a single
     conditional `UPDATE`, and sets `blocked_until` once the limit is reached;
   - `assertLoginAllowed($db, $ip, $username, $now)`: prunes, consumes one
     attempt from every bucket and responds `429 too_many_login_attempts` with
     `retryAfter` once a bucket is full. Note the new signature;
   - `recordSuccessfulLogin($db, $ip, $username, $now)`: deletes the
     IP+username row and refunds one attempt to the IP and username buckets.

   Then remove the old `assertLoginAllowed(PDO $db, string $ip)`,
   `recordFailedLogin`, `clearFailedLogins` and `loginRateLimitKey`, but only
   after the `grep` from phase 0 confirmed that nothing else calls them. If
   something else does, port that caller to the new functions too, or **STOP**
   if it is unclear how.

   Failed logins no longer need their own "record" call: every attempt is
   counted up front by `assertLoginAllowed`, and successful ones are refunded.

4. **Login handler** (`api/handlers/auth-login.php`). Change the flow to the
   reference and keep everything project-specific around it:
   ```php
   $ip = clientIp();

   assertLoginAllowed($db, $ip, $username);
   $user = findUserByUsername($db, $username);
   $valid = $username !== '' && $password !== ''
       && password_verify($password, loginPasswordHash($user));

   if (!$valid || $user === null) {
       errorResponse('invalid_credentials', 401);
   }

   recordSuccessfulLogin($db, $ip, $username);
   // password_needs_rehash(), loginUser() and the response: unchanged
   ```
   Remove `$rateLimitKey`, the `recordFailedLogin()` call, the
   `clearFailedLogins()` call and the per-request
   `password_hash('dummy-password-value', …)`. Keep the existing
   `password_needs_rehash()` block: it is what keeps real hashes at the
   runtime's default cost, which the dummy hash matches.

5. **Tests** (`tests/api/auth.spec.php`). Remove the old
   `loginRateLimitKey(...)` assertion and port the two sections from the
   reference test, starting at `// ─── Rate limiting` and
   `// ─── Timing: no extra hash for unknown users`. They cover:
   - three distinct buckets, IP bucket independent of the username and
     username bucket independent of the IP;
   - block after 5 attempts on one account, correct `retryAfter`, unblock after
     `LOGIN_BLOCK_SECONDS`;
   - password spraying from one IP stops at 30, distributed guessing on one
     account stops at 50;
   - successful logins behind one NAT IP are not blocked and are refunded;
   - pruning removes expired rows and keeps active blocks;
   - the dummy hash uses the default algorithm and cost, rejects an empty
     password, real users use their own hash, and the handler contains no
     `password_hash('dummy`.

   The tests call the helpers with an explicit `$now`, so they do not depend on
   the clock. If the app changed the limits in step 2, the tests use the
   constants and still apply.

6. **No schema change.** `auth_login_attempts.ip` already holds a 64-character
   sha256 key in 0.1.0; it now holds one of three bucket keys. Existing rows
   from the old single key stay harmless and are pruned after
   `LOGIN_WINDOW_SECONDS`. Do not write a migration and do not touch a
   production database.

- **Verify:**
  ```bash
  php -l api/helpers/auth.php
  php -l api/handlers/auth-login.php
  php tests/api/auth.spec.php
  php tests/api/production-schema.spec.php
  grep -rn "recordFailedLogin\|clearFailedLogins\|loginRateLimitKey\|password_hash('dummy" api tests
  ```
  The `grep` must return nothing. If PHP 8.2 or 8.3 is available as well as
  8.4+, run `tests/api/auth.spec.php` on both: the dummy-hash cost test covers
  the difference between cost 10 and cost 12.
- **Manual check** with `npm run dev`: log in with a valid account, log out,
  then try a wrong password six times for the same username. The sixth attempt
  must return `429` with `retryAfter`. Use a local development database only.
- **Commit:** `fix: port login rate limiting and timing hardening from boilerplate v1.0.0`

---

## Phase 2: npm audit fixes within existing ranges

v1.0.0 did not change `package.json` dependency ranges. It refreshed
`package-lock.json` so the resolved versions are patched:

| Package | Resolved in v1.0.0 |
|---|---|
| `vue` | 3.5.44 |
| `vite` | 8.3.5 |
| `vitest` | 4.1.12 |
| `concurrently` | 9.2.5 (fixes `shell-quote`) |
| `postcss` | 8.5.31 |

```bash
npm audit fix
npm audit
```

- **Never use `npm audit fix --force`.** It jumps major versions (for example
  to Tailwind 4), which is the v2 upgrade, not this one.
- `git diff package.json` must show no changed dependency ranges. If `npm audit
  fix` changed a range, **STOP** and ask.
- Expected result for the boilerplate's own dependencies: no critical or high
  advisories. **3 moderate** advisories remain through `tailwindcss` 3
  (`postcss-selector-parser`). They are only fixed by the Tailwind 4 migration
  in `UPGRADE-v2.md`; report them, do not try to fix them here.
- Report advisories from the app's own extra dependencies to the user; do not
  fix them by upgrading or replacing packages without approval.
- **Verify:** `npm ci`, `npx vitest --run` and `npm run build` pass with the
  same test count as the baseline.
- **Commit:** `chore(deps): apply npm audit fixes within existing ranges`

---

## Phase 3: Final verification and report

Run all of these again and include the real output in the report:

```bash
npm ci
npx vitest --run
npm run build
npm audit
php tests/api/auth.spec.php
php tests/api/production-schema.spec.php
test ! -e dist/api/config.php && echo "no config.php in dist"
```

Report to the user:

1. The branch name and the commits per phase.
2. Which of the four security checks from phase 0 were missing and are now
   present.
3. Test results before and after (the PHP auth test now has more assertions;
   the Vitest count must match the baseline).
4. `npm audit` before and after.
5. Everything that was not done or needs a decision: STOP items, custom limits
   that were kept or changed, other callers of the removed functions, and
   advisories in the app's own dependencies.

Do not merge, push or deploy without the user's approval. After deploying,
nothing needs to be run on the production database.

## Rollback

Each phase is its own commit. To undo one, use `git revert <commit>` and then
run `npm ci`. To abandon the upgrade, delete the branch.

## Next step

After v1.0.0, `UPGRADE-v2.md` moves the app to Tailwind 4, vue-router 5 and
Vitest 5. Its phase 1 checks for the same four functions and will be skipped
for an app that completed this upgrade.
