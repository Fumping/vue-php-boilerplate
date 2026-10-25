# Boilerplate: Vue 3 + PHP 8 monorepo

A small, reusable starter for new applications: a Vue 3 frontend, a PHP 8
flat-router API in `/api/`, SQLite for local development and MySQL in
production.

## Quick start

Clone the [Fumping Vue/PHP boilerplate](https://github.com/Fumping/vue-php-boilerplate/tree/main)
and let the setup script turn it into a standalone application with a fresh
Git history:

```bash
# 1. Download the boilerplate
git clone --branch main https://github.com/Fumping/vue-php-boilerplate.git
cd vue-php-boilerplate

# 2. Copy the boilerplate into your new project
./scripts/create-app.sh ../my-new-app "My New App"
cd ../my-new-app

# 3. Install the exact locked dependencies
npm ci

# 4. Start development (Vite + PHP in parallel)
npm run dev
```

The setup script does not copy `.git`, local secrets, the database or build
output. It creates the local configuration, can create an empty SQLite database
and can initialize the new project as a separate Git repository right away.

Then open [http://localhost:5173/](http://localhost:5173/).

## Stack

| Layer | Technology |
|---|---|
| Frontend | Vue 3 (Composition API, `<script setup>`), Vue Router 4 |
| Styling | Tailwind CSS 3.4 + PostCSS + Autoprefixer |
| Build | Vite 8 |
| Testing | Vitest + jsdom |
| Backend | PHP 8 (flat router, no framework) |
| Database | SQLite locally, MySQL in production (via `DB_DRIVER`) |
| Dev server | `concurrently` runs Vite (5173) and the PHP built-in server (8000) side by side |

## File structure

```
.
├── api/                    PHP API
│   ├── index.php           Flat router
│   ├── config.example.php  DB, session and bootstrap config
│   ├── cli/                One-off admin commands
│   ├── handlers/           One file per endpoint
│   └── helpers/            auth.php, response.php
├── db/
│   ├── schema.sqlite.sql   Local schema, applied automatically
│   └── schema.mysql.sql    One-time production import
├── src/                    Vue frontend
│   ├── main.js
│   ├── router/index.js
│   ├── lib/api.js          Fetch wrapper with session and CSRF handling
│   ├── composables/
│   └── views/
├── public/                 Static assets (includes the root .htaccess)
├── scripts/
│   ├── copy-api.js         Vite plugin: copies api/ → dist/api/
│   └── create-app.sh       Interactive setup script
└── tests/                  Vitest specs
```

## Environment setup

1. Copy `.env.example` to `.env`.
2. Copy `api/config.example.php` to `api/config.php`.
3. Run `npm install` to install Vue, Vite, Tailwind, Vitest and concurrently.

On the first API call, the app creates a local user `admin` with password
`admin`. This development bootstrap account has to change its password on first
login.

## Development

`npm run dev` starts two servers in parallel:

- Vite on `http://localhost:5173`, which serves the Vue frontend
- the PHP built-in server on `http://localhost:8000`, which serves `api/index.php`

Vite's proxy forwards every `/api/*` call to the PHP server, so the frontend
doesn't need to know the API runs somewhere else. You just fetch `/api/…`.

Separate scripts:
- `npm run dev:vite` runs only Vite (useful with MAMP or Herd)
- `npm run dev:php` runs only PHP (useful if you run Vite on its own)

## Build and deploy

```bash
npm run build
```

This produces a complete `dist/` folder containing:

- `index.html` and the hashed JS/CSS bundles (frontend)
- `api/` (the PHP files, without `config.php` or SQLite data)
- `.htaccess` (SPA routing and API passthrough)

**CI/CD (GitHub Actions)**

`.github/workflows/ci.yml` runs on every pull request. It runs `php -l` and the
API tests on PHP 8.2 and 8.4, then Vitest and `npm run build`, and checks that
`dist/` contains no `config.php`, SQLite data, `.env` files or logs.

`.github/workflows/release.yml` runs on every push to `main`, or manually. It
runs the full CI first, then builds `dist/` and publishes it as the artifact
`dist-<sha>`, kept for 30 days and including the `.htaccess` files and
`BUILD_INFO.txt`. Download it from Actions → the run → Artifacts and upload its
contents to your webroot (step 3 below).

**Deploy steps**

1. Create the production database and import the schema as a database
   administrator:
   ```bash
   mysql -h DB_HOST -u MIGRATION_USER -p DB_NAME < db/schema.mysql.sql
   ```
2. Give the runtime database user only `SELECT`, `INSERT`, `UPDATE` and
   `DELETE` on that database. No `CREATE`, `ALTER` or `DROP`.
3. Upload the contents of `dist/` to your webroot.
4. Copy `api/config.example.php` to a folder **outside the webroot** and set the
   server variable `APP_CONFIG_PATH` to that file's absolute path.
5. In that file, set `APP_ENV` to `'production'`, `SESSION_COOKIE_SECURE` to
   `true` and `DB_DRIVER` to `'mysql'`, and fill in the runtime credentials.
   Make the file readable only by the web server user (`chmod 600`).
6. Create the first production admin once, without leaving the password in SQL
   or your shell history:
   ```bash
   read -s ADMIN_PASSWORD
   export ADMIN_PASSWORD
   APP_CONFIG_PATH=/secure/path/api.php php dist/api/cli/bootstrap-admin.php admin
   unset ADMIN_PASSWORD
   ```
7. Serve the application over HTTPS only, and test it with
   `curl https://your-domain/api/`.

## Database

**Local (SQLite):**
Set `DB_DRIVER = 'sqlite'` in `api/config.php`. The database file lives at
`api/storage/db.sqlite` (gitignored) and is created automatically if it doesn't
exist yet.

**Production (MySQL):**
Import `db/schema.mysql.sql` before the first API call. In production the API
never runs `CREATE` or `ALTER`, and it refuses to start when the schema is
missing or has the wrong version.

**Schema:**
`db/schema.sqlite.sql` is for local development and `db/schema.mysql.sql` for
production. When you add a migration later, also bump
`DATABASE_SCHEMA_VERSION` in `api/helpers/database.php`.

### SQL portability: SQLite ↔ MySQL

A few differences to keep in mind when you work with both:

| Feature | SQLite | MySQL |
|---|---|---|
| Auto-increment PK | `INTEGER PRIMARY KEY AUTOINCREMENT` | `INT AUTO_INCREMENT PRIMARY KEY` |
| Enum type | None (use `TEXT` + a CHECK constraint) | `ENUM('a','b')` |
| Boolean | `INTEGER` (0/1) | `TINYINT(1)` or `BOOLEAN` |
| Date defaults | `DEFAULT CURRENT_TIMESTAMP` | same |
| JSON | `TEXT` (parse in code) or `JSON` (>=3.38) | `JSON` |
| Character set | Always UTF-8 | Set `charset=utf8mb4` in the DSN |

Recommendation: write the schema in portable SQL. Avoid `ENUM`, use
`INTEGER PRIMARY KEY` (it works in both), and never quote identifiers with
backticks (use double quotes if you need quoting at all).

The alternative is to keep two schema files (`db/schema.sqlite.sql` and
`db/schema.mysql.sql`) in sync.

## Authentication and users

The boilerplate uses server-side PHP sessions with:

- HttpOnly and SameSite cookies, plus Secure cookies in production
- CSRF validation on every mutation
- `password_hash()`/`password_verify()` and a forced password change on first login
- rate limiting on failed logins
- server-side role checks for `/api/admin/*`

Log in at `/login`. Admins manage users at `/admin/users`. New users get a
temporary password of at least 12 characters and must change it when they first
log in.

The Vue router guard only makes navigation nicer. Every protected API handler
must still call `requireUser($db)` or `requireAdmin($db)`.

## Multi-tool setup

For a dashboard with several tools behind one login, see the "Multi-tool
pattern" section in `CLAUDE.md`.

## Testing

```bash
npm run test          # Vitest watch mode
npm run test -- --run # Single run (CI)
npm run test:ci       # Frontend + PHP auth tests
```

There's an example test in `tests/lib/api.spec.js`. Copy its pattern for your
own composables.

## Troubleshooting

**"server_not_configured" on API calls:**
`api/config.php` doesn't exist. Copy `api/config.example.php`.

**"Database schema is missing" in production:**
Import `db/schema.mysql.sql` with a migration user. Don't give the runtime user
DDL permissions.

**HTTP 429 after five failed logins:**
Wait 15 minutes. The counter is stored in the `auth_login_attempts` table.

**PHP built-in server returns "404 Not Found" for assets:**
In `php -S localhost:8000 api/index.php` the second argument is a router
script, so every request goes through it. Serve assets through Vite's dev
server, not directly through PHP.

**SQLite error "unable to open database file":**
The `api/storage/` directory is missing or not writable. Fix it with
`mkdir -p api/storage && chmod 700 api/storage`.
