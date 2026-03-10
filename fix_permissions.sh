#!/usr/bin/env bash
set -euo pipefail

# Quick permission normalizer for this project.
# Defaults to dry-run so you can preview changes.
#
# Usage:
#   ./fix_permissions.sh               # dry-run in current directory
#   ./fix_permissions.sh --dry-run .   # explicit dry-run
#   ./fix_permissions.sh --apply .     # apply changes

MODE="dry-run"
TARGET_DIR="."

case "${1:-}" in
  --apply)
    MODE="apply"
    TARGET_DIR="${2:-.}"
    ;;
  --dry-run|"")
    MODE="dry-run"
    TARGET_DIR="${2:-.}"
    ;;
  *)
    TARGET_DIR="$1"
    ;;
esac

if [[ ! -d "$TARGET_DIR" ]]; then
  echo "Error: target directory does not exist: $TARGET_DIR" >&2
  exit 1
fi

cd "$TARGET_DIR"

echo "Target: $(pwd)"
echo "Mode:   $MODE"

if [[ "$MODE" == "apply" ]]; then
  find . -path './.git' -prune -o -type d -print0 | xargs -0 -r chmod 755
  find . -path './.git' -prune -o -type f -print0 | xargs -0 -r chmod 644
  find . -path './.git' -prune -o -type f -name '*.sh' -print0 | xargs -0 -r chmod 755
  find . -path './.git' -prune -o -type f -name '*.bash' -print0 | xargs -0 -r chmod 755
else
  echo "[dry-run] find . -path './.git' -prune -o -type d -print0 | xargs -0 -r chmod 755"
  echo "[dry-run] find . -path './.git' -prune -o -type f -print0 | xargs -0 -r chmod 644"
  echo "[dry-run] find . -path './.git' -prune -o -type f -name '*.sh' -print0 | xargs -0 -r chmod 755"
  echo "[dry-run] find . -path './.git' -prune -o -type f -name '*.bash' -print0 | xargs -0 -r chmod 755"
fi

echo "Done."
