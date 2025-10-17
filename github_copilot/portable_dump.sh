#!/usr/bin/env bash
# portable_dump.sh
#
# Create portable schema-only, data-only, and full (schema+data) dumps.
# - Strips DEFINER clauses & boilerplate
# - Handles GENERATED ALWAYS columns by removing their values from INSERTs
# - Streams large tables row-by-row for generated-column tables (no GROUP_CONCAT)
# - Supports optional gzip compression
#
# WARNING: This version embeds a plaintext password (requested by user).
# Consider using ~/.my.cnf or MYSQL_PWD environment variable instead.
#
set -euo pipefail

# ------------- Default Config -------------
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3306"
MYSQL_USER="gstwork"
MYSQL_PASS="gstwork@123"   # <<<<<< Hard-coded password (requested)
MYSQL_BIN="${MYSQL_BIN:-mysql}"           # or mariadb
DUMP_BIN="${DUMP_BIN:-mysqldump}"         # or mariadb-dump
GZIP=0
PARALLEL=0   # (future hook)
SKIP_TABLES=()
ONLY_TABLES=()
INCLUDE_CREATE_DB=1

# Common args (used in all mysql / mysqldump calls)
COMMON_MYSQL_ARGS=(-u "$MYSQL_USER" -h "$MYSQL_HOST" -P "$MYSQL_PORT" -p"$MYSQL_PASS")

# ------------- Args Parsing -------------
SCHEMA_OUT=""
DATA_OUT=""
FULL_OUT=""
SOURCE_DB=""
TARGET_DB=""

usage() {
  echo "Usage: $0 --source-db SRC --target-db TARGET [--schema-out f] [--data-out f] [--full-out f] [--gzip] [--skip-table t] [--only-table t] [--no-createdb]"
  exit 1
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --source-db) SOURCE_DB="$2"; shift 2;;
    --target-db) TARGET_DB="$2"; shift 2;;
    --schema-out) SCHEMA_OUT="$2"; shift 2;;
    --data-out) DATA_OUT="$2"; shift 2;;
    --full-out) FULL_OUT="$2"; shift 2;;
    --gzip) GZIP=1; shift;;
    --skip-table) SKIP_TABLES+=("$2"); shift 2;;
    --only-table) ONLY_TABLES+=("$2"); shift 2;;
    --mysql-user) MYSQL_USER="$2"; shift 2;;          # If overridden after start, also update array
    --mysql-host) MYSQL_HOST="$2"; shift 2;;
    --mysql-port) MYSQL_PORT="$2"; shift 2;;
    --mysql-bin) MYSQL_BIN="$2"; shift 2;;
    --dump-bin) DUMP_BIN="$2"; shift 2;;
    --no-createdb) INCLUDE_CREATE_DB=0; shift;;
    -h|--help) usage;;
    *) echo "Unknown arg: $1"; usage;;
  esac
done

# (If user overrides MYSQL_USER/HOST/PORT after array creation, rebuild COMMON_MYSQL_ARGS)
COMMON_MYSQL_ARGS=(-u "$MYSQL_USER" -h "$MYSQL_HOST" -P "$MYSQL_PORT" -p"$MYSQL_PASS")

[[ -z "$SOURCE_DB" || -z "$TARGET_DB" ]] && usage
if [[ -z "$SCHEMA_OUT" && -z "$DATA_OUT" && -z "$FULL_OUT" ]]; then
  echo "At least one of --schema-out / --data-out / --full-out is required."
  exit 1
fi

echo ">>> Source DB: $SOURCE_DB"
echo ">>> Target DB: $TARGET_DB"

# ------------- Temp Files -------------
WORKDIR=$(mktemp -d)
trap 'rm -rf "$WORKDIR"' EXIT

SCHEMA_TMP="${WORKDIR}/schema.sql"
DATA_TMP="${WORKDIR}/data.sql"

# ------------- Helper Functions -------------
join_by() { local IFS="$1"; shift; echo "$*"; }

contains() {
  local seek="$1"; shift
  for x in "$@"; do [[ "$x" == "$seek" ]] && return 0; done
  return 1
}

should_process_table() {
  local t="$1"
  if ((${#ONLY_TABLES[@]})); then
    contains "$t" "${ONLY_TABLES[@]}" || return 1
  fi
  if ((${#SKIP_TABLES[@]})); then
    contains "$t" "${SKIP_TABLES[@]}" && return 1
  fi
  return 0
}

open_out() {
  local path="$1"
  if [[ -z "$path" ]]; then return 0; fi
  if ((GZIP)); then
    gzip -c > "$path"
  else
    cat > "$path"
  fi
}

append_file() {
  local src="$1"
  local dest="$2"
  if [[ -z "$dest" || ! -f "$src" ]]; then return 0; fi
  if [[ "$dest" == *.gz ]]; then
    gunzip -c "$dest" > "${dest}.tmp.concat"
    cat "$src" >> "${dest}.tmp.concat"
    gzip -c "${dest}.tmp.concat" > "${dest}.new"
    mv "${dest}.new" "$dest"
    rm -f "${dest}.tmp.concat"
  else
    cat "$src" >> "$dest"
  fi
}

write_header() {
  local f="$1"
  [[ -z "$f" ]] && return 0
  if [[ "$f" == *.gz ]]; then
    {
      ((INCLUDE_CREATE_DB)) && echo "CREATE DATABASE IF NOT EXISTS \`$TARGET_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
      ((INCLUDE_CREATE_DB)) && echo "USE \`$TARGET_DB\`;"
      echo "SET sql_notes=0;"
    } | gzip -c > "$f"
  else
    {
      ((INCLUDE_CREATE_DB)) && echo "CREATE DATABASE IF NOT EXISTS \`$TARGET_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
      ((INCLUDE_CREATE_DB)) && echo "USE \`$TARGET_DB\`;"
      echo "SET sql_notes=0;"
    } > "$f"
  fi
}

append_footer() {
  local f="$1"
  [[ -z "$f" ]] && return 0
  if [[ "$f" == *.gz ]]; then
    gunzip -c "$f" > "${f}.tmpf"
    echo "SET sql_notes=1;" >> "${f}.tmpf"
    gzip -c "${f}.tmpf" > "${f}.new"
    mv "${f}.new" "$f"
    rm -f "${f}.tmpf"
  else
    echo "SET sql_notes=1;" >> "$f"
  fi
}

# ------------- Detect Generated Columns -------------
echo ">>> Detecting generated columns..."
GEN_QUERY=$(cat <<SQL
SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA='${SOURCE_DB}'
  AND (EXTRA LIKE '%GENERATED%' OR GENERATION_EXPRESSION IS NOT NULL)
ORDER BY TABLE_NAME, ORDINAL_POSITION;
SQL
)

mapfile -t GEN_ROWS < <("$MYSQL_BIN" "${COMMON_MYSQL_ARGS[@]}" -N -B -e "$GEN_QUERY")

declare -A GEN_COLS_MAP   # table -> space-separated list
for line in "${GEN_ROWS[@]}"; do
  tbl="${line%%$'\t'*}"
  col="${line#*$'\t'}"
  GEN_COLS_MAP["$tbl"]+="$col "
done

echo ">>> Tables with generated columns:"
for t in "${!GEN_COLS_MAP[@]}"; do
  echo "    $t : ${GEN_COLS_MAP[$t]}"
done

# ------------- Dump Schema -------------
echo ">>> Dumping schema (no data)..."
"$DUMP_BIN" \
  "${COMMON_MYSQL_ARGS[@]}" \
  --default-character-set=utf8mb4 \
  --skip-comments --skip-dump-date \
  --routines --triggers --events \
  --no-data \
  "$SOURCE_DB" > "$SCHEMA_TMP"

# Strip DEFINER & boilerplate
sed -i -E 's/DEFINER[ ]*=[^ ]+//g' "$SCHEMA_TMP"
sed -i '/^\/\*!50001 SET /d;/^\/\*!50013/d;/^\/\*!401/d' "$SCHEMA_TMP"

# ------------- Prepare schema output(s) -------------
if [[ -n "$SCHEMA_OUT" ]]; then
  echo ">>> Writing schema output -> $SCHEMA_OUT"
  write_header "$SCHEMA_OUT"
  if [[ "$SCHEMA_OUT" == *.gz ]]; then
    gunzip -c "$SCHEMA_OUT" > "${SCHEMA_OUT}.tmp"
    cat "$SCHEMA_TMP" >> "${SCHEMA_OUT}.tmp"
    gzip -c "${SCHEMA_OUT}.tmp" > "$SCHEMA_OUT"
    rm -f "${SCHEMA_OUT}.tmp"
    append_footer "$SCHEMA_OUT"
  else
    cat "$SCHEMA_TMP" >> "$SCHEMA_OUT"
    append_footer "$SCHEMA_OUT"
  fi
fi

# ------------- Data Dump -------------
if [[ -n "$DATA_OUT" || -n "$FULL_OUT" ]]; then
  echo ">>> Enumerating tables..."
  mapfile -t TABLES < <("$MYSQL_BIN" "${COMMON_MYSQL_ARGS[@]}" -N -B -e "SHOW FULL TABLES IN \`${SOURCE_DB}\` WHERE Table_type='BASE TABLE';" | awk '{print $1}')

  : > "$DATA_TMP"

  for tbl in "${TABLES[@]}"; do
    should_process_table "$tbl" || { echo ">>> Skipping table (policy): $tbl"; continue; }

    if [[ -n "${GEN_COLS_MAP[$tbl]:-}" ]]; then
      echo ">>> Table $tbl has generated cols -> streaming row-by-row transform"
      "$DUMP_BIN" \
        "${COMMON_MYSQL_ARGS[@]}" \
        --default-character-set=utf8mb4 \
        --skip-comments --skip-dump-date \
        --compact \
        --no-create-info \
        --skip-triggers \
        --skip-extended-insert \
        "$SOURCE_DB" "$tbl" \
        | python3 "$(dirname "$0")/strip_generated_inserts.py" "$tbl" "${GEN_COLS_MAP[$tbl]}" "$SOURCE_DB" "$MYSQL_USER" "$MYSQL_HOST" "$MYSQL_PORT" \
        >> "$DATA_TMP"
    else
      echo ">>> Table $tbl (no generated cols) -> fast extended inserts"
      "$DUMP_BIN" \
        "${COMMON_MYSQL_ARGS[@]}" \
        --default-character-set=utf8mb4 \
        --skip-comments --skip-dump-date \
        --compact \
        --no-create-info \
        --skip-triggers \
        "$SOURCE_DB" "$tbl" >> "$DATA_TMP"
    fi
  done

  if [[ -n "$DATA_OUT" ]]; then
    echo ">>> Writing data output -> $DATA_OUT"
    write_header "$DATA_OUT"
    if [[ "$DATA_OUT" == *.gz ]]; then
      gunzip -c "$DATA_OUT" > "${DATA_OUT}.tmp"
      cat "$DATA_TMP" >> "${DATA_OUT}.tmp"
      gzip -c "${DATA_OUT}.tmp" > "$DATA_OUT"
      rm -f "${DATA_OUT}.tmp"
      append_footer "$DATA_OUT"
    else
      cat "$DATA_TMP" >> "$DATA_OUT"
      append_footer "$DATA_OUT"
    fi
  fi

  if [[ -n "$FULL_OUT" ]]; then
    echo ">>> Assembling full output -> $FULL_OUT"
    write_header "$FULL_OUT"
    if [[ "$FULL_OUT" == *.gz ]]; then
      gunzip -c "$FULL_OUT" > "${FULL_OUT}.tmp"
      cat "$SCHEMA_TMP" >> "${FULL_OUT}.tmp"
      echo "" >> "${FULL_OUT}.tmp"
      cat "$DATA_TMP" >> "${FULL_OUT}.tmp"
      gzip -c "${FULL_OUT}.tmp" > "$FULL_OUT"
      rm -f "${FULL_OUT}.tmp"
      append_footer "$FULL_OUT"
    else
      cat "$SCHEMA_TMP" >> "$FULL_OUT"
      echo "" >> "$FULL_OUT"
      cat "$DATA_TMP" >> "$FULL_OUT"
      append_footer "$FULL_OUT"
    fi
  fi
fi

echo "✅ Done."
