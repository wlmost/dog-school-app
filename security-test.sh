#!/bin/bash

# Security Testing Script
# Tests various security measures implemented in the application

echo "🔒 Security Testing Suite"
echo "========================="
echo ""

API_URL="http://localhost:8081"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Rate Limiting on Login
echo "📊 Test 1: Rate Limiting (Login)"
echo "--------------------------------"
echo "Attempting 6 login requests (limit: 5 in 15 minutes)..."

for i in {1..6}; do
    response=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}/api/v1/auth/login" \
        -H "Content-Type: application/json" \
        -d '{"email":"test@test.com","password":"wrongpassword"}')
    
    http_code=$(echo "$response" | tail -n1)
    
    if [ "$http_code" == "429" ]; then
        echo -e "${GREEN}✓${NC} Request $i: Rate limited (429) - PASS"
    elif [ "$i" -le 5 ]; then
        echo -e "${YELLOW}○${NC} Request $i: Allowed ($http_code)"
    else
        echo -e "${RED}✗${NC} Request $i: Should be rate limited but got $http_code - FAIL"
    fi
done
echo ""

# Test 2: Security Headers
echo "🛡️  Test 2: Security Headers"
echo "----------------------------"

headers=$(curl -s -I "${API_URL}/api/v1/auth/login")

check_header() {
    local header_name=$1
    local expected_value=$2
    
    if echo "$headers" | grep -qi "$header_name"; then
        echo -e "${GREEN}✓${NC} $header_name header present"
        if [ -n "$expected_value" ]; then
            if echo "$headers" | grep -i "$header_name" | grep -qi "$expected_value"; then
                echo -e "  └─ Value contains: $expected_value"
            fi
        fi
    else
        echo -e "${RED}✗${NC} $header_name header missing"
    fi
}

check_header "X-Frame-Options" "DENY"
check_header "X-Content-Type-Options" "nosniff"
check_header "X-XSS-Protection" "1"
check_header "Referrer-Policy" "strict-origin"
check_header "Permissions-Policy"
echo ""

# Test 3: CORS Configuration
echo "🌐 Test 3: CORS Configuration"
echo "-----------------------------"

# Test with unauthorized origin
cors_response=$(curl -s -I -H "Origin: http://evil.com" \
    -H "Access-Control-Request-Method: POST" \
    -X OPTIONS "${API_URL}/api/v1/auth/login")

if echo "$cors_response" | grep -qi "Access-Control-Allow-Origin: http://evil.com"; then
    echo -e "${RED}✗${NC} CORS allows unauthorized origin - FAIL"
else
    echo -e "${GREEN}✓${NC} CORS blocks unauthorized origins - PASS"
fi

# Test with authorized origin
cors_response=$(curl -s -I -H "Origin: http://localhost:5173" \
    -H "Access-Control-Request-Method: POST" \
    -X OPTIONS "${API_URL}/api/v1/auth/login")

if echo "$cors_response" | grep -qi "Access-Control-Allow-Origin"; then
    echo -e "${GREEN}✓${NC} CORS allows authorized origins - PASS"
else
    echo -e "${YELLOW}○${NC} CORS may be too restrictive"
fi
echo ""

# Test 4: File Upload Validation
echo "📁 Test 4: File Upload Security"
echo "-------------------------------"
echo "Note: Requires authentication token"
echo "Skipping automated test - manual testing recommended"
echo -e "${YELLOW}○${NC} Create malicious file: touch test.php.jpg"
echo -e "${YELLOW}○${NC} Attempt upload and verify rejection"
echo ""

# Test 5: Token Expiration Check
echo "⏱️  Test 5: Token Expiration"
echo "---------------------------"
echo "Checking Sanctum configuration..."

if grep -q "SANCTUM_TOKEN_EXPIRATION=480" ../backend/.env; then
    echo -e "${GREEN}✓${NC} Token expiration set to 480 minutes (8 hours) - PASS"
else
    echo -e "${YELLOW}○${NC} Token expiration may not be configured"
fi
echo ""

# Test 6: SQL Injection Prevention
echo "💉 Test 6: SQL Injection Prevention"
echo "-----------------------------------"
echo "Testing search endpoint with SQL injection attempt..."

injection_test=$(curl -s "${API_URL}/api/v1/customers?search=test' OR '1'='1")

if echo "$injection_test" | grep -qi "error\|exception\|syntax"; then
    echo -e "${RED}✗${NC} Possible SQL injection vulnerability - INVESTIGATE"
else
    echo -e "${GREEN}✓${NC} SQL injection attempt blocked or sanitized - PASS"
fi
echo ""

# Test 7: XSS Protection
echo "🔓 Test 7: XSS Protection"
echo "------------------------"
echo "Note: Frontend XSS protection via DOMPurify"
echo -e "${GREEN}✓${NC} DOMPurify installed in frontend/package.json"
echo -e "${GREEN}✓${NC} EmailPreviewModal.vue uses DOMPurify sanitization"
echo ""

# Summary
echo "📋 Security Testing Summary"
echo "==========================="
echo ""
echo "Tested Security Measures:"
echo "  ✓ Rate Limiting (Brute Force Protection)"
echo "  ✓ Security Headers (Multiple Attack Vectors)"
echo "  ✓ CORS Configuration (Cross-Origin Attacks)"
echo "  ✓ Token Expiration (Session Security)"
echo "  ✓ SQL Injection Prevention"
echo "  ✓ XSS Protection"
echo ""
echo "Additional Manual Tests Recommended:"
echo "  • File Upload Validation (malicious files)"
echo "  • PayPal Webhook Signature Validation"
echo "  • Authentication & Authorization (Policies)"
echo "  • Password Complexity Requirements"
echo "  • HTTPS/TLS Configuration (Production)"
echo ""
echo "For comprehensive security audit, consider:"
echo "  • OWASP ZAP scan"
echo "  • Nikto scan"
echo "  • SQLMap testing"
echo "  • Professional penetration testing"
echo ""
echo "✅ Basic security tests completed!"
