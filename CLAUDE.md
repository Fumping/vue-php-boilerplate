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
          └─ api/index.php  ← flat router
              ├─ publieke endpoints (health, login, auth/me)
              ├─ requireUser($db) voor ingelogde gebruikers
              └─ requireAdmin($db) voor beheer
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

**ALTIJD** via `src/lib/api.js` — nooit bare `fetch()`. Reden: sessiecookies,
CSRF-headers en fouten worden daar uniform afgehandeld.

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

## Auth

Auth is standaard actief via PHP-sessies. Regels:

- Gebruik `requireUser($db)` in iedere ingelogde handler.
- Gebruik `requireAdmin($db)` in iedere adminhandler.
- Roep na `requireUser`/`requireAdmin` ook `requireCsrfToken()` aan bij
  POST-, PUT-, PATCH- en DELETE-mutaties.
- Vertrouw nooit alleen op Vue-routermeta; dat is geen serverbeveiliging.
- Nieuwe wachtwoorden hebben 12–200 tekens en worden uitsluitend met
  `password_hash()` opgeslagen.
- De bootstrap `admin`/`admin` is alleen voor development en vereist direct een
  wachtwoordwijziging. Productieconfiguratie weigert deze combinatie.

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
- **Waarom PHP-sessies?** Ze houden credentials uit de browserbundle en bieden
  zonder extra packages een kleine, server-side beveiligingsgrens.
- **Waarom `concurrently`?** Vite en PHP zijn twee losse processen; één
  npm-script moet ze allebei starten. Alternatieven (Foreman, tmuxinator)
  voegen buiten-npm-tools toe — `concurrently` is een dev-dep en werkt overal.
