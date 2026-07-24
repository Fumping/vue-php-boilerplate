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
