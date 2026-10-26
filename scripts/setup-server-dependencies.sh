#!/usr/bin/env bash

set -euo pipefail

auto_yes=false
check_only=false

for argument in "$@"; do
    case "$argument" in
        --yes|-y) auto_yes=true ;;
        --check) check_only=true ;;
        --help|-h)
            cat <<'EOF'
Usage: bash scripts/setup-server-dependencies.sh [--check] [--yes]

Checks the operating-system tools used by document optimization.
  --check  Report missing dependencies without installing them.
  --yes    Install missing dependencies without asking for confirmation.
EOF
            exit 0
            ;;
        *)
            echo "Unknown option: $argument" >&2
            exit 2
            ;;
    esac
done

if [[ "$(uname -s)" != "Linux" ]]; then
    echo "This setup command is intended for the Linux production server." >&2
    echo "On Windows, install Ghostscript and enable PHP GD with WebP support manually." >&2
    exit 1
fi

php_command="${PHP_BINARY:-php}"
if ! command -v "$php_command" >/dev/null 2>&1; then
    echo "PHP CLI was not found. Install PHP before running this setup command." >&2
    exit 1
fi

missing_packages=()
needs_gd=false

if command -v gs >/dev/null 2>&1; then
    echo "[OK] Ghostscript $(gs --version 2>/dev/null)"
else
    echo "[MISSING] Ghostscript is required for Grade Skipping PDF compression."
    missing_packages+=(ghostscript)
fi

php_version="$($php_command -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
if "$php_command" -r 'exit(function_exists("imagewebp") && (imagetypes() & IMG_WEBP) ? 0 : 1);'; then
    echo "[OK] PHP ${php_version} GD WebP support"
else
    gd_package="php${php_version}-gd"
    echo "[MISSING] PHP GD WebP support is required for JPG/PNG document optimization."
    missing_packages+=("$gd_package")
    needs_gd=true
fi

if ((${#missing_packages[@]} == 0)); then
    echo "All document optimization dependencies are installed."
    exit 0
fi

echo "Required Ubuntu packages: ${missing_packages[*]}"
if [[ "$check_only" == true ]]; then
    exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
    echo "Automatic installation requires an Ubuntu/Debian server with apt-get." >&2
    echo "Install these packages using the server's package manager: ${missing_packages[*]}" >&2
    exit 1
fi

if [[ "$auto_yes" != true ]]; then
    if [[ ! -t 0 ]]; then
        echo "Interactive confirmation is unavailable. Run again in a terminal or pass --yes." >&2
        exit 1
    fi

    read -r -p "Install ${missing_packages[*]} now? [Y/n] " answer
    case "${answer:-Y}" in
        y|Y|yes|YES|Yes) ;;
        *) echo "Installation cancelled."; exit 1 ;;
    esac
fi

privilege=()
if ((EUID != 0)); then
    if ! command -v sudo >/dev/null 2>&1; then
        echo "Root access or sudo is required to install server packages." >&2
        exit 1
    fi
    privilege=(sudo)
fi

"${privilege[@]}" apt-get update
"${privilege[@]}" apt-get install -y "${missing_packages[@]}"

if [[ "$needs_gd" == true ]] && command -v systemctl >/dev/null 2>&1; then
    fpm_service="php${php_version}-fpm"
    if systemctl list-unit-files "${fpm_service}.service" --no-legend 2>/dev/null | grep -q "${fpm_service}.service"; then
        "${privilege[@]}" systemctl restart "$fpm_service"
        echo "[OK] Restarted ${fpm_service}"
    fi
fi

failed=false
if command -v gs >/dev/null 2>&1; then
    echo "[OK] Ghostscript $(gs --version 2>/dev/null)"
else
    echo "[FAILED] Ghostscript is still unavailable." >&2
    failed=true
fi

if "$php_command" -r 'exit(function_exists("imagewebp") && (imagetypes() & IMG_WEBP) ? 0 : 1);'; then
    echo "[OK] PHP ${php_version} GD WebP support"
else
    echo "[FAILED] PHP GD WebP support is still unavailable to the CLI." >&2
    failed=true
fi

if [[ "$failed" == true ]]; then
    exit 1
fi

echo "Server document optimization dependencies are ready."
