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
