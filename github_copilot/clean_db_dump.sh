#!/bin/bash
# Usage: ./clean_db_dump.sh <source_db> <target_db> <output_file.sql>

if [ $# -ne 3 ]; then
  echo "Usage: $0 <source_db> <target_db> <output_file.sql>"
  exit 1
fi

DB_SOURCE="$1"
DB_TARGET="$2"
OUTFILE="$3"

# Step 1: Dump schema + data (no comments, no dump date)
#!/bin/bash
# Usage: ./clean_db_dump.sh <source_db> <target_db> <output_file.sql>

if [ $# -ne 3 ]; then
  echo "Usage: $0 <source_db> <target_db> <output_file.sql>"
  exit 1
fi

DB_SOURCE="$1"
DB_TARGET="$2"
OUTFILE="$3"

# Step 1: Dump schema + data (no comments, no dump date)
mariadb-dump --default-character-set=utf8mb4 \
  -u gstwork -p'gstwork@123' \
  -h 127.0.0.1 -P 3306 \
  --skip-comments \
  --skip-dump-date \
  "$DB_SOURCE" > "$OUTFILE"

# Step 2: Strip DEFINER clauses
sed -i -E 's/DEFINER[ ]*=[^ ]+//g' "$OUTFILE"

# Step 3: Remove session boilerplate lines
sed -i '/^\/\*!50001 SET /d;/^\/\*!50013/d;/^\/\*!401/d' "$OUTFILE"

# Step 4: Prepend clean CREATE DATABASE header
TMPFILE=$(mktemp)
cat > "$TMPFILE" <<EOF
CREATE DATABASE IF NOT EXISTS \`$DB_TARGET\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE \`$DB_TARGET\`;
EOF
cat "$OUTFILE" >> "$TMPFILE"
mv "$TMPFILE" "$OUTFILE"

echo "✅ Clean dump with data created at: $OUTFILE"


# Step 2: Strip DEFINER clauses
sed -i -E 's/DEFINER[ ]*=[^ ]+//g' "$OUTFILE"

# Step 3: Remove session boilerplate lines
sed -i '/^\/\*!50001 SET /d;/^\/\*!50013/d;/^\/\*!401/d' "$OUTFILE"

# Step 4: Prepend clean CREATE DATABASE header
TMPFILE=$(mktemp)
cat > "$TMPFILE" <<EOF
CREATE DATABASE IF NOT EXISTS \`$DB_TARGET\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE \`$DB_TARGET\`;
EOF
cat "$OUTFILE" >> "$TMPFILE"
mv "$TMPFILE" "$OUTFILE"

echo "✅ Clean dump with data created at: $OUTFILE"
