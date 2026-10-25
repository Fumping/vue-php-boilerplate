# CLAUDE.md: instructions for Claude Code

Claude reads this file at the start of every session in this project. Keep it
short and concrete.

## Commands

```bash
npm run dev         # Vite (5173) + PHP (8000) in parallel via concurrently
npm run dev:vite    # Vite only
npm run dev:php     # PHP built-in server only
npm run build       # Frontend + api/ → dist/
npm run preview     # Preview the built dist/
npm run test        # Vitest
```

## Stack

Vue 3 (Composition API, `<script setup>`) · Vue Router 5 · Vite 8 · Tailwind 4 ·
Vitest · PHP 8 flat router · PDO (SQLite locally, MySQL in production).

## Architecture on one page

```
Vue frontend (src/)
  └─ fetch('/api/...') via src/lib/api.js
      └─ Vite proxy (dev) or Apache rewrite (prod)
          └─ api/index.php  ← flat router
              ├─ public endpoints (health, login, auth/me)
              ├─ requireUser($db) for logged-in users
              └─ requireAdmin($db) for admin
                  └─ each endpoint = 1 handler in api/handlers/
```

## Patterns you MUST follow

### Adding a new API endpoint

1. Create a handler in `api/handlers/<name>.php`. Example:
   ```php
   <?php
   $db = getDb();
   $rows = $db->query('SELECT * FROM items')->fetchAll();
   jsonResponse($rows);
   ```
2. Mount it in `api/index.php`:
   ```php
   if ($method === 'GET' && $path === '/items') {
       require __DIR__ . '/handlers/items-list.php';
       exit;
   }
   ```
3. Call it from a composable in `src/composables/`:
   ```js
   import { useApi } from './useApi.js'
   export function useItems() {
       const { apiGet, apiPost } = useApi()
       return {
           list:   () => apiGet('/items'),
           create: (data) => apiPost('/items', data),
       }
   }
   ```

### Frontend fetch

**ALWAYS** go through `src/lib/api.js`, never bare `fetch()`. That's where
session cookies, CSRF headers and errors are handled consistently.

### PHP database access

**ALWAYS** use `getDb()` from `api/config.php`, never bare `new PDO()`.
`getDb()` handles the `DB_DRIVER` switch and caches the connection.

### New Vue view/route

1. Create `src/views/MyView.vue`.
2. Register it in `src/router/index.js`:
   ```js
   { path: '/my', component: () => import('../views/MyView.vue'), meta: { title: 'My' } }
   ```

## SQL portability (SQLite ↔ MySQL)

When designing a schema, it has to work on both.

| Do | Don't |
|---|---|
| `INTEGER PRIMARY KEY` | `INT AUTO_INCREMENT PRIMARY KEY` (MySQL only) |
| `TEXT` + CHECK constraint | `ENUM('a','b')` (MySQL only) |
| `INTEGER` for booleans | `BOOLEAN` (differs per DB) |
| `DATETIME DEFAULT CURRENT_TIMESTAMP` | `TIMESTAMP` with `ON UPDATE` (MySQL only) |
| Double quotes for identifiers | Backticks (MySQL only) |

See the "SQL portability" section in `README.md` for the full table.

## Auth

Auth is on by default, using PHP sessions. Rules:

- Call `requireUser($db)` in every logged-in handler.
- Call `requireAdmin($db)` in every admin handler.
- After `requireUser`/`requireAdmin`, also call `requireCsrfToken()` for POST,
  PUT, PATCH and DELETE mutations.
- Never rely on Vue router meta alone. It is not server-side security.
- New passwords are 12 to 72 bytes (bcrypt's limit) and are stored only via
  `password_hash()`.
- The bootstrap `admin`/`admin` account is for development only and must change
  its password immediately.
- Production never runs schema DDL. Import `db/schema.mysql.sql` and create the
  first admin with `api/cli/bootstrap-admin.php`.

## Multi-tool pattern (optional)

If this app becomes a dashboard with several tools (like `inventus-tools`):

1. Create `src/tools.js`:
   ```js
   export const tools = [
     { id: 'foo', label: 'Foo', description: '...', route: '/tools/foo' },
     { id: 'bar', label: 'Bar', description: '...', route: '/tools/bar' },
   ]
   ```
2. Create `src/views/tools/foo/index.vue` for each tool.
3. Register routes: `path: '/tools/foo'`, `component: () => import('../views/tools/foo/index.vue')`.
4. Have `HomeView.vue` render the tools from `tools.js` as tiles.

## What NOT to do

- **Don't add a framework** (Laravel, Symfony, Slim). The flat router is deliberate.
- **Don't add npm packages without asking** the user first. The baseline is
  small for a reason.
- **No `@supabase/supabase-js` in the baseline.** If a project needs Supabase,
  add it per project.
- **No ORM** (Eloquent, Doctrine). Raw PDO with prepared statements.
- **No bare `fetch()` in views.** Always go through `src/lib/api.js`.
- **No bare `new PDO()`.** Always use `getDb()`.
- **Don't deviate from the existing file structure** without discussing it
  first.

## Decision history

- **Why SQLite locally and MySQL in production?** SQLite needs zero config for
  dev. MySQL is what production hosting usually offers. The `DB_DRIVER` toggle
  is kept simple on purpose.
- **Why is `/api/` inside `/dist/`?** So `dist/` is a single deploy artifact:
  upload it and you're done. The API needs no separate deploy step.
- **Why PHP sessions?** They keep credentials out of the browser bundle and give
  a small server-side security boundary without extra packages.
- **Why `concurrently`?** Vite and PHP are two separate processes, and one npm
  script has to start both. Alternatives (Foreman, tmuxinator) add tools outside
  npm, while `concurrently` is a dev dependency that works everywhere.
