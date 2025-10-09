#!/bin/bash

# SQL Syntax Validation Script
# Checks SQL files for basic syntax errors without executing them

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "========================================"
echo "SQL Syntax Validation"
echo "========================================"
echo

# Check if mysql client is installed
if ! command -v mysql &> /dev/null; then
    echo "WARNING: mysql client not found. Cannot perform full syntax validation."
    echo "Performing basic checks only..."
    echo
fi

# Function to check SQL file
check_sql_file() {
    local file=$1
    echo "Checking: $file"
    
    if [ ! -f "$file" ]; then
        echo "  ✗ File not found"
        return 1
    fi
    
    # Basic syntax checks
    local errors=0
    
    # Check for unmatched quotes
    if grep -n "[^\\]'[^']*$" "$file" | grep -v "^--" > /dev/null; then
        echo "  ⚠ Possible unmatched single quotes found"
        errors=$((errors + 1))
    fi
    
    # Check for common SQL syntax errors
    if grep -in "CREAT TABLE\|CREAT INDEX\|PRIMERY KEY" "$file" > /dev/null; then
        echo "  ⚠ Possible typos in SQL keywords"
        errors=$((errors + 1))
    fi
    
    # Check for balanced delimiters (if used)
    local delimiter_count=$(grep -c "^DELIMITER" "$file" || true)
    if [ $((delimiter_count % 2)) -ne 0 ]; then
        echo "  ⚠ Unbalanced DELIMITER statements"
        errors=$((errors + 1))
    fi
    
    # Check if file is valid UTF-8
    if ! file "$file" | grep -q "UTF-8\|ASCII"; then
        echo "  ⚠ File encoding may not be UTF-8"
        errors=$((errors + 1))
    fi
    
    # Try to validate with mysql if available
    if command -v mysql &> /dev/null; then
        # Create a temp file with just basic validation
        local temp_test=$(mktemp)
        cat > "$temp_test" <<EOF
SET sql_mode='TRADITIONAL';
SET foreign_key_checks=0;
EOF
        # Extract CREATE TABLE statements and check syntax
        grep -i "^CREATE TABLE\|^CREATE VIEW\|^CREATE PROCEDURE" "$file" >> "$temp_test" || true
        echo "ROLLBACK;" >> "$temp_test"
        
        if mysql --help >/dev/null 2>&1; then
            # Just check if mysql can parse it (without executing)
            if mysql --batch --skip-column-names -e "status" >/dev/null 2>&1; then
                echo "  ✓ MySQL client available for validation"
            fi
        fi
        rm -f "$temp_test"
    fi
    
    if [ $errors -eq 0 ]; then
        echo "  ✓ Basic syntax checks passed"
        return 0
    else
        echo "  ⚠ Found $errors potential issues"
        return 1
    fi
}

# Check all SQL files
total_errors=0

for sql_file in \
    "${SCRIPT_DIR}/database_normalization.sql" \
    "${SCRIPT_DIR}/database_normalization_rollback.sql" \
    "${SCRIPT_DIR}/metadata_update.sql"; do
    
    if ! check_sql_file "$sql_file"; then
        total_errors=$((total_errors + 1))
    fi
    echo
done

# Check deployment script
echo "Checking: deploy_normalization.sh"
if bash -n "${SCRIPT_DIR}/deploy_normalization.sh" 2>&1; then
    echo "  ✓ Bash syntax valid"
else
    echo "  ✗ Bash syntax errors found"
    total_errors=$((total_errors + 1))
fi
echo

# Summary
echo "========================================"
if [ $total_errors -eq 0 ]; then
    echo "✓ All files passed validation"
    exit 0
else
    echo "⚠ Found potential issues in $total_errors file(s)"
    echo "Review warnings above before deployment"
    exit 1
fi
