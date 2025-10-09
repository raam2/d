#!/bin/bash

# Database Normalization Deployment Script
# This script automates the deployment of database normalization changes
# Usage: ./deploy_normalization.sh [test|production]

set -e  # Exit on any error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
ENVIRONMENT=${1:-test}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="${SCRIPT_DIR}/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Database credentials (override with environment variables)
DB_HOST=${DB_HOST:-"localhost"}
DB_PORT=${DB_PORT:-"3306"}
DB_USER=${DB_USER:-"root"}
DB_NAME=${DB_NAME:-"u184420243_jayanti_enter4"}

# Production overrides
if [ "$ENVIRONMENT" = "production" ]; then
    DB_HOST=${DB_HOST:-"217.21.95.103"}
    DB_USER=${DB_USER:-"u184420243_gst4"}
    echo -e "${YELLOW}WARNING: Running in PRODUCTION mode!${NC}"
    echo -n "Database password for $DB_USER: "
    read -s DB_PASS
    echo
else
    echo -e "${GREEN}Running in TEST mode${NC}"
    echo -n "Database password for $DB_USER: "
    read -s DB_PASS
    echo
fi

# MySQL command with credentials
MYSQL_CMD="mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} ${DB_NAME}"

# Function to print colored messages
print_step() {
    echo -e "${GREEN}[STEP]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Function to check if MySQL is accessible
check_mysql_connection() {
    print_step "Checking MySQL connection..."
    if echo "SELECT 1;" | $MYSQL_CMD > /dev/null 2>&1; then
        print_success "MySQL connection successful"
        return 0
    else
        print_error "Cannot connect to MySQL server"
        exit 1
    fi
}

# Function to create backup directory
create_backup_dir() {
    print_step "Creating backup directory..."
    mkdir -p "$BACKUP_DIR"
    print_success "Backup directory ready: $BACKUP_DIR"
}

# Function to backup database
backup_database() {
    print_step "Backing up database..."
    BACKUP_FILE="${BACKUP_DIR}/backup_${ENVIRONMENT}_${TIMESTAMP}.sql"
    
    mysqldump -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -p"${DB_PASS}" \
        --single-transaction --routines --triggers \
        "${DB_NAME}" > "$BACKUP_FILE" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        print_success "Database backed up to: $BACKUP_FILE"
        
        # Compress backup
        gzip "$BACKUP_FILE" 2>/dev/null || true
        if [ -f "${BACKUP_FILE}.gz" ]; then
            print_success "Backup compressed: ${BACKUP_FILE}.gz"
        fi
    else
        print_error "Backup failed!"
        exit 1
    fi
}

# Function to run SQL script with validation
run_sql_script() {
    local script_file=$1
    local description=$2
    
    print_step "Running: $description"
    
    if [ ! -f "$script_file" ]; then
        print_error "Script not found: $script_file"
        exit 1
    fi
    
    # Run the script
    if $MYSQL_CMD < "$script_file" 2>&1 | tee "${BACKUP_DIR}/migration_${TIMESTAMP}.log"; then
        print_success "$description completed"
        return 0
    else
        print_error "$description failed"
        print_error "Check log: ${BACKUP_DIR}/migration_${TIMESTAMP}.log"
        return 1
    fi
}

# Function to validate deployment
validate_deployment() {
    print_step "Validating deployment..."
    
    local validation_sql="
SELECT 
    'Tables Created' as check_type,
    COUNT(*) as result
FROM information_schema.tables 
WHERE table_schema = '${DB_NAME}'
  AND table_name IN ('item_name_variants', 'purchase_invoice_header', 'purchase_invoice_line_items')
UNION ALL
SELECT 
    'Views Created' as check_type,
    COUNT(*) as result
FROM information_schema.views
WHERE table_schema = '${DB_NAME}'
  AND table_name LIKE 'v_%'
UNION ALL
SELECT 
    'Procedures Created' as check_type,
    COUNT(*) as result
FROM information_schema.routines
WHERE routine_schema = '${DB_NAME}'
  AND routine_type = 'PROCEDURE'
  AND routine_name LIKE 'sp_%'
UNION ALL
SELECT 
    'Item Variants' as check_type,
    COUNT(*) as result
FROM item_name_variants
UNION ALL
SELECT 
    'Items with HSN' as check_type,
    COUNT(*) as result
FROM items
WHERE hsn_code IS NOT NULL AND hsn_code != '';
"
    
    echo "$validation_sql" | $MYSQL_CMD -t
    
    print_success "Validation completed"
}

# Function to show deployment summary
show_summary() {
    print_step "Deployment Summary"
    
    local summary_sql="
SELECT 'Database' as component, '${DB_NAME}' as value
UNION ALL SELECT 'Environment', '${ENVIRONMENT}'
UNION ALL SELECT 'Timestamp', '${TIMESTAMP}'
UNION ALL SELECT 'Backup Location', '${BACKUP_DIR}/backup_${ENVIRONMENT}_${TIMESTAMP}.sql.gz';

SELECT '=== Data Counts ===' as section;

SELECT 
    'Total Items' as metric,
    COUNT(*) as count
FROM items
UNION ALL
SELECT 
    'Items with HSN Code',
    COUNT(*)
FROM items
WHERE hsn_code IS NOT NULL AND hsn_code != ''
UNION ALL
SELECT 
    'Item Name Variants',
    COUNT(*)
FROM item_name_variants
UNION ALL
SELECT 
    'Purchase Invoice Headers',
    COUNT(*)
FROM purchase_invoice_header
UNION ALL
SELECT 
    'Purchase Invoice Lines',
    COUNT(*)
FROM purchase_invoice_line_items;
"
    
    echo "$summary_sql" | $MYSQL_CMD -t
}

# Main deployment flow
main() {
    echo "========================================"
    echo "Database Normalization Deployment"
    echo "Environment: $ENVIRONMENT"
    echo "Database: $DB_NAME @ $DB_HOST"
    echo "========================================"
    echo
    
    # Confirmation for production
    if [ "$ENVIRONMENT" = "production" ]; then
        print_warning "You are about to modify PRODUCTION database!"
        echo -n "Type 'yes' to continue: "
        read CONFIRM
        if [ "$CONFIRM" != "yes" ]; then
            print_error "Deployment cancelled"
            exit 1
        fi
    fi
    
    # Execute deployment steps
    check_mysql_connection
    create_backup_dir
    backup_database
    
    echo
    print_step "Starting database normalization..."
    echo
    
    # Run normalization script
    if run_sql_script "${SCRIPT_DIR}/database_normalization.sql" "Database Normalization"; then
        print_success "Normalization completed successfully"
    else
        print_error "Normalization failed. Database may be in inconsistent state."
        print_warning "Restore from backup: ${BACKUP_DIR}/backup_${ENVIRONMENT}_${TIMESTAMP}.sql.gz"
        exit 1
    fi
    
    echo
    print_step "Updating application metadata..."
    echo
    
    # Run metadata update
    if run_sql_script "${SCRIPT_DIR}/metadata_update.sql" "Metadata Update"; then
        print_success "Metadata updated successfully"
    else
        print_warning "Metadata update failed, but normalization succeeded"
        print_warning "You can manually run: metadata_update.sql"
    fi
    
    echo
    validate_deployment
    echo
    show_summary
    echo
    
    print_success "=== DEPLOYMENT COMPLETED SUCCESSFULLY ==="
    echo
    echo "Next steps:"
    echo "1. Test the application at: http://your-domain/app/main_entry.php?p=data_diagnostics"
    echo "2. Review the new pages: item_variants, purchase_invoices, data_diagnostics"
    echo "3. Check items missing HSN codes at: ?p=items"
    echo "4. If issues occur, rollback with: database_normalization_rollback.sql"
    echo
    echo "Backup location: ${BACKUP_DIR}/backup_${ENVIRONMENT}_${TIMESTAMP}.sql.gz"
    echo "Migration log: ${BACKUP_DIR}/migration_${TIMESTAMP}.log"
    echo
}

# Run main function
main
