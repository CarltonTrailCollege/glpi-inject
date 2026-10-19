#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
    cat <<'EOF'
Usage: sudo bash scripts/install-debian.sh --glpi-dir /var/www/glpi [--web-user www-data]

Deploys this checkout as GLPI's glpiinject plugin, then installs and activates it.
EOF
}

GLPI_DIR=""
WEB_USER="www-data"
SOURCE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"

while (($#)); do
    case "$1" in
        --glpi-dir)
            [[ $# -ge 2 ]] || { echo "Missing value for --glpi-dir" >&2; exit 2; }
            GLPI_DIR="$2"
            shift 2
            ;;
        --web-user)
            [[ $# -ge 2 ]] || { echo "Missing value for --web-user" >&2; exit 2; }
            WEB_USER="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

if [[ -z "$GLPI_DIR" ]]; then
    usage >&2
    exit 2
fi

if ((EUID != 0)); then
    if ! command -v sudo >/dev/null 2>&1; then
        echo "Run as root or install sudo and rerun this script." >&2
        exit 1
    fi
    exec sudo -- bash "$SOURCE_DIR/scripts/install-debian.sh" --glpi-dir "$GLPI_DIR" --web-user "$WEB_USER"
fi

GLPI_DIR="$(realpath -e -- "$GLPI_DIR")"
PLUGIN_DIR="$GLPI_DIR/plugins/glpiinject"
CONSOLE="$GLPI_DIR/bin/console"

for file in setup.php hook.php; do
    [[ -f "$SOURCE_DIR/$file" ]] || { echo "Not a GLPI Inject source directory: missing $file" >&2; exit 1; }
done
[[ -f "$CONSOLE" ]] || { echo "GLPI console not found: $CONSOLE" >&2; exit 1; }
[[ -d "$GLPI_DIR/plugins" ]] || { echo "GLPI plugin directory not found: $GLPI_DIR/plugins" >&2; exit 1; }
id "$WEB_USER" >/dev/null 2>&1 || { echo "Web user does not exist: $WEB_USER" >&2; exit 1; }
command -v runuser >/dev/null 2>&1 || { echo "runuser is required (Debian package: util-linux)." >&2; exit 1; }
PHP_BIN="$(command -v php || true)"
[[ -n "$PHP_BIN" ]] || { echo "PHP CLI is not installed or not in PATH." >&2; exit 1; }

install -d -m 0750 "$PLUGIN_DIR"
for file in setup.php hook.php; do
    install -m 0640 "$SOURCE_DIR/$file" "$PLUGIN_DIR/$file"
done
for directory in ajax front src templates; do
    [[ -d "$SOURCE_DIR/$directory" ]] || continue
    install -d -m 0750 "$PLUGIN_DIR/$directory"
    cp -a "$SOURCE_DIR/$directory/." "$PLUGIN_DIR/$directory/"
done
chown -R "root:$WEB_USER" "$PLUGIN_DIR"
find "$PLUGIN_DIR" -type d -exec chmod 0750 {} +
find "$PLUGIN_DIR" -type f -exec chmod 0640 {} +

echo "Installing GLPI Inject in $GLPI_DIR"
runuser -u "$WEB_USER" -- "$PHP_BIN" "$CONSOLE" plugin:install --force glpiinject
runuser -u "$WEB_USER" -- "$PHP_BIN" "$CONSOLE" plugin:activate glpiinject

echo "GLPI Inject is installed and activated."