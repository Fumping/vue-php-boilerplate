# Upgrade an existing app to boilerplate v2

**Audience:** an AI coding agent working in an app that was created from
[`Fumping/vue-php-boilerplate`](https://github.com/Fumping/vue-php-boilerplate)
with `scripts/create-app.sh`. Such an app is a copy without the boilerplate's
git history. It is already in use and has its own code on top of the
boilerplate.

**Goal:** bring the app to the boilerplate v2 stack without changing how the app
looks or behaves:

| Package | v1 (Tailwind 3 line) | v2 target |
|---|---|---|
| `tailwindcss` | `^3.4` + PostCSS + Autoprefixer | `^4.3.3` via `@tailwindcss/vite` `^4.3.3` |
| `vue-router` | `^4.6` | `^5.4.0` |
| `vitest` | `^4.1` | `^5.0.5` |
| `jsdom` | `^29` | `^30.1.3` |
| `concurrently` | `^9` | `^10.0.6` |
| `vue` | `^3.5` | `^3.5.44` (required by vue-router 5: `>=3.5.34`) |
| `vite` / `@vitejs/plugin-vue` | `^8` / `^6` | `^8.3.5` / `^6.0.10` |
| `autoprefixer`, `postcss` (direct deps) | present | removed |
| `tailwind.config.js`, `postcss.config.js` | present | removed (config moves to CSS) |

The boilerplate's own migration was dry-run from tag `v1.0.0` with exactly
these steps. Vitest, the build and `npm audit` (0 advisories) passed, and the
login page looked the same before and after.

Reference source code for the PHP security baseline (phase 1): tag `v1.0.0` of
the boilerplate (public repository).

---

## Rules for the agent

1. **Work on a new branch**, for example `chore/upgrade-boilerplate-v2`. Never
   on `main`. Do not push or merge unless the user asks.
2. **Make one commit per phase** (below), so each phase can be reverted on its
   own.
3. **Preserve the app's own code.** This is an upgrade, not a reset. Never
   replace a file wholesale with the boilerplate version. Port the specific
   change and keep the project's customizations.
4. **Never touch** `api/config.php`, `api/storage/`, `.env*`, a production
   database or deployment settings. Do not run anything against a live server.
5. **Install only the packages listed here.** If the app needs other packages
   upgraded or replaced (Tailwind plugins, for example), list them and ask the
   user first.
6. **Stop and ask the user** in any of the situations marked **STOP** below. Do
   not guess.
7. Run each verification step and report the real output. If something fails,
   fix it or stop. Never skip a failing check.

---

## Phase 0: Preflight and inventory (no changes)

1. **Node.js.** Run `node -v`. It must be `^22.22.2`, `^24.15.0` or `>=26`
   (required by jsdom 30, Vitest 5 and concurrently 10). If it is lower,
   **STOP** and tell the user which version is needed.
2. **Clean tree.** `git status` must be clean. Otherwise **STOP**.
3. **Baseline.** Run and record the results:
   ```bash
   npm ci
   npx vitest --run
   npm run build
   php tests/api/auth.spec.php                # if present
   php tests/api/production-schema.spec.php   # if present
   ```
   If the baseline already fails, **STOP** and report it. Do not mix existing
   failures with upgrade changes.
4. **Inventory.** Record these, because later phases depend on them:
   - The dependency versions in `package.json` and any extra dependencies the
     app added.
   - The contents of `tailwind.config.js`: `theme.extend`, `plugins` (for
     example `@tailwindcss/forms` or `typography`), `safelist`, `corePlugins`,
     `prefix`, `important`, `darkMode`.
   - Any CSS beyond the three `@tailwind` lines in `src/style.css`, such as
     `@layer components`, `@layer utilities` or `@apply`.
   - Vue files with `<style>` blocks that use `@apply` or `theme()`:
     `grep -rn "@apply\|theme(" src`.
   - Class names built in JavaScript (string concatenation or template
     literals), which the upgrade tool cannot see:
     ```bash
     grep -rnE ':class="[^"]*(\+|`)|classList|className' src
     ```
   - Whether `unplugin-vue-router` is installed.
   - The Vitest configuration (in `vite.config.js` or `vitest.config.*`) and
     test files that use `vi.mock`, `vi.hoisted`, `resolves`, `rejects`,
     `toThrow('')`, `.sequential`, `bench(` or reporters.
   - Whether the app must support browsers older than **Safari 16.4, Chrome 111
     or Firefox 128**. Check README, `browserslist` and project notes.
     Tailwind 4 does not support older browsers. If the app must, **STOP**: stay
     on v1.

---

## Phase 1: PHP security baseline (only where missing)

Apps created before boilerplate `v1.0.0` miss the login hardening. Check
`api/helpers/auth.php`:

| Check | Present when the file contains |
|---|---|
| Three-bucket rate limiting (IP+username, IP, username) | `function loginRateLimitBuckets` |
| Atomic attempt counting | `function consumeLoginAttempt` |
| Pruning of expired attempt rows | `function pruneLoginAttempts` |
| Constant-cost login for unknown usernames | `function loginDummyPasswordHash` |

- **All four present:** skip this phase.
- **No `api/helpers/auth.php` at all** (app predates authentication): **STOP**.
  Adding authentication is a feature, not an upgrade, so ask the user.
- **Otherwise**, port the missing parts from the reference:
  ```bash
  git clone --depth 1 --branch v1.0.0 https://github.com/Fumping/vue-php-boilerplate.git ../boilerplate-v1
  ```
  1. Compare the project's `api/helpers/auth.php`, `api/handlers/auth-login.php`
     and `tests/api/auth.spec.php` with the reference
     (`diff -u ../boilerplate-v1/api/helpers/auth.php api/helpers/auth.php`).
  2. From the reference `api/helpers/auth.php`, take:
     - the `LOGIN_*` constants and `LOGIN_DUMMY_PASSWORD_HASH_BODY`;
     - `loginDummyPasswordHash`, `loginPasswordHash`, `loginRateLimitBuckets`,
       `pruneLoginAttempts`, `consumeLoginAttempt`, `assertLoginAllowed` and
       `recordSuccessfulLogin`.
  3. Remove the old `recordFailedLogin`, `clearFailedLogins` and
     `loginRateLimitKey`, but only after confirming with `grep -rn` that
     nothing else calls them.
  4. Update the login handler to the reference flow:
     `assertLoginAllowed($db, $ip, $username)` → `findUserByUsername` →
     `password_verify($password, loginPasswordHash($user))` → on success
     `recordSuccessfulLogin($db, $ip, $username)`. Remove any per-request
     `password_hash('dummy…')`. Keep project-specific additions to the handler,
     such as extra fields in the response or audit logging.
  5. Port the rate-limit and timing tests from the reference
     `tests/api/auth.spec.php` (the sections after `// ─── Rate limiting`).
  6. No schema change is needed. The existing `auth_login_attempts.ip` column
     now stores a 64-character sha256 bucket key; it is `CHAR(64)`/`VARCHAR(64)`
     in both schemas. If the project changed that column to something shorter,
     **STOP**.
- **Verify:** `php -l` on the changed files and `php tests/api/auth.spec.php`.
  If PHP 8.2 or 8.3 is available, run the tests there as well: older PHP uses
  bcrypt cost 10 and newer PHP cost 12, and the dummy-hash test covers both.
- **Commit:** `fix: port login rate limiting and timing hardening from boilerplate v1.0.0`

---

## Phase 2: Tailwind CSS 3 → 4

1. **Run the official upgrade tool** (Node ≥ 20, clean git tree):
   ```bash
   npx @tailwindcss/upgrade
   ```
   It updates `tailwindcss` to v4, rewrites `src/style.css`, migrates
   `tailwind.config.js` into CSS (`@theme`, `@plugin`, …) and deletes it where
   it can, renames classes in templates, and switches PostCSS to
   `@tailwindcss/postcss` while removing `autoprefixer`.
2. **Switch from PostCSS to the Vite plugin**, which is the boilerplate v2
   standard:
   ```bash
   npm uninstall @tailwindcss/postcss postcss
   npm install -D @tailwindcss/vite@^4.3.3
   ```
   - Delete `postcss.config.js` if it now only contains `@tailwindcss/postcss`.
     If it contains other plugins, keep those plugins and remove only the
     Tailwind entry. In that case keep `postcss` installed.
   - In `vite.config.js`, add the import and the plugin right after `vue()`:
     ```js
     import tailwindcss from '@tailwindcss/vite'
     // …
     plugins: [
       vue(),
       tailwindcss(),
       copyApiPlugin({ /* unchanged */ }),
     ],
     ```
3. **Check `src/style.css`.** It must start with `@import 'tailwindcss';`. The
   tool adds a `border-color: var(--color-gray-200, currentcolor)` block in
   `@layer base`; keep it. Then append this block, because v4 no longer gives
   buttons a pointer cursor:
   ```css
   /* Tailwind v4 sets `cursor: default` on buttons; keep the v3 pointer cursor. */
   @layer base {
     button:not(:disabled),
     [role='button']:not(:disabled) {
       cursor: pointer;
     }
   }
   ```
   If the project uses placeholders and relied on the v3 gray placeholder, also
   add `input::placeholder, textarea::placeholder { color: var(--color-gray-400); }`
   inside `@layer base`.
4. **Handle what the tool cannot do**, using the inventory from phase 0:
   - **Leftover `tailwind.config.js`:** if the tool kept it (complex config),
     load it explicitly with `@config "../tailwind.config.js";` in `style.css`.
     `safelist` and `corePlugins` are not supported in v4. Replace `safelist`
     with `@source inline("…")`. For `corePlugins`, **STOP** and ask.
   - **`@apply` inside Vue `<style>` blocks:** add
     `@reference "../style.css";` at the top of each such block, with the path
     relative to the component.
   - **`@layer components` / `@layer utilities` with custom classes:** convert
     them to `@utility name { … }`. The upgrade tool usually does this; check it.
   - **Tailwind plugins** (`@tailwindcss/forms`, `typography`, …): they must be
     v4-compatible and loaded with `@plugin "…";`. Upgrading these packages
     needs the user's approval (rule 5).
   - **Classes built in JavaScript:** apply the renames below by hand.
   - **Renames to verify** (v3 → v4): `shadow-sm`→`shadow-xs`, `shadow`→`shadow-sm`,
     `rounded-sm`→`rounded-xs`, `rounded`→`rounded-sm`, `blur-sm`→`blur-xs`,
     `blur`→`blur-sm`, `drop-shadow(-sm)`, `backdrop-blur(-sm)` (same pattern),
     `outline-none`→`outline-hidden`, `ring`→`ring-3`,
     `*-opacity-*`→ an opacity modifier (`bg-black/50`), `flex-grow-*`→`grow-*`,
     `flex-shrink-*`→`shrink-*`, `overflow-ellipsis`→`text-ellipsis`, leading
     `!important` → trailing (`flex!`), `bg-[--var]`→`bg-(--var)`.
   - **Behavior changes to check visually:**
     - `space-y-*` and `divide-y-*` now use `:not(:last-child)`.
     - `hover:` only applies on devices that support hover.
     - The `hidden` attribute now wins over `block`/`flex`.
     - `transform-none` no longer resets `rotate`/`scale`/`translate`.
     - Stacked variants apply left to right (`first:*:pt-0` → `*:first:pt-0`).
5. **Verify:**
   ```bash
   npm run build
   grep -c "@tailwind" src/style.css   # must be 0
   ```
   Then run `npm run dev` (or `npm run preview`) and compare every route with
   the baseline in a browser: spacing, borders, border colors, focus rings,
   shadows, rounded corners, button cursor, placeholder color, dark mode (if
   used), and mobile width. Check every route the app has, not only the
   boilerplate's own pages. Fix any differences before committing.
6. **Commit:** `chore(deps): migrate to Tailwind CSS 4 with @tailwindcss/vite`

---

## Phase 3: vue-router 4 → 5

```bash
npm install vue-router@^5.4.0 vue@^3.5.44
```

- Apps without `unplugin-vue-router` need **no code changes**. Vue Router 5 has
  no breaking changes for plain v4 usage.
- **If `unplugin-vue-router` is installed:** remove it, and change imports
  `unplugin-vue-router/vite` → `vue-router/vite` and data loaders → `vue-router/experimental`.
  See <https://router.vuejs.org/guide/migration/v4-to-v5>.
- The only other breaking change is in the IIFE/CDN build (no bundled
  devtools), which does not apply to a Vite app.
- **Verify:** `npm run build`. Then in `npm run dev`, check:
  - logged out → visiting `/` redirects to `/login?redirect=/`;
  - logging in returns to the redirect target;
  - an admin-only route redirects a non-admin user;
  - an unknown path redirects to `/`.
- **Commit:** `chore(deps): upgrade vue-router to 5`

---

## Phase 4: Vitest 4 → 5 and jsdom 30

```bash
npm install -D vitest@^5.0.5 jsdom@^30.1.3
```

Check the test code against the Vitest 5 breaking changes
(<https://vitest.dev/guide/migration>):

- **`clearMocks` is now `true` by default:** mock call history is cleared
  before every test. Tests that count calls across tests fail now. Fix those
  tests; only set `test.clearMocks: false` if the user agrees.
- **`vi.mock` / `vi.unmock` / `vi.hoisted`** inside a function, block or
  `describe`/`it` callback now throws. Move them to module top level, or use
  `vi.doMock`.
- **Unawaited `expect(…).resolves` / `.rejects`** now fail. Add `await`.
- **`toThrow('')`** now matches any message. Use `toThrow(/^$/)` for an empty
  message.
- **`test.sequential` / `describe.sequential`** are removed. Use
  `{ concurrent: false }`.
- **`-t` name patterns** join nested names with `' > '`. Update scripts or CI
  that use `-t`.
- **Config:** Vitest no longer searches parent directories for a config file.
  `coverage.include`/`exclude` are relative to the project root. Reporter
  output moves to `.vitest/`. If the project uses json, junit or html
  reporters, add `.vitest/` to `.gitignore` and update CI artifact paths.
- **Fake timers** now also fake `Temporal`.
- **Verify:** `npx vitest --run`. All tests must pass with the same count as
  the baseline.
- **Commit:** `chore(deps): upgrade Vitest to 5 and jsdom to 30`

---

## Phase 5: Remaining dependencies

```bash
npm install -D concurrently@^10.0.6 vite@^8.3.5 @vitejs/plugin-vue@^6.0.10
npm uninstall autoprefixer   # if the upgrade tool left it
npm audit
```

- `concurrently` 10 only raises the Node requirement (≥ 22). The `dev` script
  stays the same. Check `npm run dev` still starts both Vite (5173) and PHP
  (8000), and that Ctrl+C stops both.
- `npm audit` should report **0 vulnerabilities** for the boilerplate's own
  dependencies. Report advisories from the app's own extra dependencies to the
  user; do not fix them with `--force`.
- **Commit:** `chore(deps): update concurrently, Vite and plugin-vue`

---

## Phase 6: Docs and CI

- **`README.md`:**
  - Stack table: `Tailwind CSS 3.4 + PostCSS + Autoprefixer` →
    `Tailwind CSS 4 (@tailwindcss/vite)`.
  - Remove `tailwind.config.js` and `postcss.config.js` from the project tree
    if they were deleted.
  - Mention the Node.js requirement (`^22.22.2 || ^24.15.0 || >=26`).
- **`CLAUDE.md` / `AGENTS.md`:** update any mention of Tailwind 3,
  `tailwind.config.js` or PostCSS.
- **CI** (`.github/workflows/*.yml`, if present): `node-version` must be 24 or
  newer, or 22.22+. Update artifact paths if Vitest reporters moved to
  `.vitest/`.
- **Commit:** `docs: update for Tailwind 4, vue-router 5 and Vitest 5`

---

## Phase 7: Final verification and report

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
2. The package versions before and after.
3. Test counts before and after (they must match, or be higher if phase 1 added
   tests).
4. What was checked visually, and which routes.
5. Everything that was not done or needs a decision: STOP items, plugins or
   extra dependencies that need approval, and visual differences that remain.

Do not merge, push or deploy without the user's approval.

## Rollback

Each phase is its own commit. To undo one, use `git revert <commit>` and then
run `npm ci`. To abandon the upgrade, delete the branch.

---

## Appendix: end state in the boilerplate itself

`package.json` (excerpt):

```json
"dependencies": {
  "vue": "^3.5.44",
  "vue-router": "^5.4.0"
},
"devDependencies": {
  "@tailwindcss/vite": "^4.3.3",
  "@vitejs/plugin-vue": "^6.0.10",
  "concurrently": "^10.0.6",
  "jsdom": "^30.1.3",
  "tailwindcss": "^4.3.3",
  "vite": "^8.3.5",
  "vitest": "^5.0.5"
}
```

`vite.config.js` (excerpt):

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { copyApiPlugin } from './scripts/copy-api.js'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    copyApiPlugin({ /* unchanged */ }),
  ],
  // server, test: unchanged
})
```

`src/style.css`:

```css
@import "tailwindcss";

/* Tailwind 4 dropped cursor: pointer on buttons; keep the v3 behaviour. */
@layer base {
  button:not(:disabled),
  [role="button"]:not(:disabled) {
    cursor: pointer;
  }
}
```

The boilerplate itself has no border-color compatibility block, because every
bordered element in its views has an explicit color (`border-slate-300`,
`divide-slate-100`). In an app, keep the block the upgrade tool adds unless you
have checked that every `border`/`divide` class has an explicit color.

Class renames the upgrade tool made in the boilerplate's views:
`shadow-sm`→`shadow-xs`, `shadow`→`shadow-sm`, `rounded`→`rounded-sm`.
