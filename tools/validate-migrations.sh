#!/usr/bin/env bash
set -euo pipefail

MIGRATION_DIR="${1:-database/migrations}"
PSQL=(psql "${DATABASE_DSN}" -X -v ON_ERROR_STOP=1 --set=VERBOSITY=verbose)

mapfile -t ups < <(find "$MIGRATION_DIR" -maxdepth 1 -type f -name '*.up.sql' | sort)
if [[ ${#ups[@]} -eq 0 ]]; then
  echo "No forward migrations found in $MIGRATION_DIR" >&2
  exit 1
fi

apply_migration() {
  local file="$1"
  local log_file
  log_file="$(mktemp)"

  echo "::group::Applying $file"
  if ! "${PSQL[@]}" -f "$file" >"$log_file" 2>&1; then
    local status=$?
    cat "$log_file" >&2
    echo "::error file=$file::Migration failed: $file" >&2
    echo "Last 80 lines from PostgreSQL:" >&2
    tail -n 80 "$log_file" >&2
    rm -f "$log_file"
    echo "::endgroup::"
    exit "${status:-1}"
  fi
  cat "$log_file"
  rm -f "$log_file"
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
"${PSQL[@]}" -f "$last_down"
echo "::endgroup::"

apply_migration "$last_up"

echo "Migration validation completed successfully."