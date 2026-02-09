#!/bin/bash

# Comprehensive API Testing Script
# Tests all user roles and features

API_BASE="http://localhost:8081/api/v1"
RESULTS_FILE="test-results/api-test-results.txt"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
PASSED=0
FAILED=0
TOTAL=0

# Initialize results file
echo "═══════════════════════════════════════" > $RESULTS_FILE
echo "API Testing Results - $(date)" >> $RESULTS_FILE
echo "═══════════════════════════════════════" >> $RESULTS_FILE
echo "" >> $RESULTS_FILE

log_test() {
    local name="$1"
    local status="$2"
    local details="$3"
    
    TOTAL=$((TOTAL + 1))
    
    if [ "$status" == "PASS" ]; then
        echo -e "${GREEN}✓ PASS${NC}: $name"
        echo "✓ PASS: $name" >> $RESULTS_FILE
        PASSED=$((PASSED + 1))
    elif [ "$status" == "FAIL" ]; then
        echo -e "${RED}✗ FAIL${NC}: $name - $details"
        echo "✗ FAIL: $name - $details" >> $RESULTS_FILE
        FAILED=$((FAILED + 1))
    else
        echo -e "${YELLOW}⚠ INFO${NC}: $name - $details"
        echo "⚠ INFO: $name - $details" >> $RESULTS_FILE
    fi
}

# ===== ADMIN TESTS =====
echo ""
echo "═══════════════════════════════════════"
echo "🔧 TESTING ADMIN ROLE"
echo "═══════════════════════════════════════"
echo "" >> $RESULTS_FILE
echo "=== ADMIN ROLE ===" >> $RESULTS_FILE

# Admin Login
echo ""
echo "--- Admin Login ---"
ADMIN_RESPONSE=$(curl -s -X POST "$API_BASE/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"admin@example.com","password":"password"}')

ADMIN_TOKEN=$(echo $ADMIN_RESPONSE | jq -r '.token // empty')

if [ -n "$ADMIN_TOKEN" ] && [ "$ADMIN_TOKEN" != "null" ]; then
    log_test "Admin login successful" "PASS"
else
    log_test "Admin login successful" "FAIL" "No token received"
    echo "Response: $ADMIN_RESPONSE" >> $RESULTS_FILE
    exit 1
fi

# Get Admin User Info
USER_RESPONSE=$(curl -s -X GET "$API_BASE/auth/me" \
    -H "Authorization: Bearer $ADMIN_TOKEN")

USER_ROLE=$(echo $USER_RESPONSE | jq -r '.user.role // empty')

if [ "$USER_ROLE" == "admin" ]; then
    log_test "Admin role correct" "PASS"
else
    log_test "Admin role correct" "FAIL" "Role: $USER_ROLE"
fi

# Test Settings Access
echo ""
echo "--- Settings ---"
SETTINGS_RESPONSE=$(curl -s -X GET "$API_BASE/settings" \
    -H "Authorization: Bearer $ADMIN_TOKEN")

SETTINGS_STATUS=$(echo $SETTINGS_RESPONSE | jq -r '.data // empty')

if [ -n "$SETTINGS_STATUS" ]; then
    log_test "Admin can access settings" "PASS"
    
    # Check for company_small_business setting
    SMALL_BUSINESS=$(echo $SETTINGS_RESPONSE | jq -r '.data[] | select(.key=="company_small_business") | .value')
    log_test "Kleinunternehmerregelung setting exists" "INFO" "Value: $SMALL_BUSINESS"
    
    # Check for company_name
    COMPANY_NAME=$(echo $SETTINGS_RESPONSE | jq -r '.data[] | select(.key=="company_name") | .value')
    log_test "Company name setting exists" "INFO" "Value: $COMPANY_NAME"
else
    log_test "Admin can access settings" "FAIL" "No data"
fi

# Test Update Settings (Toggle Kleinunternehmerregelung)
echo ""
echo "--- Update Kleinunternehmerregelung ---"

# First, set to true
UPDATE_RESPONSE=$(curl -s -X PUT "$API_BASE/settings" \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    -d '[{"key":"company_small_business","value":"true"}]')

if echo $UPDATE_RESPONSE | jq -e '.success' > /dev/null 2>&1; then
    log_test "Set Kleinunternehmerregelung to true" "PASS"
else
    log_test "Set Kleinunternehmerregelung to true" "FAIL" "$(echo $UPDATE_RESPONSE | jq -r '.message // .error // "Unknown error"')"
fi

# Verify it persisted
sleep 1
VERIFY_RESPONSE=$(curl -s -X GET "$API_BASE/settings" \
    -H "Authorization: Bearer $ADMIN_TOKEN")
VERIFY_VALUE=$(echo $VERIFY_RESPONSE | jq -r '.data[] | select(.key=="company_small_business") | .value')

if [ "$VERIFY_VALUE" == "true" ] || [ "$VERIFY_VALUE" == "1" ]; then
    log_test "Kleinunternehmerregelung persisted" "PASS"
else
    log_test "Kleinunternehmerregelung persisted" "FAIL" "Value: $VERIFY_VALUE"
fi

# Set back to false
UPDATE_RESPONSE2=$(curl -s -X PUT "$API_BASE/settings" \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    -d '[{"key":"company_small_business","value":"false"}]')

if echo $UPDATE_RESPONSE2 | jq -e '.success' > /dev/null 2>&1; then
    log_test "Set Kleinunternehmerregelung to false" "PASS"
else
    log_test "Set Kleinunternehmerregelung to false" "FAIL"
fi

# Test Customers
echo ""
echo "--- Customers ---"
CUSTOMERS_RESPONSE=$(curl -s -X GET "$API_BASE/customers" \
    -H "Authorization: Bearer $ADMIN_TOKEN")

CUSTOMERS_DATA=$(echo $CUSTOMERS_RESPONSE | jq -r '.data // empty')

if [ -n "$CUSTOMERS_DATA" ]; then
    CUSTOMER_COUNT=$(echo $CUSTOMERS_RESPONSE | jq -r '.data | length')
    log_test "Admin can view customers" "PASS" "Count: $CUSTOMER_COUNT"
else
    log_test "Admin can view customers" "FAIL"
fi

# Test Create Customer
echo ""
echo "--- Create Customer ---"
TIMESTAMP=$(date +%s)
CREATE_CUSTOMER_RESPONSE=$(curl -s -X POST "$API_BASE/customers" \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"firstName\":\"Test\",\"lastName\":\"Customer\",\"email\":\"test${TIMESTAMP}@example.com\",\"phone\":\"0123456789\"}")

NEW_CUSTOMER_ID=$(echo $CREATE_CUSTOMER_RESPONSE | jq -r '.data.id // empty')

if [ -n "$NEW_CUSTOMER_ID" ] && [ "$NEW_CUSTOMER_ID" != "null" ]; then
    log_test "Create customer" "PASS" "ID: $NEW_CUSTOMER_ID"
else
    log_test "Create customer" "FAIL" "$(echo $CREATE_CUSTOMER_RESPONSE | jq -r '.message // .errors // "Unknown error"')"
fi

# Test Invoices
echo ""
echo "--- Invoices ---"
INVOICES_RESPONSE=$(curl -s -X GET "$API_BASE/invoices" \
    -H "Authorization: Bearer $ADMIN_TOKEN")

INVOICES_DATA=$(echo $INVOICES_RESPONSE | jq -r '.data // empty')

if [ -n "$INVOICES_DATA" ]; then
    INVOICE_COUNT=$(echo $INVOICES_RESPONSE | jq -r '.data | length')
    log_test "Admin can view invoices" "PASS" "Count: $INVOICE_COUNT"
else
    log_test "Admin can view invoices" "FAIL"
fi

# Test Create Invoice (if we have a customer)
if [ -n "$NEW_CUSTOMER_ID" ] && [ "$NEW_CUSTOMER_ID" != "null" ]; then
    echo ""
    echo "--- Create Invoice ---"
    
    TODAY=$(date +%Y-%m-%d)
    DUE_DATE=$(date -v+14d +%Y-%m-%d 2>/dev/null || date -d "+14 days" +%Y-%m-%d)
    
    CREATE_INVOICE_RESPONSE=$(curl -s -X POST "$API_BASE/invoices" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d "{\"customerId\":$NEW_CUSTOMER_ID,\"issueDate\":\"$TODAY\",\"dueDate\":\"$DUE_DATE\",\"status\":\"draft\",\"items\":[{\"description\":\"Hundeschule Kurs\",\"quantity\":5,\"unitPrice\":50.00}],\"notes\":\"Test invoice\"}")
    
    NEW_INVOICE_ID=$(echo $CREATE_INVOICE_RESPONSE | jq -r '.data.id // empty')
    
    if [ -n "$NEW_INVOICE_ID" ] && [ "$NEW_INVOICE_ID" != "null" ]; then
        log_test "Create invoice with pre-filled date" "PASS" "ID: $NEW_INVOICE_ID"
        
        # Check invoice details
        INVOICE_DETAILS=$(curl -s -X GET "$API_BASE/invoices/$NEW_INVOICE_ID" \
            -H "Authorization: Bearer $ADMIN_TOKEN")
        
        INVOICE_TOTAL=$(echo $INVOICE_DETAILS | jq -r '.data.totalAmount // empty')
        log_test "Invoice total calculated" "INFO" "Total: $INVOICE_TOTAL"
    else
        ERROR_MSG=$(echo $CREATE_INVOICE_RESPONSE | jq -r '.message // .errors // "Unknown error"')
        log_test "Create invoice with pre-filled date" "FAIL" "$ERROR_MSG"
        echo "Full response: $CREATE_INVOICE_RESPONSE" >> $RESULTS_FILE
    fi
fi

# ===== TRAINER TESTS =====
echo ""
echo "═══════════════════════════════════════"
echo "👨‍🏫 TESTING TRAINER ROLE"
echo "═══════════════════════════════════════"
echo "" >> $RESULTS_FILE
echo "=== TRAINER ROLE ===" >> $RESULTS_FILE

# Trainer Login
echo ""
echo "--- Trainer Login ---"
TRAINER_RESPONSE=$(curl -s -X POST "$API_BASE/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"trainer@example.com","password":"password"}')

TRAINER_TOKEN=$(echo $TRAINER_RESPONSE | jq -r '.token // empty')

if [ -n "$TRAINER_TOKEN" ] && [ "$TRAINER_TOKEN" != "null" ]; then
    log_test "Trainer login successful" "PASS"
else
    log_test "Trainer login successful" "FAIL" "No token received"
fi

# Trainer role check
TRAINER_USER=$(curl -s -X GET "$API_BASE/auth/me" \
    -H "Authorization: Bearer $TRAINER_TOKEN")

TRAINER_ROLE=$(echo $TRAINER_USER | jq -r '.user.role // empty')

if [ "$TRAINER_ROLE" == "trainer" ]; then
    log_test "Trainer role correct" "PASS"
else
    log_test "Trainer role correct" "FAIL" "Role: $TRAINER_ROLE"
fi

# Test Trainer access to customers
echo ""
echo "--- Trainer Access Control ---"
TRAINER_CUSTOMERS=$(curl -s -X GET "$API_BASE/customers" \
    -H "Authorization: Bearer $TRAINER_TOKEN")

TRAINER_CUSTOMERS_STATUS=$(echo $TRAINER_CUSTOMERS | jq -r '.data // .message // empty')

if echo $TRAINER_CUSTOMERS | jq -e '.data' > /dev/null 2>&1; then
    log_test "Trainer can access customers" "PASS"
else
    log_test "Trainer can access customers" "INFO" "Access denied (expected)"
fi

# Test Trainer access to invoices
TRAINER_INVOICES=$(curl -s -X GET "$API_BASE/invoices" \
    -H "Authorization: Bearer $TRAINER_TOKEN")

if echo $TRAINER_INVOICES | jq -e '.data' > /dev/null 2>&1; then
    log_test "Trainer can access invoices" "PASS"
else
    log_test "Trainer can access invoices" "INFO" "Access denied (expected)"
fi

# Test Trainer access to settings (should fail)
TRAINER_SETTINGS=$(curl -s -X GET "$API_BASE/settings" \
    -H "Authorization: Bearer $TRAINER_TOKEN")

if echo $TRAINER_SETTINGS | jq -e '.data' > /dev/null 2>&1; then
    log_test "Trainer cannot access settings" "FAIL" "Trainer has access (should not)"
else
    log_test "Trainer cannot access settings" "PASS"
fi

# ===== CUSTOMER TESTS =====
echo ""
echo "═══════════════════════════════════════"
echo "👤 TESTING CUSTOMER ROLE"
echo "═══════════════════════════════════════"
echo "" >> $RESULTS_FILE
echo "=== CUSTOMER ROLE ===" >> $RESULTS_FILE

# Customer Login
echo ""
echo "--- Customer Login ---"
CUSTOMER_RESPONSE=$(curl -s -X POST "$API_BASE/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"customer@example.com","password":"password"}')

CUSTOMER_TOKEN=$(echo $CUSTOMER_RESPONSE | jq -r '.token // empty')

if [ -n "$CUSTOMER_TOKEN" ] && [ "$CUSTOMER_TOKEN" != "null" ]; then
    log_test "Customer login successful" "PASS"
else
    log_test "Customer login successful" "FAIL" "No token received"
fi

# Customer role check
CUSTOMER_USER=$(curl -s -X GET "$API_BASE/auth/me" \
    -H "Authorization: Bearer $CUSTOMER_TOKEN")

CUSTOMER_ROLE=$(echo $CUSTOMER_USER | jq -r '.user.role // empty')

if [ "$CUSTOMER_ROLE" == "customer" ]; then
    log_test "Customer role correct" "PASS"
else
    log_test "Customer role correct" "FAIL" "Role: $CUSTOMER_ROLE"
fi

# Test Customer access
echo ""
echo "--- Customer Access Control ---"

# Customer should NOT access all customers
CUSTOMER_CUSTOMERS=$(curl -s -X GET "$API_BASE/customers" \
    -H "Authorization: Bearer $CUSTOMER_TOKEN")

if echo $CUSTOMER_CUSTOMERS | jq -e '.data' > /dev/null 2>&1; then
    log_test "Customer cannot access all customers" "FAIL" "Customer has access (should not)"
else
    log_test "Customer cannot access all customers" "PASS"
fi

# Customer should see their own invoices
CUSTOMER_INVOICES=$(curl -s -X GET "$API_BASE/invoices" \
    -H "Authorization: Bearer $CUSTOMER_TOKEN")

if echo $CUSTOMER_INVOICES | jq -e '.data' > /dev/null 2>&1; then
    CUSTOMER_INVOICE_COUNT=$(echo $CUSTOMER_INVOICES | jq -r '.data | length')
    log_test "Customer can view own invoices" "PASS" "Count: $CUSTOMER_INVOICE_COUNT"
else
    log_test "Customer can view own invoices" "FAIL"
fi

# Customer should NOT access settings
CUSTOMER_SETTINGS=$(curl -s -X GET "$API_BASE/settings" \
    -H "Authorization: Bearer $CUSTOMER_TOKEN")

if echo $CUSTOMER_SETTINGS | jq -e '.data' > /dev/null 2>&1; then
    log_test "Customer cannot access settings" "FAIL" "Customer has access (should not)"
else
    log_test "Customer cannot access settings" "PASS"
fi

# ===== SUMMARY =====
echo ""
echo "═══════════════════════════════════════"
echo "📊 TEST SUMMARY"
echo "═══════════════════════════════════════"
echo "" >> $RESULTS_FILE
echo "=== SUMMARY ===" >> $RESULTS_FILE
echo "✓ Passed: $PASSED"
echo "✗ Failed: $FAILED"
echo "Total: $TOTAL"
echo "✓ Passed: $PASSED" >> $RESULTS_FILE
echo "✗ Failed: $FAILED" >> $RESULTS_FILE
echo "Total: $TOTAL" >> $RESULTS_FILE

if [ $FAILED -gt 0 ]; then
    echo ""
    echo -e "${RED}⚠ There are failed tests. See $RESULTS_FILE for details.${NC}"
    exit 1
else
    echo ""
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
fi
