#!/usr/bin/env bash
set -euo pipefail

MIGRATION_DIR="${1:-database/migrations}"
LOG_DIR="${MIGRATION_LOG_DIR:-artifacts/migrations}"
PSQL=(psql "${DATABASE_DSN}" -X -v ON_ERROR_STOP=1 --set=VERBOSITY=verbose)

mkdir -p "$LOG_DIR"
rm -f "$LOG_DIR"/*.log 2>/dev/null || true

mapfile -t ups < <(find "$MIGRATION_DIR" -maxdepth 1 -type f -name '*.up.sql' | sort)
if [[ ${#ups[@]} -eq 0 ]]; then
  echo "No forward migrations found in $MIGRATION_DIR" >&2
  exit 1
fi

apply_migration() {
  local file="$1"
  local base log_file status
  base="$(basename "$file")"
  log_file="$LOG_DIR/${base}.log"

  echo "::group::Applying $file"
  set +e
  "${PSQL[@]}" -f "$file" >"$log_file" 2>&1
  status=$?
  set -e

  cat "$log_file"

  if [[ $status -ne 0 ]]; then
    echo "::error file=$file::Migration failed with psql exit code $status: $file" >&2
    echo "Complete PostgreSQL output saved to $log_file" >&2
    echo "Last 80 lines from PostgreSQL:" >&2
    tail -n 80 "$log_file" >&2
    echo "::endgroup::"
    exit "$status"
  fi

  echo "::endgroup::"
}

for up in "${ups[@]}"; do
  apply_migration "$up"
done

last_up="${ups[$((${#ups[@]} - 1))]}"
last_down="${last_up%.up.sql}.down.sql"
if [[ ! -f "$last_down" ]]; then
  echo "::error file=$last_up::Missing rollback migration: $last_down" >&2
  exit 1
fi

echo "::group::Rolling back latest migration with $last_down"
"${PSQL[@]}" -f "$last_down" 2>&1 | tee "$LOG_DIR/$(basename "$last_down").log"
echo "::endgroup::"

apply_migration "$last_up"

echo "Migration validation completed successfully."
