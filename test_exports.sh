#!/bin/bash

# Test script for Zoho Books export utility
# This script verifies that exports work correctly

set -e

echo "=========================================="
echo "Zoho Books Export Utility - Test Suite"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Create exports directory if it doesn't exist
mkdir -p exports

echo -e "${YELLOW}Test 1: PHP Syntax Check${NC}"
php -l zoho_export.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PHP syntax is valid${NC}"
else
    echo -e "${RED}✗ PHP syntax error${NC}"
    exit 1
fi
echo ""

echo -e "${YELLOW}Test 2: Database Connection${NC}"
php -r "require 'db.php'; try { db(); echo 'Connected successfully\n'; } catch (Exception \$e) { echo 'Connection failed: ' . \$e->getMessage() . '\n'; exit(1); }"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Database connection failed${NC}"
    exit 1
fi
echo ""

echo -e "${YELLOW}Test 3: Export Contacts${NC}"
php zoho_export.php contacts
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Contacts export completed${NC}"
    # Check if file was created
    LATEST_CONTACTS=$(ls -t exports/zoho_contacts_*.csv 2>/dev/null | head -1)
    if [ -n "$LATEST_CONTACTS" ]; then
        CONTACT_LINES=$(wc -l < "$LATEST_CONTACTS")
        echo -e "  File: $LATEST_CONTACTS (${CONTACT_LINES} lines)"
    else
        echo -e "${RED}  Warning: No export file found${NC}"
    fi
else
    echo -e "${RED}✗ Contacts export failed${NC}"
fi
echo ""

echo -e "${YELLOW}Test 4: Export Items${NC}"
php zoho_export.php items
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Items export completed${NC}"
    LATEST_ITEMS=$(ls -t exports/zoho_items_*.csv 2>/dev/null | head -1)
    if [ -n "$LATEST_ITEMS" ]; then
        ITEM_LINES=$(wc -l < "$LATEST_ITEMS")
        echo -e "  File: $LATEST_ITEMS (${ITEM_LINES} lines)"
    else
        echo -e "${RED}  Warning: No export file found${NC}"
    fi
else
    echo -e "${RED}✗ Items export failed${NC}"
fi
echo ""

echo -e "${YELLOW}Test 5: Export Sales Invoices${NC}"
php zoho_export.php sales
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Sales invoices export completed${NC}"
    LATEST_SALES=$(ls -t exports/zoho_sales_invoices_*.csv 2>/dev/null | head -1)
    if [ -n "$LATEST_SALES" ]; then
        SALES_LINES=$(wc -l < "$LATEST_SALES")
        echo -e "  File: $LATEST_SALES (${SALES_LINES} lines)"
    else
        echo -e "${RED}  Warning: No export file found${NC}"
    fi
else
    echo -e "${RED}✗ Sales invoices export failed${NC}"
fi
echo ""

echo -e "${YELLOW}Test 6: Export Purchase Invoices${NC}"
php zoho_export.php purchases
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Purchase invoices export completed${NC}"
    LATEST_PURCHASES=$(ls -t exports/zoho_purchase_invoices_*.csv 2>/dev/null | head -1)
    if [ -n "$LATEST_PURCHASES" ]; then
        PURCHASE_LINES=$(wc -l < "$LATEST_PURCHASES")
        echo -e "  File: $LATEST_PURCHASES (${PURCHASE_LINES} lines)"
    else
        echo -e "${RED}  Warning: No export file found${NC}"
    fi
else
    echo -e "${RED}✗ Purchase invoices export failed${NC}"
fi
echo ""

echo -e "${YELLOW}Test 7: Validate CSV Format${NC}"
# Check if latest contacts file has valid CSV structure
LATEST_CONTACTS=$(ls -t exports/zoho_contacts_*.csv 2>/dev/null | head -1)
if [ -n "$LATEST_CONTACTS" ]; then
    # Count commas in header vs first data row
    HEADER_COMMAS=$(head -1 "$LATEST_CONTACTS" | tr -cd ',' | wc -c)
    FIRST_ROW_COMMAS=$(sed -n '2p' "$LATEST_CONTACTS" | tr -cd ',' | wc -c)
    
    if [ "$HEADER_COMMAS" -eq "$FIRST_ROW_COMMAS" ]; then
        echo -e "${GREEN}✓ CSV format appears valid (column count matches)${NC}"
        echo -e "  Columns: $((HEADER_COMMAS + 1))"
    else
        echo -e "${YELLOW}⚠ CSV format warning: column count mismatch${NC}"
        echo -e "  Header: $((HEADER_COMMAS + 1)) columns"
        echo -e "  First row: $((FIRST_ROW_COMMAS + 1)) columns"
    fi
else
    echo -e "${YELLOW}⚠ No CSV file to validate${NC}"
fi
echo ""

echo -e "${YELLOW}Test 8: Check Export Directory${NC}"
EXPORT_COUNT=$(ls exports/zoho_*.csv 2>/dev/null | wc -l)
if [ "$EXPORT_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ Found ${EXPORT_COUNT} export file(s)${NC}"
    echo ""
    echo "Export files:"
    ls -lh exports/zoho_*.csv 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'
else
    echo -e "${RED}✗ No export files found${NC}"
fi
echo ""

echo "=========================================="
echo -e "${GREEN}Test Suite Complete${NC}"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Review export files in ./exports/"
echo "2. Check ZOHO_IMPORT_GUIDE.md for import instructions"
echo "3. Visit https://books.zoho.in/app to import data"
echo ""
