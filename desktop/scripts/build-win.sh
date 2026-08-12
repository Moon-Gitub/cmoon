#!/usr/bin/env bash
# Empaqueta instalador Windows con better-sqlite3 nativo win32 (no el .node de Linux).
set -euo pipefail
cd "$(dirname "$0")/.."

VERSION=$(node -p "require('./package.json').dependencies['better-sqlite3'].replace(/^[^0-9]*/,'')")
ELECTRON_VER=$(node -p "require('./package.json').devDependencies.electron.replace(/^[^0-9]*/,'')")
# Electron 33 → ABI 130
ABI=130

echo "==> npm install"
npm install

echo "==> Descargando better-sqlite3 win32 prebuild (v${VERSION} electron-v${ABI})"
TMP=$(mktemp -d)
ARCHIVE="better-sqlite3-v${VERSION}-electron-v${ABI}-win32-x64.tar.gz"
URL="https://github.com/WiseLibs/better-sqlite3/releases/download/v${VERSION}/${ARCHIVE}"

if ! curl -fL --retry 3 -o "$TMP/$ARCHIVE" "$URL"; then
  echo "No hay prebuild exacto $URL — probando latest 12.x con ABI $ABI"
  URL="https://github.com/WiseLibs/better-sqlite3/releases/download/v12.12.0/better-sqlite3-v12.12.0-electron-v${ABI}-win32-x64.tar.gz"
  curl -fL --retry 3 -o "$TMP/$ARCHIVE" "$URL"
fi

mkdir -p node_modules/better-sqlite3/build/Release
tar -xzf "$TMP/$ARCHIVE" -C "$TMP"
# tar suele traer build/Release/better_sqlite3.node
NODE_SRC=$(find "$TMP" -name 'better_sqlite3.node' | head -1)
cp -f "$NODE_SRC" node_modules/better-sqlite3/build/Release/better_sqlite3.node

# Verificar PE/Windows (MZ header) sin depender de `file`
python3 - <<'PY'
from pathlib import Path
p = Path('node_modules/better-sqlite3/build/Release/better_sqlite3.node')
magic = p.read_bytes()[:2]
print(f'native module: {p} size={p.stat().st_size} magic={magic!r}')
if magic != b'MZ':
    raise SystemExit(f'ERROR: expected Windows PE (MZ), got {magic!r}')
print('OK: Windows PE native module')
PY

echo "==> electron-builder (sin rebuild npm, conserva .node win32)"
npx electron-builder --win --x64 -c.npmRebuild=false

echo "==> Verificando artefacto empaquetado"
PACKED=$(find dist/win-unpacked -path '*/better-sqlite3/build/Release/better_sqlite3.node' | head -1)
python3 - <<PY
from pathlib import Path
p = Path("$PACKED")
magic = p.read_bytes()[:2]
print(f'packed: {p} magic={magic!r}')
if magic != b'MZ':
    raise SystemExit('ERROR: el instalador sigue con .node incorrecto')
print('OK: packed Windows PE')
PY

mkdir -p entregas
SETUP=$(ls -1 dist/POSMoon-Offline-*-Setup-x64.exe | sort | tail -1)
cp -f "$SETUP" entregas/
echo "OK: $SETUP → entregas/"
rm -rf "$TMP"
