#!/usr/bin/env bash
# clean_db_dump.sh
# Create a portable dump (schema + data + routines + triggers + events)
# Strips DEFINER clauses / session boilerplate
# Rewrites INSERTs for tables having generated columns so those columns are excluded
# Optional: --schema-only

set -euo pipefail

if [[ $# -lt 3 ]]; then
  echo "Usage: $0 [--schema-only] <source_db> <target_db> <output_file.sql>"
  exit 1
fi

SCHEMA_ONLY=0
if [[ "${1:-}" == "--schema-only" ]]; then
  SCHEMA_ONLY=1
  shift
fi

DB_SOURCE="$1"
DB_TARGET="$2"
OUTFILE="$3"

MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3306"
MYSQL_USER="gstwork"
# Password: use MYSQL_PWD env var or .my.cnf for safety (avoid -p inline)

echo ">>> Inspecting generated columns..."
GEN_INFO=$(mysql -u "$MYSQL_USER" -h "$MYSQL_HOST" -P "$MYSQL_PORT" -N -B <<SQL
SELECT TABLE_NAME,
       GROUP_CONCAT(CASE WHEN EXTRA LIKE '%GENERATED%' THEN COLUMN_NAME END ORDER BY ORDINAL_POSITION),
       GROUP_CONCAT(CASE WHEN EXTRA NOT LIKE '%GENERATED%' THEN CONCAT('\\\\\`',COLUMN_NAME,'\\\\\`') END ORDER BY ORDINAL_POSITION)
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA='${DB_SOURCE}'
GROUP BY TABLE_NAME;
SQL
)

declare -A TABLE_GEN_COLS
declare -A TABLE_BASE_COLS
while IFS=$'\t' read -r t g b; do
  [[ -z "$t" ]] && continue
  if [[ -n "$g" ]]; then
    TABLE_GEN_COLS["$t"]="$g"
    TABLE_BASE_COLS["$t"]="$b"
  fi
done <<< "$GEN_INFO"

echo ">>> Tables with generated columns:"
for t in "${!TABLE_GEN_COLS[@]}"; do
  echo "    $t : generated(${TABLE_GEN_COLS[$t]})"
done

TMP_RAW=$(mktemp)
trap 'rm -f "$TMP_RAW"' EXIT

echo ">>> Running initial mysqldump..."

DUMP_ARGS=(
  --default-character-set=utf8mb4
  -u "$MYSQL_USER"
  -h "$MYSQL_HOST"
  -P "$MYSQL_PORT"
  --skip-comments
  --skip-dump-date
  --routines
  --triggers
  --events
  --single-transaction
  --quick
  "$DB_SOURCE"
)

if [[ $SCHEMA_ONLY -eq 1 ]]; then
  DUMP_ARGS+=(--no-data)
fi

mysqldump "${DUMP_ARGS[@]}" > "$TMP_RAW"

echo ">>> Stripping DEFINER and session boilerplate..."
# Remove DEFINER
sed -i -E 's/DEFINER[ ]*=[^ ]+//g' "$TMP_RAW"
# Remove session SET lines
sed -i '/^\/\*!50001 SET /d;/^\/\*!50013/d;/^\/\*!401/d' "$TMP_RAW"

# Prepare header
{
  echo "CREATE DATABASE IF NOT EXISTS \`$DB_TARGET\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  echo "USE \`$DB_TARGET\`;"
  echo "SET sql_notes=0;"
} > "$OUTFILE"

# Copy raw (will still have original INSERTs we may rewrite)
cat "$TMP_RAW" >> "$OUTFILE"

if [[ $SCHEMA_ONLY -eq 0 && ${#TABLE_GEN_COLS[@]} -gt 0 ]]; then
  echo ">>> Rewriting INSERTs for generated-column tables..."

  # For each table with generated columns:
  for tbl in "${!TABLE_GEN_COLS[@]}"; do
    base_cols="${TABLE_BASE_COLS[$tbl]}"
    # Skip if somehow base_cols empty
    [[ -z "$base_cols" ]] && continue

    # Extract data fresh (excluding generated columns)
    echo "    Processing $tbl ..."

    # Build SELECT list without generated columns
    SELECT_LIST=$(mysql -u "$MYSQL_USER" -h "$MYSQL_HOST" -P "$MYSQL_PORT" -N -B <<SQL
SELECT GROUP_CONCAT(CONCAT('QUOTE(',COLUMN_NAME,')') ORDER BY ORDINAL_POSITION)
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA='${DB_SOURCE}'
  AND TABLE_NAME='${tbl}'
  AND EXTRA NOT LIKE '%GENERATED%';
SQL
)

    # If table is big, you might want to stream row by row; here we concat.
    DATA_INSERT=$(mysql -u "$MYSQL_USER" -h "$MYSQL_HOST" -P "$MYSQL_PORT" -N -B <<SQL
SET SESSION group_concat_max_len=18446744073709551615;
SELECT CONCAT(
  'LOCK TABLES \\\`$tbl\\\` WRITE;\\n/*!40000 ALTER TABLE \\\`$tbl\\\` DISABLE KEYS */;\\n',
  IF(COUNT(*)=0,'',CONCAT('INSERT INTO \\\`$tbl\\\` ($base_cols) VALUES ',
     GROUP_CONCAT(CONCAT('(', REPLACE(CONCAT_WS(',', $SELECT_LIST), '\\\\','\\\\\\\\'), ')') SEPARATOR ',\\n'),
     ';')),
  '\\n/*!40000 ALTER TABLE \\\`$tbl\\\` ENABLE KEYS */;\\nUNLOCK TABLES;\\n'
)
FROM \`$DB_SOURCE\`.\`$tbl\`;
SQL
)

    # Remove original INSERT blocks for this table from OUTFILE, then append new block at end.
    # Original pattern: INSERT INTO `tbl` VALUES( ...
    perl -0777 -i -pe "
      s/INSERT INTO \`$tbl\` VALUES\s*\([^;]*;//gs
    " "$OUTFILE"

    # Append reconstructed data block
    {
      echo ""
      echo "-- Rewritten data block for $tbl (generated columns excluded)"
      echo "$DATA_INSERT"
    } >> "$OUTFILE"
  done
fi

echo "SET sql_notes=1;" >> "$OUTFILE"

echo "✅ Clean portable dump written to: $OUTFILE"
