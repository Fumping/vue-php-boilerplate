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
│   ├── config.example.php  DB, sessie- en bootstrapconfig
│   ├── cli/                Eenmalige beheercommando's
│   ├── handlers/           1 bestand per endpoint
│   └── helpers/            auth.php, response.php
├── db/
│   ├── schema.sqlite.sql   Automatisch lokaal schema
│   └── schema.mysql.sql    Eenmalig productie-importbestand
├── src/                    Vue frontend
│   ├── main.js
│   ├── router/index.js
│   ├── lib/api.js          Fetch-wrapper met sessie en CSRF
│   ├── composables/
│   └── views/
├── public/                 Static assets (bevat root .htaccess)
├── scripts/
│   ├── copy-api.js         Vite plugin: kopieert api/ → dist/api/
│   └── create-app.sh       Interactief setup-script
└── tests/                  Vitest specs
```

## Environment setup

1. Kopieer `.env.example` → `.env`.
2. Kopieer `api/config.example.php` → `api/config.php`.
3. `npm install` — installeert Vue, Vite, Tailwind, Vitest, concurrently.

Bij de eerste API-call wordt lokaal gebruiker `admin` met wachtwoord `admin`
aangemaakt. Deze development-bootstrap moet bij de eerste login verplicht
worden gewijzigd.

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

1. Maak de productiedatabase aan en importeer het schema met een
   databasebeheerder:
   ```bash
   mysql -h DB_HOST -u MIGRATION_USER -p DB_NAME < db/schema.mysql.sql
   ```
2. Geef de runtime-DB-gebruiker daarna uitsluitend `SELECT`, `INSERT`, `UPDATE`
   en `DELETE` op deze database; geen `CREATE`, `ALTER` of `DROP`.
3. Upload de inhoud van `dist/` naar je webroot.
4. Kopieer `api/config.example.php` naar een map **buiten de webroot** en stel
   de servervariabele `APP_CONFIG_PATH` in op dat absolute bestandspad.
5. Zet daarin `APP_ENV` op `'production'`, `SESSION_COOKIE_SECURE` op `true`,
   `DB_DRIVER` op `'mysql'` en vul de runtimecredentials in. Beperk
   het configuratiebestand tot de webservergebruiker (`chmod 600`).
6. Maak de eerste productieadmin eenmalig aan zonder het wachtwoord in SQL of
   shellgeschiedenis op te slaan:
   ```bash
   read -s ADMIN_PASSWORD
   export ADMIN_PASSWORD
   APP_CONFIG_PATH=/veilig/pad/api.php php dist/api/cli/bootstrap-admin.php admin
   unset ADMIN_PASSWORD
   ```
7. Serveer de applicatie uitsluitend via HTTPS en test
   `curl https://jouw-domein/api/`.

## Database

**Lokaal (SQLite):**
`DB_DRIVER = 'sqlite'` in `api/config.php`. De DB-file komt in
`api/storage/db.sqlite` (gitignored). Wordt automatisch aangemaakt als 'ie nog
niet bestaat.

**Productie (MySQL):**
Importeer `db/schema.mysql.sql` vóór de eerste API-call. De API voert in
productie geen `CREATE` of `ALTER` uit en weigert te starten bij een ontbrekende
of verkeerde schemaversie.

**Schema:**
`db/schema.sqlite.sql` is voor lokale ontwikkeling; `db/schema.mysql.sql` voor
productie. Verhoog bij latere migraties ook `DATABASE_SCHEMA_VERSION` in
`api/helpers/database.php`.

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

## Authenticatie en gebruikers

De boilerplate bevat server-side PHP-sessies met:

- HttpOnly-, SameSite- en in productie Secure-cookies
- CSRF-validatie op alle mutaties
- `password_hash()`/`password_verify()` en verplichte eerste wijziging
- rate limiting op mislukte logins
- server-side rolcontrole voor `/api/admin/*`

Ga naar `/login` om in te loggen. Admins beheren gebruikers via
`/admin/users`. Nieuwe gebruikers krijgen een tijdelijk wachtwoord van minimaal
12 tekens en moeten dit bij de eerste login wijzigen.

De Vue-routerguard is alleen navigatiegemak; iedere beveiligde API-handler moet
altijd `requireUser($db)` of `requireAdmin($db)` aanroepen.

## Multi-tool uitbreiding

Voor een dashboard met meerdere tools onder één auth (zoals `inventus-tools`):
zie `CLAUDE.md` sectie "Multi-tool patroon".

## Testen

```bash
npm run test          # Vitest watch mode
npm run test -- --run # Éénmalige run (CI)
npm run test:ci       # Frontend + PHP-authtests
```

Voorbeeld test in `tests/lib/api.spec.js` — kopieer 't patroon voor je eigen
composables.

## Troubleshooting

**"server_not_configured" bij API-calls:**
`api/config.php` bestaat niet. Kopieer `api/config.example.php`.

**"Database-schema ontbreekt" in productie:**
Importeer `db/schema.mysql.sql` met een migratiegebruiker. Geef de
runtimegebruiker geen DDL-rechten.

**Na vijf foutieve logins volgt HTTP 429:**
Wacht 15 minuten. De teller staat in de tabel `auth_login_attempts`.

**PHP built-in server geeft "404 Not Found" op assets:**
`php -S localhost:8000 api/index.php` — de tweede argument is een router-script,
alle requests komen daar binnen. Als je een asset wilt serveren, doe het via
Vite's dev-server, niet direct via PHP.

**SQLite-fouten "unable to open database file":**
Directory `api/storage/` bestaat niet of is niet writable. Fix: `mkdir -p
api/storage && chmod 700 api/storage`.
