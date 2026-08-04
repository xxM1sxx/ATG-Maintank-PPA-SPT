#!/bin/bash
# Run on BeagleBone after syncing Maintak_PPA from PC.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$ROOT/build"
INSTALL_BIN="${INSTALL_BIN:-/home/user/Apps/bin/atg-reader}"
DB_PATH="${DB_PATH:-/home/user/Apps/qims_logger.db}"

echo "=== 1. Verify synced sources ==="
SERIAL="$ROOT/libs/atg-sdk/src/serial_port.cpp"
if grep -q 'case Bps::B0:' "$SERIAL" 2>/dev/null; then
    echo "ERROR: Old code detected (Bps::B0). Sync Maintak_PPA from PC first."
    exit 1
fi
if ! grep -q 'case Bps::Rate0:' "$SERIAL"; then
    echo "ERROR: Expected Bps::Rate0 in serial_port.cpp — sync from PC."
    exit 1
fi
if ! grep -q '#include <system_error>' "$SERIAL"; then
    echo "ERROR: Missing #include <system_error> in serial_port.cpp"
    exit 1
fi
echo "OK: serial_port.cpp has Rate* enum + system_error"

echo "=== 2. System time (optional NTP) ==="
if command -v timedatectl >/dev/null 2>&1; then
    sudo timedatectl set-ntp true 2>/dev/null || true
fi
date

echo "=== 3. Dependencies ==="
sudo apt-get install -y build-essential cmake libsqlite3-dev

echo "=== 4. Clean build ==="
mkdir -p "$BUILD"
cd "$BUILD"
rm -rf ./*
cmake .. -DATG_BUILD_READER=ON
cmake --build . -j"$(nproc 2>/dev/null || echo 2)"

echo "=== 5. Deploy binary (no systemd — manual test) ==="
sudo mkdir -p "$(dirname "$INSTALL_BIN")"
sudo cp "$BUILD/atg-reader" "$INSTALL_BIN"
sudo chmod +x "$INSTALL_BIN"
echo "Binary installed to $INSTALL_BIN (service not touched)"

echo "=== 6. Runtime checks ==="
if [ -f "$DB_PATH" ]; then
    sqlite3 "$DB_PATH" "PRAGMA table_info(tbl_atg_sensor);" | grep -E 'tank_|offset_mm' || \
        echo "WARN: volume columns missing — open web once to run init_atg_table.php"
else
    echo "WARN: DB not found at $DB_PATH"
fi

echo "=== Done ==="
echo "Binary: $INSTALL_BIN"
echo "Manual test (foreground):"
echo "  cd $BUILD"
echo "  ATG_RTS_DE=1 ATG_RS485_DELAY_MS=15 ./atg-reader $DB_PATH"
echo "Or installed copy:"
echo "  ATG_RTS_DE=1 ATG_RS485_DELAY_MS=15 $INSTALL_BIN $DB_PATH"
