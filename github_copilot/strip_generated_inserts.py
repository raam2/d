#!/usr/bin/env python3
"""
strip_generated_inserts.py

Reads mysqldump output (one-row-per-INSERT form for a single table) from stdin
and rewrites INSERT lines to exclude generated columns’ values.

Usage (invoked by portable_dump.sh):
  strip_generated_inserts.py <table_name> "<generated_columns_space_sep>" <source_db> <user> <host> <port>

Strategy:
  1. Fetch full ordered column list from information_schema for the table.
  2. Compute indexes of generated columns.
  3. For each line starting with INSERT INTO `table` VALUES (...):
       - Parse the parenthesized value list robustly (comma-aware inside quotes).
       - Drop generated column positions.
       - Output INSERT with explicit column list of remaining columns.

Assumptions:
  - Input lines are single-row inserts: INSERT INTO `t` VALUES (...);
  - No multiline value tuples (mysqldump --skip-extended-insert guarantees this).
"""
import sys
import re
import pymysql  # If not available, fallback to simple mysqlclient via CLI is possible, but pymysql is cleaner.

# Minimal fallback if pymysql isn't installed:
try:
    import pymysql  # noqa
except ImportError:
    pymysql = None

def fatal(msg):
    print(f"-- ERROR strip_generated_inserts: {msg}", file=sys.stderr)
    sys.exit(1)

if len(sys.argv) < 7:
    fatal("Args: table generated_cols source_db user host port")

table = sys.argv[1]
gen_cols_raw = sys.argv[2].strip()
source_db = sys.argv[3]
user = sys.argv[4]
host = sys.argv[5]
port = int(sys.argv[6])

generated_cols = [c for c in gen_cols_raw.split() if c != 'NULL']

# Connect to DB to get column order
if pymysql is None:
    fatal("pymysql module required; install with: pip install pymysql")

conn = pymysql.connect(host=host, user=user, database=source_db, port=port, charset="utf8mb4")
with conn.cursor() as cur:
    cur.execute("""
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s
        ORDER BY ORDINAL_POSITION
    """, (source_db, table))
    ordered_cols = [row[0] for row in cur.fetchall()]

gen_set = set(generated_cols)
keep_cols = [c for c in ordered_cols if c not in gen_set]
gen_indexes = {i for i, c in enumerate(ordered_cols)}

if not keep_cols:
    fatal(f"All columns are generated? Table {table}")

# Regex for matching start of insert
prefix_pattern = re.compile(rf"^INSERT INTO `({re.escape(table)})` VALUES \(")

def split_values(val_str):
    """Split a parenthesized values string by commas respecting quotes and backslash escapes."""
    parts = []
    buf = []
    in_str = False
    escape = False
    quote_char = ''
    for ch in val_str:
        if escape:
            buf.append(ch)
            escape = False
            continue
        if ch == '\\':
            buf.append(ch)
            escape = True
            continue
        if in_str:
            buf.append(ch)
            if ch == quote_char:
                in_str = False
            continue
        else:
            if ch in ("'", '"'):
                in_str = True
                quote_char = ch
                buf.append(ch)
                continue
            if ch == ',':
                parts.append(''.join(buf).strip())
                buf = []
            else:
                buf.append(ch)
    if buf:
        parts.append(''.join(buf).strip())
    return parts

for line in sys.stdin:
    if not line.startswith("INSERT INTO `"+table+"` VALUES ("):
        # Pass other lines through unchanged
        sys.stdout.write(line)
        continue

    # Extract inner values portion
    m = prefix_pattern.match(line)
    if not m:
        sys.stdout.write(line)
        continue

    # Remove leading 'INSERT INTO ... VALUES (' and trailing ');\n'
    inner = line.strip()
    assert inner.endswith(");"), f"Unexpected line format for {table}: {line}"
    inner_vals = inner[len(f"INSERT INTO `{table}` VALUES ("):-2]

    # Split top-level commas
    values = split_values(inner_vals)

    if len(values) != len(ordered_cols):
        # Mismatch — output a comment + pass original
        sys.stdout.write(f"-- WARNING: column/value count mismatch for {table} line skipped\n")
        sys.stdout.write(line)
        continue

    new_values = [v for idx, v in enumerate(values) if ordered_cols[idx] in keep_cols]

    out = f"INSERT INTO `{table}` ({','.join('`'+c.replace('`','``')+'`' for c in keep_cols)}) VALUES ({', '.join(new_values)});\n"
    sys.stdout.write(out)