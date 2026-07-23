# Boilerplate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Scaffold a reusable Vue 3 + PHP 8 monorepo boilerplate at `/Users/edwinprins/Git/boilerplate/` that a developer can copy (or run a setup script) to bootstrap a new application.

**Architecture:** Vue 3 + Vite + Tailwind frontend in `src/`, PHP 8 flat-router API in `api/`. Local dev uses SQLite; production uses MySQL — both via a `DB_DRIVER` constant in `api/config.php` and a shared `getDb(): PDO` helper. `npm run build` invokes a custom Vite plugin (`scripts/copy-api.js`) that copies `api/` into `dist/api/`, so the `dist/` folder is a complete deploy artifact. `npm run dev` uses `concurrently` to run Vite (5173) and the PHP built-in server (8000) in parallel, with Vite proxying `/api/*` to PHP. No Supabase or auth framework in baseline — auth is left as three commented-out hook-points.

**Tech Stack:** Vue 3.5 · Vite 8 · Vue Router 4 · Tailwind CSS 3.4 · PostCSS · Autoprefixer · Vitest · jsdom · concurrently · PHP 8 · PDO (SQLite + MySQL)

## Global Constraints

- Working directory for all tasks: `/Users/edwinprins/Git/boilerplate/`
- No Supabase dependency anywhere in baseline (no `@supabase/supabase-js`)
- No framework in PHP (no Laravel/Symfony) — flat router only
- Frontend uses Vue 3 Composition API with `<script setup>`
- Fetch always via `src/lib/api.js` (never bare `fetch()` in views/composables)
- PDO always via `getDb()` (never bare `new PDO()` in handlers)
- `dist/` must contain both frontend and API after `npm run build`
- SQLite for local dev, MySQL for production, single `DB_DRIVER` toggle
- Documentation in Dutch (README.md, CLAUDE.md)
- Files that must exist per spec (verbatim paths):
  `package.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `index.html`, `.env.example`, `.gitignore`, `.htaccess`, `README.md`, `CLAUDE.md`, `scripts/copy-api.js`, `scripts/create-app.sh`, `src/main.js`, `src/App.vue`, `src/style.css`, `src/router/index.js`, `src/lib/api.js`, `src/composables/useApi.js`, `src/views/HomeView.vue`, `src/views/LoginView.vue`, `api/index.php`, `api/config.example.php`, `api/.htaccess`, `api/helpers/auth.php`, `api/helpers/response.php`, `api/handlers/health.php`, `api/storage/.gitkeep`, `db/.gitkeep`, `src/components/.gitkeep`, `docs/.gitkeep`

---

## Task 1: Project bootstrap files

**Files:**
- Create: `boilerplate/package.json`
- Create: `boilerplate/index.html`
- Create: `boilerplate/.gitignore`
- Create: `boilerplate/.env.example`

**Interfaces:**
- Consumes: nothing (first task)
- Produces:
  - npm scripts: `dev`, `dev:vite`, `dev:php`, `build`, `preview`, `test`
  - Placeholder `{{APP_NAME}}` in `package.json` `name` field and `index.html` `<title>`
  - App name mounts to `<div id="app">`

- [ ] **Step 1: Create `package.json`**

```json
{
  "name": "{{APP_NAME}}",
  "private": true,
  "version": "0.1.0",
  "type": "module",
  "scripts": {
    "dev": "concurrently -k -n vite,php -c cyan,magenta \"vite\" \"php -S localhost:8000 api/index.php\"",
    "dev:vite": "vite",
    "dev:php": "php -S localhost:8000 api/index.php",
    "build": "vite build",
    "preview": "vite preview",
    "test": "vitest"
  },
  "dependencies": {
    "vue": "^3.5.30",
    "vue-router": "^4.6.4"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^6.0.5",
    "autoprefixer": "^10.4.27",
    "concurrently": "^9.1.0",
    "jsdom": "^29.0.1",
    "postcss": "^8.5.8",
    "tailwindcss": "^3.4.19",
    "vite": "^8.0.1",
    "vitest": "^4.1.2"
  }
}
```

- [ ] **Step 2: Create `index.html`**

```html
<!doctype html>
<html lang="nl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{APP_NAME}}</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.js"></script>
  </body>
</html>
```

- [ ] **Step 3: Create `.gitignore`**

```
node_modules/
dist/
dist-ssr/
.env
.env.local
.DS_Store
.superpowers/
.worktrees/

# Local secrets & data
api/config.php
api/storage/db.sqlite
api/storage/db.sqlite-shm
api/storage/db.sqlite-wal
api/storage/tmp/

# Editor
.vscode/*
!.vscode/extensions.json
.idea
*.suo
*.ntvs*
*.njsproj
*.sln
*.sw?

# Logs
*.log
npm-debug.log*
```

- [ ] **Step 4: Create `.env.example`**

```
# API base URL (leeg tijdens dev; Vite proxy stuurt /api → localhost:8000)
VITE_API_URL=/api

# API key — moet overeenkomen met API_KEY in api/config.php
VITE_API_KEY=change-me
```

- [ ] **Step 5: Verify structure**

Run: `ls -la /Users/edwinprins/Git/boilerplate/`
Expected: `package.json`, `index.html`, `.gitignore`, `.env.example` present.

- [ ] **Step 6: Install deps**

Run: `cd /Users/edwinprins/Git/boilerplate && npm install`
Expected: Installs without errors, creates `node_modules/` and `package-lock.json`.

- [ ] **Step 7: Commit**

```bash
cd /Users/edwinprins/Git/boilerplate
git init -b main
git add package.json package-lock.json index.html .gitignore .env.example
git commit -m "chore: project bootstrap (package.json, index.html, gitignore, env)"
```

---

## Task 2: Vue + Vite baseline running

**Files:**
- Create: `boilerplate/vite.config.js`
- Create: `boilerplate/src/main.js`
- Create: `boilerplate/src/App.vue`
- Create: `boilerplate/src/style.css`
- Create: `boilerplate/src/views/HomeView.vue`
- Create: `boilerplate/src/components/.gitkeep`

**Interfaces:**
- Consumes: `package.json` scripts from Task 1
- Produces:
  - `main.js` mounts Vue app to `#app` using `createApp(App)`, calls `.use(router)` (router added in Task 3 — for now baseline mount only)
  - `App.vue` renders a `<RouterView />`
  - `HomeView.vue` is a default landing view (name: `HomeView`)

- [ ] **Step 1: Create `vite.config.js` (baseline, no plugins yet)**

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/',
  test: {
    environment: 'jsdom',
    globals: true,
    env: {
      VITE_API_URL: '/api',
      VITE_API_KEY: 'test-key',
    },
  },
})
```

- [ ] **Step 2: Create `src/main.js` (router import commented until Task 3)**

```js
import { createApp } from 'vue'
import App from './App.vue'
import './style.css'

// import router from './router/index.js'
// createApp(App).use(router).mount('#app')

createApp(App).mount('#app')
```

- [ ] **Step 3: Create `src/App.vue`**

```vue
<script setup>
import HomeView from './views/HomeView.vue'
</script>

<template>
  <HomeView />
</template>
```

- [ ] **Step 4: Create `src/style.css` (plain, Tailwind added in Task 4)**

```css
:root {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  line-height: 1.5;
}
body {
  margin: 0;
  padding: 0;
}
```

- [ ] **Step 5: Create `src/views/HomeView.vue`**

```vue
<script setup>
const appName = 'Boilerplate'
</script>

<template>
  <main>
    <h1>{{ appName }}</h1>
    <p>Welkom bij je nieuwe app.</p>
  </main>
</template>
```

- [ ] **Step 6: Create `src/components/.gitkeep`**

```bash
touch /Users/edwinprins/Git/boilerplate/src/components/.gitkeep
```

- [ ] **Step 7: Verify dev server starts**

Run: `cd /Users/edwinprins/Git/boilerplate && npm run dev:vite`
Expected: Vite starts on `http://localhost:5173`. Open in browser; page shows "Boilerplate — Welkom bij je nieuwe app."
Stop the server (Ctrl-C).

- [ ] **Step 8: Commit**

```bash
git add vite.config.js src/main.js src/App.vue src/style.css src/views/HomeView.vue src/components/.gitkeep
git commit -m "feat: Vue 3 + Vite baseline with HomeView"
```

---

## Task 3: Router with 2 routes and commented-out auth guard

**Files:**
- Create: `boilerplate/src/router/index.js`
- Create: `boilerplate/src/views/LoginView.vue`
- Modify: `boilerplate/src/main.js` (activate router import)
- Modify: `boilerplate/src/App.vue` (replace direct HomeView import with `<RouterView />`)

**Interfaces:**
- Consumes: `src/main.js` mount, `src/views/HomeView.vue`
- Produces:
  - `router` (default export) — imported by `main.js`
  - Named export `requireAuth` — commented-out hook-point that other apps can implement and attach as `beforeEnter`

- [ ] **Step 1: Create `src/router/index.js`**

```js
import { createRouter, createWebHistory } from 'vue-router'

// AUTH HOOK-POINT (commented-out door bewuste keuze).
// Om auth aan te zetten:
//   1. Implementeer de `requireAuth` guard hieronder (voorbeeld: check
//      localStorage token, Supabase session, cookie, wat je maar wilt).
//   2. Voeg `beforeEnter: requireAuth` toe aan de routes die je wilt
//      beschermen.
//   3. Overweeg ook een globale `router.beforeEach` als je 't voor alle
//      routes wilt behalve /login.
//
// export async function requireAuth(to, from, next) {
//   const authenticated = false // TODO: echte auth-check
//   if (!authenticated) {
//     return next({ path: '/login', query: { redirect: to.fullPath } })
//   }
//   next()
// }

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      component: () => import('../views/LoginView.vue'),
      meta: { title: 'Inloggen' },
    },
    {
      path: '/',
      component: () => import('../views/HomeView.vue'),
      meta: { title: 'Home' },
    },
  ],
})

router.afterEach((to) => {
  document.title = to.meta?.title ? `${to.meta.title} | Boilerplate` : 'Boilerplate'
})

export default router
```

- [ ] **Step 2: Create `src/views/LoginView.vue`**

```vue
<script setup>
// AUTH HOOK-POINT.
// Deze view is een skeleton. Vul in met je auth-provider naar keuze:
//   - Supabase: importeer supabase client, roep signInWithOAuth aan
//   - Firebase: signInWithPopup / signInWithRedirect
//   - Session-based: POST username/password naar api/login endpoint
//
// Zie CLAUDE.md sectie "Auth toevoegen" voor concrete voorbeelden.

function loginWithProvider(provider) {
  // TODO: implementeer per provider
  console.log('TODO: login met', provider)
}
</script>

<template>
  <main class="login">
    <h1>Inloggen</h1>
    <p>Auth is nog niet geactiveerd. Zie CLAUDE.md voor instructies.</p>

    <div class="providers">
      <button type="button" @click="loginWithProvider('microsoft')">
        Inloggen met Microsoft
      </button>
      <button type="button" @click="loginWithProvider('google')">
        Inloggen met Google
      </button>
    </div>
  </main>
</template>
```

- [ ] **Step 3: Update `src/main.js` — enable router**

Replace file contents with:

```js
import { createApp } from 'vue'
import App from './App.vue'
import router from './router/index.js'
import './style.css'

createApp(App).use(router).mount('#app')
```

- [ ] **Step 4: Update `src/App.vue` — use RouterView**

Replace file contents with:

```vue
<template>
  <RouterView />
</template>
```

- [ ] **Step 5: Verify routes work**

Run: `npm run dev:vite`
Open `http://localhost:5173/` → HomeView, title "Home | Boilerplate".
Open `http://localhost:5173/login` → LoginView with two buttons, title "Inloggen | Boilerplate".
Stop server.

- [ ] **Step 6: Commit**

```bash
git add src/router/index.js src/views/LoginView.vue src/main.js src/App.vue
git commit -m "feat: Vue Router with Home and Login views, commented-out auth guard"
```

---

## Task 4: Tailwind CSS + PostCSS

**Files:**
- Create: `boilerplate/tailwind.config.js`
- Create: `boilerplate/postcss.config.js`
- Modify: `boilerplate/src/style.css` (add Tailwind directives)
- Modify: `boilerplate/src/views/HomeView.vue` (use a few Tailwind classes to verify)
- Modify: `boilerplate/src/views/LoginView.vue` (replace inline structure with Tailwind classes)

**Interfaces:**
- Consumes: Vue baseline from Task 3
- Produces: Tailwind utility classes available throughout the app

- [ ] **Step 1: Create `tailwind.config.js`**

```js
/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

- [ ] **Step 2: Create `postcss.config.js`**

```js
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
```

- [ ] **Step 3: Replace `src/style.css` with Tailwind directives**

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

- [ ] **Step 4: Update `src/views/HomeView.vue`**

```vue
<script setup>
const appName = 'Boilerplate'
</script>

<template>
  <main class="min-h-screen bg-slate-50 flex items-center justify-center p-8">
    <div class="max-w-lg text-center space-y-4">
      <h1 class="text-4xl font-bold text-slate-800">{{ appName }}</h1>
      <p class="text-slate-600">Welkom bij je nieuwe app.</p>
      <p class="text-sm text-slate-500">
        Zie <code class="bg-slate-200 px-1 rounded">README.md</code> voor volgende stappen.
      </p>
    </div>
  </main>
</template>
```

- [ ] **Step 5: Update `src/views/LoginView.vue`**

```vue
<script setup>
function loginWithProvider(provider) {
  console.log('TODO: login met', provider)
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 flex items-center justify-center p-8">
    <div class="w-full max-w-sm space-y-6 bg-white rounded-lg shadow p-8">
      <h1 class="text-2xl font-bold text-slate-800">Inloggen</h1>
      <p class="text-sm text-slate-500">
        Auth is nog niet geactiveerd. Zie CLAUDE.md voor instructies.
      </p>

      <div class="space-y-2">
        <button
          type="button"
          class="w-full rounded border border-slate-300 px-4 py-2 text-slate-700 hover:bg-slate-100"
          @click="loginWithProvider('microsoft')"
        >
          Inloggen met Microsoft
        </button>
        <button
          type="button"
          class="w-full rounded border border-slate-300 px-4 py-2 text-slate-700 hover:bg-slate-100"
          @click="loginWithProvider('google')"
        >
          Inloggen met Google
        </button>
      </div>
    </div>
  </main>
</template>
```

- [ ] **Step 6: Verify Tailwind renders**

Run: `npm run dev:vite`
`http://localhost:5173/` → home is centered, slate background, large bold heading.
`http://localhost:5173/login` → card with two buttons, white background on slate page.
Stop server.

- [ ] **Step 7: Commit**

```bash
git add tailwind.config.js postcss.config.js src/style.css src/views/HomeView.vue src/views/LoginView.vue
git commit -m "feat: Tailwind CSS setup and styled views"
```

---

## Task 5: PHP API scaffold (flat router + config + health handler)

**Files:**
- Create: `boilerplate/api/config.example.php`
- Create: `boilerplate/api/index.php`
- Create: `boilerplate/api/helpers/response.php`
- Create: `boilerplate/api/helpers/auth.php`
- Create: `boilerplate/api/handlers/health.php`
- Create: `boilerplate/api/.htaccess`
- Create: `boilerplate/api/storage/.gitkeep`
- Create: `boilerplate/db/.gitkeep`
- Create: `boilerplate/docs/.gitkeep`

**Interfaces:**
- Consumes: nothing
- Produces:
  - Constants: `API_KEY`, `DB_DRIVER`, `DB_SQLITE_PATH`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
  - Functions: `getDb(): PDO`, `jsonResponse(mixed $data, int $status = 200): void`, `errorResponse(string $message, int $status = 400): void`, `requireApiKey(): void`
  - Endpoint: `GET /` (via PHP built-in server) returns `{"status": "ok", "driver": "sqlite"|"mysql"}`

- [ ] **Step 1: Create `api/config.example.php`**

```php
<?php
// ─── API ──────────────────────────────────────────────────────────
define('API_KEY', 'change-me');

// ─── Database ─────────────────────────────────────────────────────
// Zet DB_DRIVER op 'sqlite' (lokaal) of 'mysql' (productie).
define('DB_DRIVER', 'sqlite');

// SQLite (lokaal) — bestand wordt automatisch aangemaakt
define('DB_SQLITE_PATH', __DIR__ . '/storage/db.sqlite');

// MySQL (productie) — vul in bij deploy
define('DB_HOST', 'localhost');
define('DB_NAME', 'my_database');
define('DB_USER', 'my_user');
define('DB_PASS', 'my_password');

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (DB_DRIVER === 'sqlite') {
        $dir = dirname(DB_SQLITE_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } elseif (DB_DRIVER === 'mysql') {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
    } else {
        throw new RuntimeException('Onbekende DB_DRIVER: ' . DB_DRIVER);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
```

- [ ] **Step 2: Create `api/helpers/response.php`**

```php
<?php

function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function errorResponse(string $message, int $status = 400, array $extra = []): void {
    jsonResponse(array_merge(['error' => $message], $extra), $status);
}
```

- [ ] **Step 3: Create `api/helpers/auth.php`**

```php
<?php

function requireApiKey(): void {
    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // Fallback: sommige servers strippen X-Api-Key uit $_SERVER.
    if ($key === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'X-Api-Key') === 0) {
                $key = $value;
                break;
            }
        }
    }

    if ($key !== API_KEY) {
        errorResponse('invalid_api_key', 401);
    }
}

// AUTH HOOK-POINT.
// Om user-level auth toe te voegen (Supabase, Firebase, session, ...):
//   1. Implementeer requireUser() hieronder.
//   2. Roep 'm aan in handlers die per-user auth vereisen.
// Voorbeeld voor Supabase:
//
// function requireUser(): array {
//     $token = extractBearerToken();
//     $user = validateSupabaseToken($token); // HTTP call naar /auth/v1/user
//     if (!$user) errorResponse('unauthorized', 401);
//     return $user;
// }
```

- [ ] **Step 4: Create `api/handlers/health.php`**

```php
<?php

jsonResponse([
    'status' => 'ok',
    'driver' => defined('DB_DRIVER') ? DB_DRIVER : 'unknown',
    'time'   => date(DATE_ATOM),
]);
```

- [ ] **Step 5: Create `api/index.php`**

```php
<?php
declare(strict_types=1);

// ─── Bootstrap ────────────────────────────────────────────────────
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error'  => 'missing_config',
        'detail' => 'Kopieer api/config.example.php naar api/config.php.',
    ]);
    exit;
}
require $configPath;
require __DIR__ . '/helpers/response.php';
require __DIR__ . '/helpers/auth.php';

// ─── CORS ─────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Path & method ────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Strip leading /api zodat /api/health en /health beide werken
// (Vite proxy stuurt met prefix door; Apache rewrite ook)
$path = preg_replace('#^/api#', '', $path) ?: '/';

// ─── Stage 1: publieke endpoints (voor requireApiKey) ─────────────
if ($method === 'GET' && $path === '/') {
    require __DIR__ . '/handlers/health.php';
    exit;
}

// ─── Stage 2: API-key gate ────────────────────────────────────────
requireApiKey();

// ─── Stage 3: beschermde endpoints ───────────────────────────────
// Voeg hier je handlers toe. Voorbeeld:
// if ($method === 'GET' && $path === '/items') {
//     require __DIR__ . '/handlers/items-list.php';
//     exit;
// }

// ─── 404 ──────────────────────────────────────────────────────────
errorResponse('not_found', 404, ['path' => $path, 'method' => $method]);
```

- [ ] **Step 6: Create `api/.htaccess`**

```
# Blokkeer directe toegang tot gevoelige bestanden
<FilesMatch "\.(sqlite|sqlite-shm|sqlite-wal)$">
    Require all denied
</FilesMatch>

<Files "config.php">
    Require all denied
</Files>

<Files "config.example.php">
    Require all denied
</Files>

# Stuur alle non-existent paden binnen /api/ naar index.php
RewriteEngine On
RewriteBase /api/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

- [ ] **Step 7: Create empty directories via `.gitkeep`**

```bash
mkdir -p /Users/edwinprins/Git/boilerplate/api/storage
mkdir -p /Users/edwinprins/Git/boilerplate/db
mkdir -p /Users/edwinprins/Git/boilerplate/docs
touch /Users/edwinprins/Git/boilerplate/api/storage/.gitkeep
touch /Users/edwinprins/Git/boilerplate/db/.gitkeep
touch /Users/edwinprins/Git/boilerplate/docs/.gitkeep
```

- [ ] **Step 8: Verify PHP API works locally**

```bash
cd /Users/edwinprins/Git/boilerplate
cp api/config.example.php api/config.php
php -S localhost:8000 api/index.php &
sleep 1
curl -sS http://localhost:8000/api/
curl -sS -H "X-Api-Key: change-me" http://localhost:8000/api/items
kill %1
rm api/config.php
```

Expected first curl: `{"status":"ok","driver":"sqlite","time":"..."}`
Expected second curl: `{"error":"not_found","path":"/items","method":"GET"}`

- [ ] **Step 9: Commit**

```bash
git add api/ db/.gitkeep docs/.gitkeep
git commit -m "feat: PHP 8 flat-router API with SQLite/MySQL config and health endpoint"
```

---

## Task 6: Frontend API client + composable + wire HomeView to /health

**Files:**
- Create: `boilerplate/src/lib/api.js`
- Create: `boilerplate/src/composables/useApi.js`
- Create: `boilerplate/tests/lib/api.spec.js`
- Modify: `boilerplate/src/views/HomeView.vue` (fetch and display /health)

**Interfaces:**
- Consumes: `VITE_API_URL`, `VITE_API_KEY` env vars
- Produces:
  - `src/lib/api.js` exports async functions: `apiGet(path)`, `apiPost(path, body)`, `apiPatch(path, body)`, `apiDelete(path)`
  - On non-2xx: throws `Error` with `.status` and `.body` fields
  - `src/composables/useApi.js` exports `useApi()` returning `{ apiGet, apiPost, apiPatch, apiDelete }` (dun laag zodat je in Vue componenten `const { apiGet } = useApi()` schrijft, consistent met beide bestaande projecten)

- [ ] **Step 1: Create `src/lib/api.js`**

```js
const API_URL = (import.meta.env.VITE_API_URL ?? '/api').replace(/\/$/, '')
const API_KEY = import.meta.env.VITE_API_KEY ?? ''

function headers(extra = {}) {
  return {
    'Content-Type': 'application/json',
    'X-Api-Key': API_KEY,
    ...extra,
  }
}

async function request(path, init = {}) {
  const url = `${API_URL}${path}`
  const res = await fetch(url, {
    ...init,
    headers: { ...headers(), ...(init.headers ?? {}) },
  })

  if (res.status === 204) return null

  let body = null
  const contentType = res.headers.get('content-type') ?? ''
  if (contentType.includes('application/json')) {
    body = await res.json().catch(() => null)
  }

  if (!res.ok) {
    const err = new Error(body?.error || `HTTP ${res.status}`)
    err.status = res.status
    err.body = body
    throw err
  }

  return body
}

export function apiGet(path) {
  return request(path, { method: 'GET' })
}

export function apiPost(path, body) {
  return request(path, { method: 'POST', body: JSON.stringify(body) })
}

export function apiPatch(path, body) {
  return request(path, { method: 'PATCH', body: JSON.stringify(body) })
}

export function apiDelete(path) {
  return request(path, { method: 'DELETE' })
}
```

- [ ] **Step 2: Create `src/composables/useApi.js`**

```js
import { apiGet, apiPost, apiPatch, apiDelete } from '../lib/api.js'

export function useApi() {
  return { apiGet, apiPost, apiPatch, apiDelete }
}
```

- [ ] **Step 3: Write failing test `tests/lib/api.spec.js`**

```js
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { apiGet, apiPost } from '../../src/lib/api.js'

describe('lib/api', () => {
  beforeEach(() => {
    global.fetch = vi.fn()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('apiGet sends X-Api-Key header and returns JSON body', async () => {
    global.fetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ hello: 'world' }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    const result = await apiGet('/hello')
    expect(result).toEqual({ hello: 'world' })

    const [url, init] = global.fetch.mock.calls[0]
    expect(url).toBe('/api/hello')
    expect(init.headers['X-Api-Key']).toBe('test-key')
    expect(init.method).toBe('GET')
  })

  it('apiPost stringifies body and sets JSON content-type', async () => {
    global.fetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ ok: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    await apiPost('/items', { name: 'x' })

    const [, init] = global.fetch.mock.calls[0]
    expect(init.method).toBe('POST')
    expect(init.body).toBe(JSON.stringify({ name: 'x' }))
    expect(init.headers['Content-Type']).toBe('application/json')
  })

  it('throws an Error with status and body on non-2xx', async () => {
    global.fetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'bad' }), {
        status: 400,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    await expect(apiGet('/x')).rejects.toMatchObject({
      status: 400,
      body: { error: 'bad' },
      message: 'bad',
    })
  })

  it('returns null on 204', async () => {
    global.fetch.mockResolvedValueOnce(new Response(null, { status: 204 }))
    const result = await apiGet('/nothing')
    expect(result).toBeNull()
  })
})
```

- [ ] **Step 4: Run test to see it pass (implementation exists)**

Run: `cd /Users/edwinprins/Git/boilerplate && npx vitest run tests/lib/api.spec.js`
Expected: 4 tests pass.

- [ ] **Step 5: Update `src/views/HomeView.vue` to display /health**

```vue
<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'

const appName = 'Boilerplate'
const health = ref(null)
const error = ref(null)

const { apiGet } = useApi()

onMounted(async () => {
  try {
    health.value = await apiGet('/')
  } catch (e) {
    error.value = e.message
  }
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 flex items-center justify-center p-8">
    <div class="max-w-lg text-center space-y-4">
      <h1 class="text-4xl font-bold text-slate-800">{{ appName }}</h1>
      <p class="text-slate-600">Welkom bij je nieuwe app.</p>

      <div v-if="health" class="text-sm text-slate-700 bg-white rounded p-4 shadow-sm">
        <div>API status: <strong>{{ health.status }}</strong></div>
        <div>DB driver: <strong>{{ health.driver }}</strong></div>
      </div>

      <div v-else-if="error" class="text-sm text-red-600">
        API-fout: {{ error }}
      </div>

      <p class="text-sm text-slate-500">
        Zie <code class="bg-slate-200 px-1 rounded">README.md</code> voor volgende stappen.
      </p>
    </div>
  </main>
</template>
```

- [ ] **Step 6: Commit**

```bash
git add src/lib/api.js src/composables/useApi.js tests/lib/api.spec.js src/views/HomeView.vue
git commit -m "feat: frontend API client with tests and health display on HomeView"
```

---

## Task 7: Dev server (concurrently) + Vite proxy for /api

**Files:**
- Modify: `boilerplate/vite.config.js` (add server.proxy)
- Modify: `boilerplate/package.json` — already correct from Task 1, but verify `dev` uses `concurrently`

**Interfaces:**
- Consumes: `vite.config.js` from Task 4
- Produces: `/api/*` requests from Vite dev server are proxied to `http://localhost:8000/api/*`

- [ ] **Step 1: Update `vite.config.js`**

Replace file contents with:

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/',
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    env: {
      VITE_API_URL: '/api',
      VITE_API_KEY: 'test-key',
    },
  },
})
```

- [ ] **Step 2: Verify dev flow (both servers)**

```bash
cd /Users/edwinprins/Git/boilerplate
cp api/config.example.php api/config.php
npm run dev
```

Open `http://localhost:5173/` in browser. Expected: HomeView shows "API status: ok" and "DB driver: sqlite". Console shows no fetch errors.

Stop the servers (Ctrl-C).

- [ ] **Step 3: Clean up local config**

```bash
rm api/config.php
```

- [ ] **Step 4: Verify tests still pass**

Run: `npm run test -- --run`
Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add vite.config.js
git commit -m "feat: Vite proxy /api → localhost:8000 for parallel dev servers"
```

---

## Task 8: Custom Vite plugin — copy api/ into dist/api/ on build

**Files:**
- Create: `boilerplate/scripts/copy-api.js`
- Modify: `boilerplate/vite.config.js` (register plugin)

**Interfaces:**
- Consumes: Vite build pipeline
- Produces:
  - Default export from `scripts/copy-api.js`: `copyApiPlugin({ src, dest, exclude })`
  - Returns a Vite plugin object with `closeBundle` hook that runs after Vite's build

- [ ] **Step 1: Create `scripts/copy-api.js`**

```js
import { readdirSync, statSync, mkdirSync, copyFileSync, existsSync } from 'node:fs'
import { join, relative, sep, posix } from 'node:path'

function toPosix(p) {
  return p.split(sep).join(posix.sep)
}

function matchesGlob(pathPosix, pattern) {
  // Ondersteunt: exact match, wildcard * (geen /), dubbele ** (recursief)
  const re = new RegExp(
    '^' +
    pattern
      .replace(/[.+^${}()|[\]\\]/g, '\\$&')
      .replace(/\*\*/g, '::DOUBLE::')
      .replace(/\*/g, '[^/]*')
      .replace(/::DOUBLE::/g, '.*') +
    '$'
  )
  return re.test(pathPosix)
}

function isExcluded(relPath, excludes) {
  const p = toPosix(relPath)
  return excludes.some((pattern) => matchesGlob(p, pattern))
}

function walk(srcDir, destDir, excludes, srcRoot) {
  let copied = 0
  for (const entry of readdirSync(srcDir)) {
    const srcPath = join(srcDir, entry)
    const relPath = relative(srcRoot, srcPath)
    if (isExcluded(relPath, excludes)) continue

    const destPath = join(destDir, entry)
    const stat = statSync(srcPath)
    if (stat.isDirectory()) {
      if (!existsSync(destPath)) mkdirSync(destPath, { recursive: true })
      copied += walk(srcPath, destPath, excludes, srcRoot)
    } else {
      copyFileSync(srcPath, destPath)
      copied += 1
    }
  }
  return copied
}

export function copyApiPlugin(options = {}) {
  const src = options.src ?? 'api'
  const dest = options.dest ?? 'dist/api'
  const exclude = options.exclude ?? [
    'config.php',
    'storage/db.sqlite',
    'storage/db.sqlite-shm',
    'storage/db.sqlite-wal',
    'storage/tmp/**',
    '**/*.log',
  ]

  return {
    name: 'copy-api',
    apply: 'build',
    closeBundle() {
      if (!existsSync(src)) {
        this.warn(`copy-api: source directory "${src}" does not exist, skipping`)
        return
      }
      if (!existsSync(dest)) mkdirSync(dest, { recursive: true })
      const count = walk(src, dest, exclude, src)
      console.log(`[copy-api] copied ${count} files from ${src}/ → ${dest}/`)
    },
  }
}
```

- [ ] **Step 2: Update `vite.config.js` to register the plugin**

Replace file contents with:

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { copyApiPlugin } from './scripts/copy-api.js'

export default defineConfig({
  plugins: [
    vue(),
    copyApiPlugin({
      src: 'api',
      dest: 'dist/api',
      exclude: [
        'config.php',
        'storage/db.sqlite',
        'storage/db.sqlite-shm',
        'storage/db.sqlite-wal',
        'storage/tmp/**',
        '**/*.log',
      ],
    }),
  ],
  base: '/',
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    env: {
      VITE_API_URL: '/api',
      VITE_API_KEY: 'test-key',
    },
  },
})
```

- [ ] **Step 3: Verify build produces dist/api/**

```bash
cd /Users/edwinprins/Git/boilerplate
cp api/config.example.php api/config.php
touch api/storage/db.sqlite   # simuleer een lokale DB
npm run build
```

Expected output includes `[copy-api] copied N files from api/ → dist/api/`.

- [ ] **Step 4: Inspect dist/api/**

Run: `ls -R /Users/edwinprins/Git/boilerplate/dist/api/`

Expected contents:
- `index.php`, `config.example.php`, `.htaccess`
- `helpers/response.php`, `helpers/auth.php`
- `handlers/health.php`
- `storage/.gitkeep`

Expected **absent** from dist/api/:
- `config.php` (secret)
- `storage/db.sqlite` (local data)

Run: `test ! -f /Users/edwinprins/Git/boilerplate/dist/api/config.php && echo "OK: config.php not in dist"`
Run: `test ! -f /Users/edwinprins/Git/boilerplate/dist/api/storage/db.sqlite && echo "OK: sqlite not in dist"`

- [ ] **Step 5: Clean up build artifacts**

```bash
rm -rf dist/
rm api/config.php
rm api/storage/db.sqlite
```

- [ ] **Step 6: Commit**

```bash
git add scripts/copy-api.js vite.config.js
git commit -m "feat: custom Vite plugin copies api/ into dist/api/ on build"
```

---

## Task 9: Root .htaccess for SPA routing + API passthrough

**Files:**
- Create: `boilerplate/public/.htaccess`

**Interfaces:**
- Consumes: existing `api/.htaccess` from Task 5
- Produces: production-ready Apache config that Vite copies to `dist/.htaccess` automatically (Vite copies everything in `public/` to `dist/` at the same relative path)

- [ ] **Step 1: Create `public/.htaccess`**

```bash
mkdir -p /Users/edwinprins/Git/boilerplate/public
```

Then create `public/.htaccess` with:

```
RewriteEngine On
RewriteBase /

# Bestaande bestanden/mappen direct serveren
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# /api/* wordt door api/.htaccess afgehandeld
RewriteRule ^api/ - [L]

# Al het overige naar de Vue SPA
RewriteRule ^ index.html [L]

# Cache-control voor gehashte assets (Vite output)
<IfModule mod_headers.c>
    <FilesMatch "\.(js|css|woff2?|png|jpe?g|svg|webp)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
    <FilesMatch "\.html$">
        Header set Cache-Control "no-cache"
    </FilesMatch>
</IfModule>
```

- [ ] **Step 2: Verify build copies `.htaccess` to `dist/`**

```bash
cd /Users/edwinprins/Git/boilerplate
cp api/config.example.php api/config.php
npm run build
ls -la dist/.htaccess
rm -rf dist/
rm api/config.php
```

Expected: `dist/.htaccess` exists (copied by Vite from `public/`).

- [ ] **Step 3: Commit**

```bash
git add public/.htaccess
git commit -m "feat: root .htaccess for SPA routing and API passthrough (via public/)"
```

---

## Task 10: Setup script (`scripts/create-app.sh`)

**Files:**
- Create: `boilerplate/scripts/create-app.sh`

**Interfaces:**
- Consumes: entire boilerplate directory tree
- Produces: interactive script that scaffolds a new app copy

- [ ] **Step 1: Create `scripts/create-app.sh`**

```bash
#!/usr/bin/env bash
#
# Boilerplate setup-script.
# Kopieert deze boilerplate naar een nieuwe map, vervangt placeholders,
# en zet 'm klaar voor `npm install && npm run dev`.
#
# Gebruik: ./scripts/create-app.sh
#         of: ./scripts/create-app.sh ../nieuwe-app "Nieuwe App"

set -euo pipefail

# ─── Bepaal boilerplate root ─────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BOILER_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# ─── Argumenten of vragen ────────────────────────────────────────
TARGET_DIR="${1:-}"
APP_NAME="${2:-}"

if [[ -z "$TARGET_DIR" ]]; then
  read -r -p "Doelmap (bijv. ../mijn-app): " TARGET_DIR
fi
if [[ -z "$TARGET_DIR" ]]; then
  echo "Geen doelmap opgegeven — afgebroken."
  exit 1
fi

if [[ -e "$TARGET_DIR" ]]; then
  echo "Fout: $TARGET_DIR bestaat al."
  exit 1
fi

if [[ -z "$APP_NAME" ]]; then
  DEFAULT_NAME="$(basename "$TARGET_DIR")"
  read -r -p "App-naam [$DEFAULT_NAME]: " APP_NAME
  APP_NAME="${APP_NAME:-$DEFAULT_NAME}"
fi

read -r -p "Verse API_KEY genereren via openssl? [Y/n]: " GEN_KEY
GEN_KEY="${GEN_KEY:-Y}"

read -r -p "SQLite-DB nu aanmaken (empty)? [Y/n]: " MAKE_DB
MAKE_DB="${MAKE_DB:-Y}"

read -r -p "git init in nieuwe app? [Y/n]: " DO_GIT
DO_GIT="${DO_GIT:-Y}"

# ─── Kopieer ─────────────────────────────────────────────────────
echo "→ Kopieer $BOILER_ROOT → $TARGET_DIR"
mkdir -p "$TARGET_DIR"
# Kopieer alles behalve node_modules, dist, .git, .superpowers, api/config.php, api/storage/db.sqlite*
rsync -a \
  --exclude 'node_modules' \
  --exclude 'dist' \
  --exclude '.git' \
  --exclude '.superpowers' \
  --exclude '.worktrees' \
  --exclude 'api/config.php' \
  --exclude 'api/storage/db.sqlite*' \
  "$BOILER_ROOT/" "$TARGET_DIR/"

# ─── Placeholder vervanging ──────────────────────────────────────
echo "→ Vervang {{APP_NAME}} placeholders"
# package.json: name moet lowercase + geen spaties
NPM_NAME="$(echo "$APP_NAME" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')"
# BSD sed (macOS) vs GNU sed
if sed --version >/dev/null 2>&1; then
  SED_INPLACE=(sed -i)
else
  SED_INPLACE=(sed -i '')
fi

"${SED_INPLACE[@]}" "s|{{APP_NAME}}|$NPM_NAME|g" "$TARGET_DIR/package.json"
"${SED_INPLACE[@]}" "s|{{APP_NAME}}|$APP_NAME|g" "$TARGET_DIR/index.html"

# ─── API_KEY genereren ───────────────────────────────────────────
if [[ "$GEN_KEY" =~ ^[Yy]$ ]]; then
  if command -v openssl >/dev/null 2>&1; then
    NEW_KEY="$(openssl rand -hex 24)"
  else
    NEW_KEY="$(head -c 24 /dev/urandom | xxd -p)"
  fi
  echo "→ Genereer verse API_KEY"
  cp "$TARGET_DIR/api/config.example.php" "$TARGET_DIR/api/config.php"
  "${SED_INPLACE[@]}" "s|define('API_KEY', 'change-me');|define('API_KEY', '$NEW_KEY');|" \
    "$TARGET_DIR/api/config.php"
  cp "$TARGET_DIR/.env.example" "$TARGET_DIR/.env"
  "${SED_INPLACE[@]}" "s|VITE_API_KEY=change-me|VITE_API_KEY=$NEW_KEY|" \
    "$TARGET_DIR/.env"
fi

# ─── SQLite DB ───────────────────────────────────────────────────
if [[ "$MAKE_DB" =~ ^[Yy]$ ]]; then
  mkdir -p "$TARGET_DIR/api/storage"
  : > "$TARGET_DIR/api/storage/db.sqlite"
  echo "→ Lege SQLite DB aangemaakt op api/storage/db.sqlite"
fi

# ─── Git init ────────────────────────────────────────────────────
if [[ "$DO_GIT" =~ ^[Yy]$ ]]; then
  (
    cd "$TARGET_DIR"
    git init -b main >/dev/null
    git add .
    git commit -m "Initial commit (from boilerplate)" >/dev/null
  )
  echo "→ Git repo aangemaakt met eerste commit"
fi

# ─── Vervolgstappen ──────────────────────────────────────────────
cat <<EOF

✓ Klaar! Je nieuwe app staat in: $TARGET_DIR

Volgende stappen:

  cd $TARGET_DIR
  npm install
  npm run dev

Open dan http://localhost:5173/
EOF
```

- [ ] **Step 2: Make it executable**

```bash
chmod +x /Users/edwinprins/Git/boilerplate/scripts/create-app.sh
```

- [ ] **Step 3: Smoke test the script**

```bash
cd /Users/edwinprins/Git/boilerplate
./scripts/create-app.sh /tmp/boilerplate-test "Smoke Test" <<< $'Y\nY\nN'
```

Expected:
- Creates `/tmp/boilerplate-test/`
- Prints "✓ Klaar!"
- No git init (because we said N to last question... adjust HEREDOC as needed)

Alternative non-interactive test with all defaults:
```bash
rm -rf /tmp/boilerplate-test
printf 'Y\nY\nN\n' | ./scripts/create-app.sh /tmp/boilerplate-test "Smoke Test"
```

- [ ] **Step 4: Verify smoke test artifacts**

```bash
test -f /tmp/boilerplate-test/api/config.php && echo "OK: config.php exists"
test -f /tmp/boilerplate-test/.env && echo "OK: .env exists"
grep -q "smoke-test" /tmp/boilerplate-test/package.json && echo "OK: package.json name replaced"
grep -q "Smoke Test" /tmp/boilerplate-test/index.html && echo "OK: index.html title replaced"
test ! -d /tmp/boilerplate-test/node_modules && echo "OK: node_modules not copied"
test ! -d /tmp/boilerplate-test/.git && echo "OK: no .git (declined)"
```

- [ ] **Step 5: Clean up smoke test**

```bash
rm -rf /tmp/boilerplate-test
```

- [ ] **Step 6: Commit**

```bash
git add scripts/create-app.sh
git commit -m "feat: interactive create-app.sh setup script"
```

---

## Task 11: README.md

**Files:**
- Create: `boilerplate/README.md`

**Interfaces:**
- Consumes: entire scaffolded project
- Produces: NL-documentatie voor developers

- [ ] **Step 1: Create `README.md`**

````markdown
# Boilerplate — Vue 3 + PHP 8 monorepo

Een minimale, herbruikbare starter voor nieuwe applicaties.
Vue 3 frontend, PHP 8 flat-router API in `/api/`, SQLite lokaal, MySQL in productie.

## Snelle start

```bash
# 1. Kopieer of gebruik het setup-script
./scripts/create-app.sh ../mijn-nieuwe-app "Mijn Nieuwe App"
cd ../mijn-nieuwe-app

# 2. Installeer dependencies
npm install

# 3. Start ontwikkeling (Vite + PHP parallel)
npm run dev
```

Open daarna [http://localhost:5173/](http://localhost:5173/).

## Stack

| Laag | Technologie |
|---|---|
| Frontend | Vue 3 (Composition API, `<script setup>`), Vue Router 4 |
| Styling | Tailwind CSS 3.4 + PostCSS + Autoprefixer |
| Build | Vite 8 |
| Testing | Vitest + jsdom |
| Backend | PHP 8 (flat router, geen framework) |
| Database | SQLite lokaal, MySQL in productie (via `DB_DRIVER`) |
| Dev-server | `concurrently` draait Vite (5173) + PHP built-in (8000) parallel |

## Bestandsstructuur

```
.
├── api/                    PHP API
│   ├── index.php           Flat router
│   ├── config.example.php  DB + API_KEY config template
│   ├── handlers/           1 bestand per endpoint
│   └── helpers/            auth.php, response.php
├── src/                    Vue frontend
│   ├── main.js
│   ├── router/index.js
│   ├── lib/api.js          Fetch-wrapper met X-Api-Key
│   ├── composables/
│   └── views/
├── public/                 Static assets (bevat root .htaccess)
├── scripts/
│   ├── copy-api.js         Vite plugin: kopieert api/ → dist/api/
│   └── create-app.sh       Interactief setup-script
└── tests/                  Vitest specs
```

## Environment setup

1. Kopieer `.env.example` → `.env`, vul `VITE_API_KEY` in.
2. Kopieer `api/config.example.php` → `api/config.php`, vul `API_KEY` in
   (moet gelijk zijn aan `VITE_API_KEY`).
3. `npm install` — installeert Vue, Vite, Tailwind, Vitest, concurrently.

## Development

`npm run dev` start twee servers parallel:

- Vite op `http://localhost:5173` — serveert de Vue frontend
- PHP built-in server op `http://localhost:8000` — serveert `api/index.php`

Vite's proxy stuurt alle `/api/*` calls door naar de PHP-server. Je frontend
hoeft dus niet te weten dat de API elders draait — je fetcht gewoon `/api/…`.

Afzonderlijke scripts:
- `npm run dev:vite` — alleen Vite (handig als je MAMP/Herd gebruikt)
- `npm run dev:php` — alleen PHP (handig als je Vite los draait)

## Build & deploy

```bash
npm run build
```

Levert een complete `dist/` map op met:

- `index.html` + gehashte JS/CSS bundles (frontend)
- `api/` (PHP-bestanden, zonder `config.php` of SQLite-data)
- `.htaccess` (SPA-routing + API passthrough)

**Deploy-flow:**

1. Upload de inhoud van `dist/` naar je webroot.
2. Op de server: kopieer `api/config.example.php` naar `api/config.php` en
   vul productie-credentials in. Zet `DB_DRIVER` op `'mysql'`.
3. Zorg dat MySQL bereikbaar is met de opgegeven credentials.
4. Test de API met `curl https://jouw-domein/api/`.

## Database

**Lokaal (SQLite):**
`DB_DRIVER = 'sqlite'` in `api/config.php`. De DB-file komt in
`api/storage/db.sqlite` (gitignored). Wordt automatisch aangemaakt als 'ie nog
niet bestaat.

**Productie (MySQL):**
`DB_DRIVER = 'mysql'`. Vul `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` in.

**Schema:**
Ontwerp je schema in `db/schema.sql`. Zie sectie "SQL portabiliteit"
hieronder voor tips.

### SQL portabiliteit — SQLite ↔ MySQL

Een handvol verschillen om rekening mee te houden als je met beide werkt:

| Feature | SQLite | MySQL |
|---|---|---|
| Auto-increment PK | `INTEGER PRIMARY KEY AUTOINCREMENT` | `INT AUTO_INCREMENT PRIMARY KEY` |
| Enum type | Geen (gebruik `TEXT` + CHECK constraint) | `ENUM('a','b')` |
| Boolean | `INTEGER` (0/1) | `TINYINT(1)` of `BOOLEAN` |
| Datum-defaults | `DEFAULT CURRENT_TIMESTAMP` | idem |
| JSON | `TEXT` (parse in code) of `JSON` (>=3.38) | `JSON` |
| Character set | Altijd UTF-8 | Zet `charset=utf8mb4` in DSN |

**Aanbeveling:** schrijf schema in "portable SQL" — vermijd `ENUM`, gebruik
`INTEGER PRIMARY KEY` (werkt in beide), quote nooit identifiers met backticks
(gebruik dubbele quotes als je 't nodig hebt).

Alternatief: houd twee schema-bestanden (`db/schema.sqlite.sql`,
`db/schema.mysql.sql`) synchroon.

## Auth (nog niet geactiveerd)

De boilerplate levert alleen **X-Api-Key** authenticatie (`requireApiKey()` in
PHP + auto-header in `src/lib/api.js`). Voor user-level auth (Supabase, Firebase,
session) staan er drie hook-points klaar met TODO's:

1. `src/router/index.js` — commented-out `requireAuth` guard
2. `src/views/LoginView.vue` — skeleton met provider-buttons
3. `api/helpers/auth.php` — commented-out `requireUser()` stub

Zie `CLAUDE.md` sectie "Auth toevoegen" voor concrete voorbeelden.

## Multi-tool uitbreiding

Voor een dashboard met meerdere tools onder één auth (zoals `inventus-tools`):
zie `CLAUDE.md` sectie "Multi-tool patroon".

## Testen

```bash
npm run test          # Vitest watch mode
npm run test -- --run # Éénmalige run (CI)
```

Voorbeeld test in `tests/lib/api.spec.js` — kopieer 't patroon voor je eigen
composables.

## Troubleshooting

**"missing_config" bij API-calls:**
`api/config.php` bestaat niet. Kopieer `api/config.example.php`.

**"invalid_api_key":**
`VITE_API_KEY` in `.env` en `API_KEY` in `api/config.php` moeten identiek zijn.

**PHP built-in server geeft "404 Not Found" op assets:**
`php -S localhost:8000 api/index.php` — de tweede argument is een router-script,
alle requests komen daar binnen. Als je een asset wilt serveren, doe het via
Vite's dev-server, niet direct via PHP.

**SQLite-fouten "unable to open database file":**
Directory `api/storage/` bestaat niet of is niet writable. Fix: `mkdir -p
api/storage && chmod 755 api/storage`.
````

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: comprehensive README.md in Dutch"
```

---

## Task 12: CLAUDE.md

**Files:**
- Create: `boilerplate/CLAUDE.md`

**Interfaces:**
- Consumes: complete scaffolded project
- Produces: instructies voor Claude Code sessies

- [ ] **Step 1: Create `CLAUDE.md`**

````markdown
# CLAUDE.md — instructies voor Claude Code

Deze file leest Claude bij het starten van een sessie in dit project. Houd 'm
kort en concreet.

## Commands

```bash
npm run dev         # Vite (5173) + PHP (8000) parallel via concurrently
npm run dev:vite    # Alleen Vite
npm run dev:php     # Alleen PHP built-in server
npm run build       # Frontend + api/ → dist/
npm run preview     # Preview de gebouwde dist/
npm run test        # Vitest
```

## Stack

Vue 3 (Composition API, `<script setup>`) · Vue Router · Vite · Tailwind ·
Vitest · PHP 8 flat router · PDO (SQLite lokaal, MySQL productie).

## Architectuur op één pagina

```
Vue frontend (src/)
  └─ fetch('/api/...') via src/lib/api.js
      └─ Vite proxy (dev) of Apache rewrite (prod)
          └─ api/index.php  ← flat router met stages
              ├─ Stage 1: publieke endpoints (health, share links, ...)
              ├─ Stage 2: requireApiKey() gate
              └─ Stage 3: beschermde endpoints
                  └─ elk endpoint = 1 handler in api/handlers/
```

## Patronen die je MOET volgen

### Nieuwe API-endpoint toevoegen

1. Maak een handler in `api/handlers/<naam>.php`. Voorbeeld:
   ```php
   <?php
   $db = getDb();
   $rows = $db->query('SELECT * FROM items')->fetchAll();
   jsonResponse($rows);
   ```
2. Mount 'm in `api/index.php`:
   ```php
   if ($method === 'GET' && $path === '/items') {
       require __DIR__ . '/handlers/items-list.php';
       exit;
   }
   ```
3. Roep 'm aan vanuit een composable in `src/composables/`:
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

**ALTIJD** via `src/lib/api.js` — nooit bare `fetch()`. Reden: `X-Api-Key`
wordt daar automatisch gezet, fouten uniform gethrowd.

### PHP database access

**ALTIJD** via `getDb()` uit `api/config.php` — nooit bare `new PDO()`.
Reden: DB_DRIVER-schakeling zit in `getDb()`, en de connectie wordt gecached.

### Nieuwe Vue view/route

1. Maak `src/views/MijnView.vue`.
2. Registreer in `src/router/index.js`:
   ```js
   { path: '/mijn', component: () => import('../views/MijnView.vue'), meta: { title: 'Mijn' } }
   ```

## SQL portabiliteit (SQLite ↔ MySQL)

Bij ontwerp van schema's — beide moeten werken.

| Doe | Niet doe |
|---|---|
| `INTEGER PRIMARY KEY` | `INT AUTO_INCREMENT PRIMARY KEY` (MySQL-only) |
| `TEXT` + CHECK constraint | `ENUM('a','b')` (MySQL-only) |
| `INTEGER` voor booleans | `BOOLEAN` (verschilt per DB) |
| `DATETIME DEFAULT CURRENT_TIMESTAMP` | `TIMESTAMP` met `ON UPDATE` (MySQL-only) |
| Dubbele quotes voor identifiers | Backticks (MySQL-only) |

Zie `README.md` sectie "SQL portabiliteit" voor de complete tabel.

## Auth toevoegen

Drie hook-points liggen klaar:

### 1. Router guard — `src/router/index.js`

Uncomment de `requireAuth` functie en implementeer 'm. Bijvoorbeeld
Supabase:

```js
import { supabase } from '../lib/supabase.js'

export async function requireAuth(to, from, next) {
    const { data } = await supabase.auth.getSession()
    if (!data.session) {
        return next({ path: '/login', query: { redirect: to.fullPath } })
    }
    next()
}
```

Voeg dan `beforeEnter: requireAuth` toe aan de routes die je wilt
beschermen.

### 2. LoginView — `src/views/LoginView.vue`

Implementeer de `loginWithProvider(provider)` functie. Bijvoorbeeld
Supabase OAuth:

```js
async function loginWithProvider(provider) {
    await supabase.auth.signInWithOAuth({
        provider,
        options: { redirectTo: window.location.origin + '/' },
    })
}
```

### 3. PHP requireUser() — `api/helpers/auth.php`

Uncomment en implementeer. Bijvoorbeeld voor Supabase (haalt user op via
Supabase's `/auth/v1/user` endpoint met bearer token):

```php
function requireUser(): array {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/', $auth, $m)) {
        errorResponse('unauthorized', 401);
    }
    $token = $m[1];

    $ch = curl_init(SUPABASE_URL . '/auth/v1/user');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) errorResponse('unauthorized', 401);
    return json_decode($body, true);
}
```

Roep 'm dan aan in beschermde handlers:

```php
$user = requireUser();
$userId = $user['id'];
```

## Multi-tool patroon (optioneel)

Als deze app een dashboard met meerdere tools wordt (à la `inventus-tools`):

1. Maak `src/tools.js`:
   ```js
   export const tools = [
     { id: 'foo', label: 'Foo', description: '...', route: '/tools/foo' },
     { id: 'bar', label: 'Bar', description: '...', route: '/tools/bar' },
   ]
   ```
2. Maak `src/views/tools/foo/index.vue` per tool.
3. Registreer routes: `path: '/tools/foo'`, `component: () => import('../views/tools/foo/index.vue')`.
4. Laat `HomeView.vue` de tools uit `tools.js` als tegels renderen.

## Wat NIET te doen

- **Geen framework toevoegen** (Laravel, Symfony, Slim). Flat router is bewust.
- **Geen npm-packages toevoegen zonder overleg** met de user. Baseline is
  klein om een reden.
- **Geen `@supabase/supabase-js` in baseline**. Als je Supabase nodig hebt,
  voeg 't per-project toe.
- **Geen ORM** (Eloquent, Doctrine). Ruwe PDO met prepared statements.
- **Geen bare `fetch()` in views** — altijd via `src/lib/api.js`.
- **Geen bare `new PDO()`** — altijd via `getDb()`.
- **Niet buiten de bestaande bestandsstructuur** afwijken zonder eerst
  te bespreken.

## Beslissingsgeschiedenis

- **Waarom SQLite lokaal en MySQL prod?** SQLite = zero-config voor dev.
  MySQL = wat prod-hosting nu eenmaal is. `DB_DRIVER`-toggle is bewust simpel.
- **Waarom `/api/` in `/dist/`?** Zodat `dist/` één deploy-artifact is —
  je uploadt het en klaar. Geen aparte deploy-stap voor de API.
- **Waarom geen Supabase in baseline?** Elke app kiest zijn eigen auth. De
  baseline moet auth-agnostisch zijn.
- **Waarom `concurrently`?** Vite en PHP zijn twee losse processen; één
  npm-script moet ze allebei starten. Alternatieven (Foreman, tmuxinator)
  voegen buiten-npm-tools toe — `concurrently` is een dev-dep en werkt overal.
````

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: CLAUDE.md with patterns, auth hook-points, and multi-tool guide"
```

---

## Task 13: End-to-end smoke test

**Files:** none (verification only)

**Interfaces:**
- Consumes: complete boilerplate
- Produces: confidence that fresh clone → running app works

- [ ] **Step 1: Run create-app.sh into a temp location**

```bash
cd /Users/edwinprins/Git/boilerplate
rm -rf /tmp/boilerplate-e2e
printf 'Y\nY\nY\n' | ./scripts/create-app.sh /tmp/boilerplate-e2e "E2E Test"
```

Expected:
- Prints "✓ Klaar!"
- `/tmp/boilerplate-e2e/` exists
- `.env`, `api/config.php` exist
- git repo initialized

- [ ] **Step 2: npm install in the fresh app**

```bash
cd /tmp/boilerplate-e2e
npm install
```

Expected: completes without errors.

- [ ] **Step 3: Start dev servers**

```bash
npm run dev &
DEV_PID=$!
sleep 3
```

- [ ] **Step 4: Hit endpoints**

```bash
# Direct on PHP
curl -sS http://localhost:8000/api/
# Through Vite proxy (frontend does this)
curl -sS http://localhost:5173/api/
```

Expected: both return `{"status":"ok","driver":"sqlite","time":"..."}`.

- [ ] **Step 5: Verify frontend HTML loads**

```bash
curl -sS http://localhost:5173/ | grep -q 'id="app"' && echo "OK: root HTML loads"
```

- [ ] **Step 6: Kill dev servers**

```bash
kill $DEV_PID
# Also kill any orphaned child processes
pkill -f "vite" 2>/dev/null || true
pkill -f "php -S localhost:8000" 2>/dev/null || true
```

- [ ] **Step 7: Run tests in the fresh app**

```bash
cd /tmp/boilerplate-e2e
npm run test -- --run
```

Expected: 4 tests pass.

- [ ] **Step 8: Build and inspect dist**

```bash
npm run build
ls -la dist/
ls -la dist/api/
test -f dist/index.html && echo "OK: frontend built"
test -f dist/api/index.php && echo "OK: API in dist"
test ! -f dist/api/config.php && echo "OK: config.php excluded"
test -f dist/.htaccess && echo "OK: root .htaccess in dist"
```

- [ ] **Step 9: Clean up**

```bash
rm -rf /tmp/boilerplate-e2e
```

- [ ] **Step 10: Final commit (if anything changed)**

Nothing should have changed during e2e — the boilerplate stayed intact.
If something surprised you: fix in the boilerplate and commit.

```bash
cd /Users/edwinprins/Git/boilerplate
git status  # should be clean
```

---

## Post-implementation checklist

- [ ] All 13 tasks green
- [ ] `npm run dev`, `npm run build`, `npm run test` all work
- [ ] `dist/` contains frontend + `api/` after build
- [ ] `dist/api/config.php` is **absent** after build
- [ ] `dist/api/storage/db.sqlite` is **absent** after build
- [ ] `README.md` and `CLAUDE.md` are complete in Dutch
- [ ] `scripts/create-app.sh` is executable and works with defaults
- [ ] Auth hook-points are commented-out but discoverable via grep for "AUTH HOOK-POINT"
- [ ] Git log shows one commit per task (readable history)
