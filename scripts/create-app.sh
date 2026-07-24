#!/usr/bin/env bash
#
# Boilerplate setup-script.
# Kopieert deze boilerplate naar een nieuwe map, vervangt placeholders,
# en zet 'm klaar voor `npm install && npm run dev`.
#
# Gebruik: ./scripts/create-app.sh
#         of: ./scripts/create-app.sh ../nieuwe-app "Nieuwe App"

set -euo pipefail

# ─── Preflight ────────────────────────────────────────────────────
for tool in php node npm rsync; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    echo "Fout: '$tool' is niet geïnstalleerd. Installeer 't eerst." >&2
    exit 1
  fi
done

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
  --exclude '.env' \
  --exclude '.env.local' \
  --exclude '.DS_Store' \
  --exclude '*.log' \
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
# Regenerate package-lock.json op eerste `npm install` in de nieuwe app
rm -f "$TARGET_DIR/package-lock.json"

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
