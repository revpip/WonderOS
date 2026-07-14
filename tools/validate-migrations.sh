#!/usr/bin/env bash
set -euo pipefail

MIGRATION_DIR="${1:-database/migrations}"
PSQL=(psql "${DATABASE_DSN}" -v ON_ERROR_STOP=1)

mapfile -t ups < <(find "$MIGRATION_DIR" -maxdepth 1 -type f -name '*.up.sql' | sort)
if [[ ${#ups[@]} -eq 0 ]]; then
  echo "No forward migrations found in $MIGRATION_DIR" >&2
  exit 1
fi

for up in "${ups[@]}"; do
  echo "Applying $up"
  "${PSQL[@]}" -f "$up"
done

last_up="${ups[-1]}"
last_down="${last_up%.up.sql}.down.sql"
if [[ ! -f "$last_down" ]]; then
  echo "Missing rollback migration for $last_up" >&2
  exit 1
fi

echo "Rolling back latest migration with $last_down"
"${PSQL[@]}" -f "$last_down"
echo "Reapplying latest migration with $last_up"
"${PSQL[@]}" -f "$last_up"

echo "Migration validation completed successfully."
