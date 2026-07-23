# Boilerplate voor nieuwe applicaties — ontwerp

**Datum:** 2026-07-23
**Status:** Ontwerp goedgekeurd door user, klaar voor implementatieplan
**Doelmap:** `/Users/edwinprins/Git/boilerplate/`

## Doel

Een herbruikbare startbasis voor nieuwe applicaties, gebaseerd op de gedeelde patronen tussen `datumprikker` en `inventus-tools`, maar zonder de projectspecifieke ballast. De boilerplate levert een werkende Vue 3 + PHP 8 monorepo waarmee je binnen minuten een nieuwe app kunt starten.

## Achtergrond

De twee bestaande projecten delen 90% van hun structuur maar zijn nooit geëxtraheerd naar een gedeelde basis. Deze boilerplate is die extractie — plus twee toevoegingen die de user expliciet wenst:

1. `/api/` wordt bij `npm run build` mee in `/dist/` gekopieerd (in beide bestaande projecten blijft `/api/` in de root)
2. Lokale ontwikkeling gebruikt SQLite; productie gebruikt MySQL, geschakeld via één config-constante

## Niet-doelen (YAGNI)

- **Geen Supabase-integratie** in de baseline. Auth-integratie is expliciet uit scope; er zijn slechts hook-points en TODO-markers.
- **Geen tools-registry** in de baseline. Multi-tool uitbreiding is gedocumenteerd maar niet gescaffold.
- **Geen ORM, Laravel, Symfony.** De PHP-API blijft een flat router (bewuste keuze conform beide bestaande projecten).
- **Geen CI/CD-templates, geen Docker.** Uit scope voor v1.
- **Geen npm-packages** buiten de minimum-set (Vue, Vue Router, Vite, Tailwind, PostCSS, Autoprefixer, Vitest, jsdom, concurrently).

## Stack

| Laag | Keuze |
|---|---|
| Frontend | Vue 3 (Composition API, `<script setup>`), Vue Router 4 |
| Styling | Tailwind CSS + PostCSS + Autoprefixer |
| Build | Vite |
| Testing | Vitest + jsdom |
| Backend | PHP 8 (flat router, geen framework) |
| Database | SQLite lokaal, MySQL productie, beide via PDO |
| Dev-server | `concurrently` draait Vite (5173) + PHP built-in (8000) parallel |

## Bestandsstructuur

```
boilerplate/
├── .env.example
├── .gitignore
├── .htaccess                     # SPA routing + /api/ passthrough
├── README.md                     # NL, uitgebreid
├── CLAUDE.md                     # Instructies voor Claude Code sessies
├── index.html
├── package.json
├── postcss.config.js
├── tailwind.config.js
├── vite.config.js                # Inclusief copyApiPlugin + proxy
├── scripts/
│   ├── copy-api.js               # Vite plugin die api/ → dist/api/ kopieert
│   └── create-app.sh             # Optioneel interactief setup-script
├── api/
│   ├── .htaccess                 # Blokkeert directe toegang tot config.php
│   ├── index.php                 # Flat router met stages (public → auth)
│   ├── config.example.php        # DB_DRIVER + API_KEY + getDb() helper
│   ├── storage/
│   │   └── .gitkeep              # SQLite komt hier; gitignored
│   ├── helpers/
│   │   ├── auth.php              # requireApiKey() + TODO stub requireUser()
│   │   └── response.php          # jsonResponse(), errorResponse()
│   └── handlers/
│       └── health.php            # GET / → { status: "ok" }
├── db/
│   └── .gitkeep                  # Voor app-specifiek schema.sql
├── docs/
│   └── .gitkeep
└── src/
    ├── main.js
    ├── App.vue
    ├── style.css                 # Tailwind directives
    ├── router/
    │   └── index.js              # requireAuth guard commented-out
    ├── lib/
    │   └── api.js                # fetch-wrapper met X-Api-Key header
    ├── composables/
    │   └── useApi.js             # Voorbeeld CRUD-composable
    ├── views/
    │   ├── HomeView.vue          # Placeholder
    │   └── LoginView.vue         # Skeleton met TODO-blok
    └── components/
        └── .gitkeep
```

## Frontend architectuur

### Routing
- Vue Router 4, `createWebHistory`
- Twee routes uit de doos: `/` (HomeView), `/login` (LoginView)
- `requireAuth` guard staat commented-out in `router/index.js` met TODO-blok dat uitlegt hoe je 'm activeert

### API-communicatie
- Alle fetches via `src/lib/api.js`
- `lib/api.js` zet automatisch `X-Api-Key` header (uit `VITE_API_KEY`) en `Content-Type: application/json`
- Gooit `Error` bij non-2xx responses, met body als `error.body`
- Composables in `src/composables/` volgen het `useXxxApi()` patroon uit inventus-tools (met 204-support en JSON-parsing)

### Auth hook-points
Drie duidelijke plekken waar auth aangehaakt kan worden:
1. **`src/router/index.js`** — de commented-out `requireAuth` guard
2. **`src/views/LoginView.vue`** — skeleton met provider-slots
3. **`api/helpers/auth.php`** — `requireUser()` stub met TODO-comment

CLAUDE.md documenteert deze drie plekken en geeft voorbeelden voor Supabase, Firebase, session-based auth.

## Backend architectuur

### Router (`api/index.php`)
- Flat if/elseif chain met stages, patroon gekopieerd van datumprikker
- Stages:
  1. CORS headers + OPTIONS
  2. Publieke endpoints (voorbeeld: `GET /` health)
  3. `requireApiKey()` gate
  4. Beschermde endpoints
  5. 404

### Config (`api/config.example.php`)
```php
define('API_KEY', 'change-me');
define('DB_DRIVER', 'sqlite');  // of 'mysql'
define('DB_SQLITE_PATH', __DIR__ . '/storage/db.sqlite');
define('DB_HOST', 'localhost');
define('DB_NAME', 'my_database');
define('DB_USER', 'my_user');
define('DB_PASS', 'my_password');
```

### `getDb(): PDO`
- Static cache (één connectie per request)
- Bij SQLite: maakt de directory aan indien nodig, zet `PRAGMA foreign_keys = ON`
- Bij MySQL: DSN met utf8mb4 charset
- Beide: `ERRMODE_EXCEPTION` en `FETCH_ASSOC`

### Helpers
- `helpers/auth.php` — `requireApiKey()` werkt, `requireUser()` is stub
- `helpers/response.php` — `jsonResponse($data, $status = 200)`, `errorResponse($message, $status)`

### Handlers
- Één handler per endpoint in `api/handlers/`
- Voorbeeld: `handlers/health.php` (voor `GET /`)
- Nieuwe endpoint = handler-bestand + regel in `index.php`

## Build pipeline

### `vite.config.js`
```js
export default defineConfig({
  plugins: [vue(), copyApiPlugin({
    src: 'api',
    dest: 'dist/api',
    exclude: ['config.php', 'storage/db.sqlite*', 'storage/tmp/**', '**/*.log'],
  })],
  server: {
    port: 5173,
    proxy: { '/api': { target: 'http://localhost:8000', changeOrigin: true } },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    env: { VITE_API_URL: 'http://test/api/', VITE_API_KEY: 'test-key' },
  },
})
```

### `scripts/copy-api.js` (custom Vite plugin)
- Hook: `closeBundle` (draait éénmalig na build)
- Kopieert `api/` recursief naar `dist/api/`
- Respecteert exclude-lijst (glob patterns)
- Overschrijft `config.example.php` niet
- Slaat `config.php` en SQLite-bestanden altijd over (secrets/data horen niet in dist)
- Logt aantal gekopieerde bestanden

### `.htaccess` (root)
- SPA-routing: onbekende paden → `index.html`
- `/api/*` gaat door naar `api/index.php` (via zijn eigen rewrite)

### `api/.htaccess`
- Blokkeert directe HTTP-toegang tot `config.php`, `config.example.php`, SQLite-bestanden
- Rewrite die alle non-existent paden binnen `/api/` naar `index.php` stuurt

## Dev-flow

### `npm run dev`
`concurrently -k -n vite,php -c cyan,magenta "vite" "php -S localhost:8000 -t api api/index.php"`

- Vite op 5173 (frontend)
- PHP built-in server op 8000, met `api/index.php` als router-script
- Vite proxy forward `/api/*` naar `localhost:8000`
- Frontend hoeft niet te weten dat de API elders draait

### Losse scripts (voor devs zonder PHP CLI of met MAMP/Herd)
- `npm run dev:vite` — alleen Vite
- `npm run dev:php` — alleen PHP-server

## Setup-script (`scripts/create-app.sh`)

Interactief bash-script, optioneel te draaien. Vraagt:
1. Doelmap (bijv. `../mijn-app`)
2. App-naam (voor `package.json`, `<title>`, README-header)
3. Verse SQLite-DB aanmaken? (y/n)
4. Verse `API_KEY` genereren via `openssl rand -hex 24`? (default y)
5. `git init` draaien? (y/n)

Wat het script doet:
- `cp -r` van boilerplate naar doelmap, exclusief `node_modules/`, `dist/`, `.git/`, `.superpowers/`
- Placeholder-vervanging in `package.json` (`{{APP_NAME}}`), `index.html`, `api/config.php`, `README.md`
- `cp api/config.example.php api/config.php` en injecteert API_KEY
- `cp .env.example .env`
- Optioneel `git init && git add . && git commit -m "Initial commit from boilerplate"`
- Print vervolgstappen naar stdout

Zonder script kan de user ook handmatig: `cp -r boilerplate mijn-app && cd mijn-app && npm install && cp .env.example .env && cp api/config.example.php api/config.php && npm run dev`.

## Documentatie

### README.md (uitgebreid, NL)
Secties:
- Snelle start
- Stack
- Development (dev-server, SQLite locatie, proxy-uitleg)
- Build & deploy (dist bevat frontend + API, upload-flow)
- Auth (niet geactiveerd, verwijzing naar CLAUDE.md)
- SQL portabiliteit SQLite ↔ MySQL (tabel met verschillen: AUTOINCREMENT, ENUM, TIMESTAMP-defaults, JSON, BOOLEAN)
- Multi-tool uitbreiding (verwijzing naar CLAUDE.md)
- Troubleshooting

### CLAUDE.md
Secties:
- Commands
- Architectuur
- Patronen die te volgen zijn (handler-per-endpoint, composables via `useXxxApi`, fetch via `lib/api.js`, PDO via `getDb()`)
- SQL portabiliteit cheat-sheet
- Auth toevoegen (3 hook-points, voorbeelden Supabase/Firebase/session)
- Multi-tool patroon (optioneel, met concrete stappen)
- Wat NIET te doen

## `.gitignore`

```
node_modules/
dist/
.env
.env.local
api/config.php
api/storage/db.sqlite
api/storage/db.sqlite-shm
api/storage/db.sqlite-wal
api/storage/tmp/
.DS_Store
.superpowers/
```

## Dependencies

**Runtime:**
- `vue`, `vue-router`, `@supabase/supabase-js` **niet** in baseline (Supabase eruit)
- Alleen `vue`, `vue-router`

**Dev:**
- `vite`, `@vitejs/plugin-vue`
- `tailwindcss`, `postcss`, `autoprefixer`
- `vitest`, `jsdom`
- `concurrently`

## Testing

- Vitest config in `vite.config.js` (`test:` block)
- `jsdom` environment
- Voorbeeld test: `tests/lib/api.spec.js` die `lib/api.js` test met `vi.fn()` voor `fetch`
- Testcommand: `npm run test`

## Beslissingen die vast staan

1. Geen Supabase in baseline — auth is hook-point
2. `/api/` wél in `/dist/` via custom Vite plugin
3. SQLite lokaal, MySQL prod, via `DB_DRIVER` constant
4. `concurrently` voor dev-server (Vite + PHP parallel)
5. Setup-script is optioneel, handmatige flow moet ook triviaal blijven
6. Multi-tool patroon: alleen gedocumenteerd, niet gescaffold
7. Locatie: `/Users/edwinprins/Git/boilerplate/`

## Vervolg

Na goedkeuring van deze spec: over naar `writing-plans` om een implementatieplan (stap-voor-stap) te schrijven dat elk bestand in `boilerplate/` concreet neerlegt.
