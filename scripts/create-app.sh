#!/usr/bin/env bash
#
# Boilerplate setup-script.
# Copies this boilerplate into a new folder, replaces placeholders
# and prepares it for `npm install && npm run dev`.
#
# Usage: ./scripts/create-app.sh
#     or: ./scripts/create-app.sh ../new-app "New App"

set -euo pipefail

# ─── Preflight ────────────────────────────────────────────────────
for tool in php node npm rsync; do
  if ! command -v "$tool" >/dev/null 2>&1; then
    echo "Error: '$tool' is not installed. Install it first." >&2
    exit 1
  fi
done

# ─── Determine boilerplate root ──────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BOILER_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# ─── Arguments or prompts ───────────────────────────────────────────
TARGET_DIR="${1:-}"
APP_NAME="${2:-}"

if [[ -z "$TARGET_DIR" ]]; then
  read -r -p "Target folder (e.g. ../my-app): " TARGET_DIR
fi
if [[ -z "$TARGET_DIR" ]]; then
  echo "No target folder given, aborting."
  exit 1
fi

if [[ -e "$TARGET_DIR" ]]; then
  echo "Error: $TARGET_DIR already exists."
  exit 1
fi

if [[ -z "$APP_NAME" ]]; then
  DEFAULT_NAME="$(basename "$TARGET_DIR")"
  read -r -p "App name [$DEFAULT_NAME]: " APP_NAME
  APP_NAME="${APP_NAME:-$DEFAULT_NAME}"
fi

read -r -p "Create an empty SQLite DB now? [Y/n]: " MAKE_DB
MAKE_DB="${MAKE_DB:-Y}"

read -r -p "Run git init in the new app? [Y/n]: " DO_GIT
DO_GIT="${DO_GIT:-Y}"

# ─── Copy ───────────────────────────────────────────────────────
echo "→ Copying $BOILER_ROOT → $TARGET_DIR"
mkdir -p "$TARGET_DIR"
# Copy everything except node_modules, dist, .git, .superpowers, api/config.php, api/storage/db.sqlite*
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

# ─── Placeholder replacement ────────────────────────────────────
echo "→ Replacing {{APP_NAME}} placeholders"
# package.json: name must be lowercase with no spaces
NPM_NAME="$(echo "$APP_NAME" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')"
# BSD sed (macOS) vs GNU sed
if sed --version >/dev/null 2>&1; then
  SED_INPLACE=(sed -i)
else
  SED_INPLACE=(sed -i '')
fi

"${SED_INPLACE[@]}" "s|{{APP_NAME}}|$NPM_NAME|g" "$TARGET_DIR/package.json"
"${SED_INPLACE[@]}" "s|{{APP_NAME}}|$APP_NAME|g" "$TARGET_DIR/index.html"
# ─── Local configuration ──────────────────────────────────────────
cp "$TARGET_DIR/api/config.example.php" "$TARGET_DIR/api/config.php"
chmod 600 "$TARGET_DIR/api/config.php"
cp "$TARGET_DIR/.env.example" "$TARGET_DIR/.env"

# ─── SQLite DB ───────────────────────────────────────────────────
if [[ "$MAKE_DB" =~ ^[Yy]$ ]]; then
  mkdir -p "$TARGET_DIR/api/storage"
  : > "$TARGET_DIR/api/storage/db.sqlite"
  echo "→ Created empty SQLite DB at api/storage/db.sqlite"
fi

# ─── Git init ────────────────────────────────────────────────────
if [[ "$DO_GIT" =~ ^[Yy]$ ]]; then
  (
    cd "$TARGET_DIR"
    git init -b main >/dev/null
    git add .
    git commit -m "Initial commit (from boilerplate)" >/dev/null
  )
  echo "→ Created Git repo with an initial commit"
fi

# ─── Next steps ─────────────────────────────────────────────────
cat <<EOF

✓ Done! Your new app is in: $TARGET_DIR

Next steps:

  cd $TARGET_DIR
  npm install
  npm run dev

Then open http://localhost:5173/
EOF
