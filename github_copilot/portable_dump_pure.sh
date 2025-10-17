#!/usr/bin/env bash
# portable_dump_pure.sh
#
# Pure Bash + MariaDB/MySQL client solution (NO Python) to produce:
#   - Schema-only dump
#   - Data-only dump
#   - Full dump (schema + data)
#
# Features:
#   - Detects GENERATED ALWAYS columns (via INFORMATION_SCHEMA)
#   - Omits generated columns from INSERTs
#   - Streams tables with generated columns row-by-row, batching INSERTs
#   - Uses fast extended inserts for normal tables
#   - Removes DEFINER clauses & session boilerplate
#   - Adds CREATE DATABASE / USE (can be disabled)
#   - Wraps import with SET sql_notes=0 / SET sql_notes=1
#
# SECURITY NOTE:
#   Password is embedded per user request. Consider migrating to ~/.my.cnf or
#   MYSQL_PWD env var for production.
#
# Usage example:
#   ./portable_dump_pure.sh \
#       --source-db gst_accounting \
#       --target-db gst_app_github_copilot \
#       --schema-out schema.sql \
#       --data-out data.sql \
#       --full-out full.sql
#
# With gzip compression (auto-detect by --gzip flag):
#   ./portable_dump_pure.sh ... --gzip \
#     --schema-out schema.sql.gz --data-out data.sql.gz --full-out full.sql.gz
#
set -euo pipefail

# ---------------- Configuration (defaults) ----------------
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3306"
MYSQL_USER="gstwork"
MYSQL_PASS="gstwork@123"          # <== Embedded password (requested)
MYSQL_BIN="${MYSQL_BIN:-mariadb}" # or mysql
DUMP_BIN="${DUMP_BIN:-mariadb-dump}" # or mysqldump
INCLUDE_CREATE_DB=1
GZIP=0
BATCH_SIZE=500   # rows per multi-row INSERT for generated-column tables
ONLY_TABLES=()
SKIP_TABLES=()

# Output targets
SCHEMA_OUT=""
DATA_OUT=""
FULL_OUT=""
SOURCE_DB=""
TARGET_DB=""

usage() {
  cat <<EOF
Usage: $0 --source-db SRC --target-db TARGET [options]

Required:
  --source-db NAME
  --target-db NAME

Outputs (choose one or more):
  --schema-out FILE
  --data-out FILE
  --full-out FILE

Options:
  --gzip                 Compress outputs (files should have .gz if you like)
  --batch-size N         Batch size for generated-column tables (default 500)
  --only-table NAME      Include only this table (repeatable)
  --skip-table NAME      Skip this table (repeatable)
  --no-createdb          Omit CREATE DATABASE / USE header
  --mysql-bin PATH       Override mariadb/mysql client
  --dump-bin PATH        Override mariadb-dump/mysqldump
  --mysql-user USER
  --mysql-host HOST
  --mysql-port PORT

Security:
  Password is hard-coded (MYSQL_PASS). Adjust before sharing.

Examples:
  $0 --source-db src --target-db tgt --schema-out s.sql --data-out d.sql
  $0 --source-db src --target-db tgt --full-out full.sql --gzip

EOF
  exit 1
}

# ---------------- Parse Arguments ----------------
while [[ $# -gt 0 ]]; do
  case "$1" in
    --source-db) SOURCE_DB="$2"; shift 2;;
    --target-db) TARGET_DB="$2"; shift 2;;
    --schema-out) SCHEMA_OUT="$2"; shift 2;;
    --data-out) DATA_OUT="$2"; shift 2;;
    --full-out) FULL_OUT="$2"; shift 2;;
    --gzip) GZIP=1; shift;;
    --batch-size) BATCH_SIZE="$2"; shift 2;;
    --only-table) ONLY_TABLES+=("$2"); shift 2;;
    --skip-table) SKIP_TABLES+=("$2"); shift 2;;
    --no-createdb) INCLUDE_CREATE_DB=0; shift;;
    --mysql-bin) MYSQL_BIN="$2"; shift 2;;
    --dump-bin) DUMP_BIN="$2"; shift 2;;
    --mysql-user) MYSQL_USER="$2"; shift 2;;
    --mysql-host) MYSQL_HOST="$2"; shift 2;;
    --mysql-port) MYSQL_PORT="$2"; shift 2;;
    -h|--help) usage;;
    *) echo "Unknown argument: $1" >&2; usage;;
  esac
done

[[ -z "$SOURCE_DB" || -z "$TARGET_DB" ]] && { echo "Missing required DB names."; usage; }
if [[ -z "$SCHEMA_OUT" && -z "$DATA_OUT" && -z "$FULL_OUT" ]]; then
  echo "Specify at least one of: --schema-out / --data-out / --full-out" >&2
  exit 1
fi

echo ">>> Source DB: $SOURCE_DB"
echo ">>> Target DB: $TARGET_DB"

# Build common mysql args
COMMON_MYSQL_ARGS=(-u "$MYSQL_USER" -h "$MYSQL_HOST" -P "$MYSQL_PORT" -p"$MYSQL_PASS")

# ---------------- Helpers ----------------
contains() { local s="$1"; shift; for x in "$@"; do [[ "$x" == "$s" ]] && return 0; done; return 1; }
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

write_header() {
  local f="$1"
  [[ -z "$f" ]] && return 0
  local tmp="${f}.hdr.$$"
  {
    ((INCLUDE_CREATE_DB)) && echo "CREATE DATABASE IF NOT EXISTS \`$TARGET_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    ((INCLUDE_CREATE_DB)) && echo "USE \`$TARGET_DB\`;"
    echo "SET sql_notes=0;"
  } > "$tmp"
  if ((GZIP)); then gzip -c "$tmp" > "$f"; rm -f "$tmp"; else mv "$tmp" "$f"; fi
}

append_footer() {
  local f="$1"
  [[ -z "$f" ]] && return 0
  [[ ! -f "$f" ]] && return 0
  if ((GZIP)); then
    local tmp="${f}.tmp.$$"
    gunzip -c "$f" > "$tmp"
    echo "SET sql_notes=1;" >> "$tmp"
    gzip -c "$tmp" > "${f}.new"
    mv "${f}.new" "$f"
    rm -f "$tmp"
  else
    echo "SET sql_notes=1;" >> "$f"
  fi
}

append_raw() {
  local src="$1" dest="$2"
  [[ -z "$dest" || ! -f "$src" ]] && return 0
  if ((GZIP)); then
    local tmp="${dest}.tmp.$$"
    gunzip -c "$dest" > "$tmp"
    cat "$src" >> "$tmp"
    gzip -c "$tmp" > "${dest}.new"
    mv "${dest}.new" "$dest"
    rm -f "$tmp"
  else
    cat "$src" >> "$dest"
  fi
}

# ---------------- Workspace ----------------
WORKDIR=$(mktemp -d)
trap 'rm -rf "$WORKDIR"' EXIT
SCHEMA_TMP="$WORKDIR/schema.sql"
DATA_TMP="$WORKDIR/data.sql"
: > "$DATA_TMP"

# ---------------- Detect Generated Columns ----------------
echo ">>> Detecting generated columns..."
GEN_QUERY="
SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA='${SOURCE_DB}'
  AND (EXTRA LIKE '%GENERATED%' OR GENERATION_EXPRESSION IS NOT NULL)
ORDER BY TABLE_NAME, ORDINAL_POSITION;
"
mapfile -t GEN_ROWS < <("$MYSQL_BIN" "${COMMON_MYSQL_ARGS[@]}" -N -B -e "$GEN_QUERY")

declare -A GEN_COLS_MAP
if ((${#GEN_ROWS[@]})); then
  for row in "${GEN_ROWS[@]}"; do
    t=${row%%$'\t'*}
    c=${row#*$'\t'}
    GEN_COLS_MAP["$t"]+="$c "
  done
fi

echo ">>> Tables with generated columns:"
if ((${#GEN_COLS_MAP[@]})); then
  for t in "${!GEN_COLS_MAP[@]}"; do
    echo "    $t : ${GEN_COLS_MAP[$t]}"
  done
else
  echo "    (none)"
fi

# ---------------- Dump Schema ----------------
if [[ -n "$SCHEMA_OUT" || -n "$FULL_OUT" ]]; then
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
fi

# ---------------- Dump Data ----------------
if [[ -n "$DATA_OUT" || -n "$FULL_OUT" ]]; then
  echo ">>> Enumerating base tables..."
  mapfile -t TABLES < <("$MYSQL_BIN" "${COMMON_MYSQL_ARGS[@]}" -N -B -e \
    "SHOW FULL TABLES IN \`${SOURCE_DB}\` WHERE Table_type='BASE TABLE';" | awk '{print $1}')

  for tbl in "${TABLES[@]}"; do
    should_process_table "$tbl" || { echo ">>> Skipping (policy) $tbl"; continue; }

    if [[ -n "${GEN_COLS_MAP[$tbl]:-}" ]]; then
      echo ">>> Table $tbl has generated columns -> custom streaming"
      # Collect base (non-generated) column list (backticked CSV) and raw names
      base_cols_csv=$(
        "$MYSQL_BIN" "${COMMON_MYSQL_ARGS[@]}" -N -B -e "
          SELECT GROUP_CONCAT(CONCAT('`',COLUMN_NAME,'`') ORDER BY ORDINAL_POSITION SEPARATOR ',')
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA='${SOURCE_DB}' AND TABLE_NAME='${tbl}'
            AND NOT (EXTRA LIKE '%GENERATED%' OR GENERATION_EXPRESSION IS NOT NULL);
        "
      )
      mapfile -t raw_cols < <("$MYSQL_BIN" "${COMMON_MYSQL_ARGS[@]}" -N -B -e "
          SELECT COLUMN_NAME
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA='${SOURCE_DB}' AND TABLE_NAME='${tbl}'
            AND NOT (EXTRA LIKE '%GENERATED%' OR GENERATION_EXPRESSION IS NOT NULL)
          ORDER BY ORDINAL_POSITION;
        ")

      echo "LOCK TABLES \`$tbl\` WRITE;" >> "$DATA_TMP"
      echo "/*!40000 ALTER TABLE \`$tbl\` DISABLE KEYS */;" >> "$DATA_TMP"

      # Build SELECT list (simple CSV)
      SELECT_LIST=$(printf "%s," "${raw_cols[@]}")
      SELECT_LIST=${SELECT_LIST%,}

      # Stream rows (tab separated)
      "$MYSQL_BIN" "${COMMON_MYSQL_ARGS[@]}" -N -B -e "SELECT ${SELECT_LIST} FROM \`${SOURCE_DB}\`.\`${tbl}\`" | \
      awk -v tbl="$tbl" -v cols="$base_cols_csv" -v batch="$BATCH_SIZE" '
        BEGIN {
          FS="\t"; row_in_batch=0; out_line="";
        }
        function escape(val) {
          if (val == "NULL") return "NULL";
          # Distinguish empty string vs literal NULL:
          # Here an empty field becomes empty string ''
          # Escape backslash and single quote
          gsub(/\\/,"\\\\",val);
          gsub(/\047/,"\\047",val);
          return "'"'"'" val "'"'"'";
        }
        {
          tuple="(";
          for (i=1; i<=NF; i++) {
             if ($i == "") {
               tuple=tuple "'"'"''"'"'";
             } else if ($i == "NULL") {
               tuple=tuple "NULL";
             } else {
               tuple=tuple escape($i);
             }
             if (i < NF) tuple=tuple ",";
          }
          tuple=tuple ")";

          if (row_in_batch == 0) {
             out_line="INSERT INTO `" tbl "` (" cols ") VALUES " tuple;
          } else {
             out_line=out_line ",\n" tuple;
          }
          row_in_batch++;

          if (row_in_batch == batch) {
             print out_line ";";
             row_in_batch=0;
             out_line="";
          }
        }
        END {
          if (row_in_batch > 0) {
             print out_line ";";
          }
        }
      ' >> "$DATA_TMP"

      echo "/*!40000 ALTER TABLE \`$tbl\` ENABLE KEYS */;" >> "$DATA_TMP"
      echo "UNLOCK TABLES;" >> "$DATA_TMP"
      echo "" >> "$DATA_TMP"
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
      echo "" >> "$DATA_TMP"
    fi
  done
fi

# ---------------- Write schema output ----------------
if [[ -n "$SCHEMA_OUT" ]]; then
  echo ">>> Writing schema file: $SCHEMA_OUT"
  write_header "$SCHEMA_OUT"
  if ((GZIP)); then
    gunzip -c "$SCHEMA_OUT" > "${SCHEMA_OUT}.tmp"
    cat "$SCHEMA_TMP" >> "${SCHEMA_OUT}.tmp"
    gzip -c "${SCHEMA_OUT}.tmp" > "$SCHEMA_OUT"
    rm -f "${SCHEMA_OUT}.tmp"
  else
    cat "$SCHEMA_TMP" >> "$SCHEMA_OUT"
  fi
  append_footer "$SCHEMA_OUT"
fi

# ---------------- Write data output ----------------
if [[ -n "$DATA_OUT" ]]; then
  echo ">>> Writing data file: $DATA_OUT"
  write_header "$DATA_OUT"
  if ((GZIP)); then
    gunzip -c "$DATA_OUT" > "${DATA_OUT}.tmp"
    cat "$DATA_TMP" >> "${DATA_OUT}.tmp"
    gzip -c "${DATA_OUT}.tmp" > "$DATA_OUT"
    rm -f "${DATA_OUT}.tmp"
  else
    cat "$DATA_TMP" >> "$DATA_OUT"
  fi
  append_footer "$DATA_OUT"
fi

# ---------------- Write full output ----------------
if [[ -n "$FULL_OUT" ]]; then
  echo ">>> Writing full file: $FULL_OUT"
  write_header "$FULL_OUT"
  if ((GZIP)); then
    gunzip -c "$FULL_OUT" > "${FULL_OUT}.tmp"
    cat "$SCHEMA_TMP" >> "${FULL_OUT}.tmp"
    echo "" >> "${FULL_OUT}.tmp"
    cat "$DATA_TMP" >> "${FULL_OUT}.tmp"
    gzip -c "${FULL_OUT}.tmp" > "$FULL_OUT"
    rm -f "${FULL_OUT}.tmp"
  else
    cat "$SCHEMA_TMP" >> "$FULL_OUT"
    echo "" >> "$FULL_OUT"
    cat "$DATA_TMP" >> "$FULL_OUT"
  fi
  append_footer "$FULL_OUT"
fi

echo "✅ Done."