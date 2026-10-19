#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
    cat <<'EOF'
Usage: sudo bash scripts/uninstall-debian.sh --glpi-dir /var/www/glpi [--web-user www-data] [--yes]

Runs GLPI's plugin uninstall hook (deleting GLPI Inject tables and uploaded CSVs),
then removes the deployed plugin files. Use --yes to skip the confirmation prompt.
EOF
}

GLPI_DIR=""
WEB_USER="www-data"
ASSUME_YES=0
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
        --yes|-y)
            ASSUME_YES=1
            shift
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
    args=(--glpi-dir "$GLPI_DIR" --web-user "$WEB_USER")
    ((ASSUME_YES)) && args+=(--yes)
    exec sudo -- bash "$SOURCE_DIR/scripts/uninstall-debian.sh" "${args[@]}"
fi

GLPI_DIR="$(realpath -e -- "$GLPI_DIR")"
PLUGIN_DIR="$GLPI_DIR/plugins/glpiinject"
CONSOLE="$GLPI_DIR/bin/console"
[[ -f "$CONSOLE" ]] || { echo "GLPI console not found: $CONSOLE" >&2; exit 1; }
[[ -d "$PLUGIN_DIR" ]] || { echo "Plugin directory not found: $PLUGIN_DIR" >&2; exit 1; }
id "$WEB_USER" >/dev/null 2>&1 || { echo "Web user does not exist: $WEB_USER" >&2; exit 1; }
command -v runuser >/dev/null 2>&1 || { echo "runuser is required (Debian package: util-linux)." >&2; exit 1; }
PHP_BIN="$(command -v php || true)"
[[ -n "$PHP_BIN" ]] || { echo "PHP CLI is not installed or not in PATH." >&2; exit 1; }

cat <<EOF
This will uninstall GLPI Inject from:
  $GLPI_DIR

GLPI's uninstall hook permanently deletes the plugin's model, run-history, and
run-log tables. It also removes uploaded CSVs from GLPI_PLUGIN_DOC_DIR/glpiinject.
EOF

if ((!ASSUME_YES)); then
    [[ -t 0 ]] || { echo "Refusing non-interactive uninstall without --yes." >&2; exit 1; }
    read -r -p 'Type yes to continue: ' confirmation
    [[ "$confirmation" == "yes" ]] || { echo "Uninstall cancelled."; exit 1; }
fi

echo "Running the GLPI Inject uninstall hook..."
printf 'yes\n' | runuser -u "$WEB_USER" -- "$PHP_BIN" "$CONSOLE" plugin:uninstall glpiinject

echo "Removing deployed plugin files..."
rm -rf -- "$PLUGIN_DIR"

echo "GLPI Inject has been uninstalled."