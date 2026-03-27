#!/usr/bin/env bash
set -euo pipefail

# Build a clean WP.org-ready folder + ZIP for THIS plugin repo.
#
# Usage:
#   ./scripts/build-wporg-zip.sh
#   ./scripts/build-wporg-zip.sh --out dist
#   INCLUDE_VENDOR=1 ./scripts/build-wporg-zip.sh
#
# Output:
#   dist/wporg/<plugin-slug>/   (staged clean folder used for WP.org deploy)
#   dist/<plugin-slug>.zip      (ZIP you can attach to GitHub Releases)
#
# Notes:
# - Assumes repo root == plugin root (single-plugin repo)
# - Excludes common dev artifacts
# - Keeps .wordpress-org/ in repo (for WP.org listing) but excludes it from the ZIP/build folder

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

OUT_DIR="dist"
INCLUDE_VENDOR="${INCLUDE_VENDOR:-0}"

# Optional args
while [[ $# -gt 0 ]]; do
  case "$1" in
    --out)
      OUT_DIR="${2:-dist}"
      shift 2
      ;;
    *)
      echo "Unknown argument: $1"
      exit 1
      ;;
  esac
done

# Derive plugin slug from repo folder name
PLUGIN_SLUG="$(basename "${REPO_ROOT}")"

DIST_DIR="${REPO_ROOT}/${OUT_DIR}"
BUILD_DIR="${DIST_DIR}/wporg/${PLUGIN_SLUG}"
ZIP_PATH="${DIST_DIR}/${PLUGIN_SLUG}.zip"

echo "==> Building WP.org artifact"
echo "Plugin slug : ${PLUGIN_SLUG}"
echo "Repo root   : ${REPO_ROOT}"
echo "Build dir   : ${BUILD_DIR}"
echo "Zip path    : ${ZIP_PATH}"
echo "Vendor      : $( [[ "${INCLUDE_VENDOR}" == "1" ]] && echo "included" || echo "excluded" )"
echo

mkdir -p "${DIST_DIR}"

# 1) Remove macOS junk anywhere in repo (safe, non-fatal)
echo "==> Removing macOS junk (.DS_Store, __MACOSX) ..."
find "${REPO_ROOT}" -name ".DS_Store" -delete 2>/dev/null || true
find "${REPO_ROOT}" -name "__MACOSX" -type d -exec rm -rf {} + 2>/dev/null || true

# 2) Sanity checks
echo "==> Sanity checks ..."
ROOT_PHP_COUNT="$(find "${REPO_ROOT}" -maxdepth 1 -type f -name "*.php" | wc -l | tr -d ' ')"
if [[ "${ROOT_PHP_COUNT}" -lt 1 ]]; then
  echo "Warning: No PHP files found at repo root. WP.org expects a main plugin file at root."
  echo "         (Example: ${PLUGIN_SLUG}.php)"
fi

if [[ ! -f "${REPO_ROOT}/readme.txt" ]]; then
  echo "Warning: readme.txt not found at repo root. WP.org listing requires readme.txt in the plugin root."
fi

# 3) Stage a clean folder to dist/wporg/<slug> using rsync
echo "==> Staging clean build folder ..."
rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}"

RSYNC_EXCLUDES=(
  "--exclude=.git/"
  "--exclude=.github/"          # build excludes workflows
  "--exclude=node_modules/"
  "--exclude=tests/"
  "--exclude=test/"
  "--exclude=docs/"
  "--exclude=doc/"
  "--exclude=.env*"
  "--exclude=.idea/"
  "--exclude=.vscode/"
  "--exclude=.phpunit.result.cache"
  "--exclude=*.log"
  "--exclude=status.md"
  "--exclude=roadmap.md"
  "--exclude=CONTRIBUTING.md"
  "--exclude=CODE_OF_CONDUCT.md"
  "--exclude=composer.lock"
  "--exclude=package-lock.json"
  "--exclude=pnpm-lock.yaml"
  "--exclude=.wordpress-org/"   # listing assets should NOT ship in plugin zip/trunk
  "--exclude=scripts/"          # build/dev scripts must not be in WP.org zip
  "--exclude=${OUT_DIR}/"       # avoid recursion if dist exists in repo
)

if [[ "${INCLUDE_VENDOR}" != "1" ]]; then
  RSYNC_EXCLUDES+=("--exclude=vendor/")
fi

# Copy everything except excludes
rsync -a --delete \
  "${RSYNC_EXCLUDES[@]}" \
  "${REPO_ROOT}/" "${BUILD_DIR}/"

# 4) Create ZIP (ensuring root folder is <slug>)
echo "==> Creating ZIP ..."
rm -f "${ZIP_PATH}"

(
  cd "${DIST_DIR}/wporg"
  zip -r "${ZIP_PATH}" "${PLUGIN_SLUG}" \
    -x "*.DS_Store" \
    -x "__MACOSX/*"
)

# 5) Verify ZIP contents for common junk
echo
echo "==> Verifying ZIP contents ..."
if unzip -l "${ZIP_PATH}" | grep -E "__MACOSX|\.DS_Store" >/dev/null 2>&1; then
  echo "Error: ZIP still contains __MACOSX or .DS_Store. Aborting."
  exit 1
fi

# 6) Show a short listing
echo "==> ZIP created successfully."
echo "Top-level listing:"
unzip -l "${ZIP_PATH}" | head -n 25

echo
echo "Done ✅"
echo "Build folder: ${BUILD_DIR}"
echo "ZIP:         ${ZIP_PATH}"
