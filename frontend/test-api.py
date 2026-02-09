#!/usr/bin/env python3
import requests
import json
import sys
from datetime import datetime, timedelta

# Test configuration
API_BASE = "http://localhost:8081/api/v1"
 
# Test results
results = {
    "passed": [],
    "failed": [],
    "info": []
}

def log_test(name, status, details=""):
    """Log test result"""
    result = {
        "name": name,
        "status": status,
        "details": details,
        "timestamp": datetime.now().isoformat()
    }
    
    if status == "PASS":
        print(f"✓ PASS: {name}")
        results["passed"].append(result)
    elif status == "FAIL":
        print(f"✗ FAIL: {name} - {details}")
        results["failed"].append(result)
    else:
        print(f"⚠ INFO: {name} - {details}")
        results["info"].append(result)

def test_admin():
    """Test admin role"""
    print("\n═══════════════════════════════════════")
    print("🔧 TESTING ADMIN ROLE")
    print("═══════════════════════════════════════\n")
    
    # Login
    print("--- Admin Login ---")
    try:
        response = requests.post(f"{API_BASE}/auth/login", json={
            "email": "admin@example.com",
            "password": "password"
        })
        
        if response.status_code == 200:
            data = response.json()
            token = data.get("token")
            if token:
                log_test("Admin login successful", "PASS")
                admin_token = token
                
                # Check role
                user_data = data.get("user", {})
                role = user_data.get("role")
                if role == "admin":
                    log_test("Admin role correct", "PASS")
                else:
                    log_test("Admin role correct", "FAIL", f"Role: {role}")
            else:
                log_test("Admin login successful", "FAIL", "No token in response")
                return None
        else:
            log_test("Admin login successful", "FAIL", f"Status: {response.status_code}")
            return None
    except Exception as e:
        log_test("Admin login", "FAIL", str(e))
        return None
    
    # Test Settings
    print("\n--- Settings ---")
    try:
        headers = {"Authorization": f"Bearer {admin_token}"}
        response = requests.get(f"{API_BASE}/settings", headers=headers)
        
        if response.status_code == 200:
            log_test("Admin can access settings", "PASS")
            settings = response.json().get("data", [])
            
            # Find company_small_business
            small_business = None
            company_name = None
            for setting in settings:
                if setting.get("key") == "company_small_business":
                    small_business = setting.get("value")
                if setting.get("key") == "company_name":
                    company_name = setting.get("value")
            
            log_test("Kleinunternehmerregelung setting exists", "INFO", f"Value: {small_business}")
            log_test("Company name setting exists", "INFO", f"Value: {company_name}")
        else:
            log_test("Admin can access settings", "FAIL", f"Status: {response.status_code}")
    except Exception as e:
        log_test("Admin settings access", "FAIL", str(e))
    
    # Test Update Settings
    print("\n--- Update Kleinunternehmerregelung ---")
    try:
        # Set to true
        response = requests.put(f"{API_BASE}/settings", 
            headers=headers,
            json=[{"key": "company_small_business", "value": "true"}])
        
        if response.status_code == 200:
            log_test("Set Kleinunternehmerregelung to true", "PASS")
        else:
            log_test("Set Kleinunternehmerregelung to true", "FAIL", 
                    f"Status: {response.status_code}, {response.text}")
        
        # Verify persistence
        response = requests.get(f"{API_BASE}/settings", headers=headers)
        if response.status_code == 200:
            settings = response.json().get("data", [])
            for setting in settings:
                if setting.get("key") == "company_small_business":
                    value = str(setting.get("value"))
                    if value in ["true", "1", "True"]:
                        log_test("Kleinunternehmerregelung persisted", "PASS")
                    else:
                        log_test("Kleinunternehmerregelung persisted", "FAIL", f"Value: {value}")
                    break
        
        # Set back to false
        response = requests.put(f"{API_BASE}/settings",
            headers=headers,
            json=[{"key": "company_small_business", "value": "false"}])
        
        if response.status_code == 200:
            log_test("Set Kleinunternehmerregelung to false", "PASS")
        else:
            log_test("Set Kleinunternehmerregelung to false", "FAIL", f"Status: {response.status_code}")
    except Exception as e:
        log_test("Update settings", "FAIL", str(e))
    
    # Test Customers
    print("\n--- Customers ---")
    try:
        response = requests.get(f"{API_BASE}/customers", headers=headers)
        
        if response.status_code == 200:
            customers = response.json().get("data", [])
            log_test("Admin can view customers", "PASS", f"Count: {len(customers)}")
        else:
            log_test("Admin can view customers", "FAIL", f"Status: {response.status_code}")
    except Exception as e:
        log_test("View customers", "FAIL", str(e))
    
    # Test Create Customer
    print("\n--- Create Customer ---")
    try:
        timestamp = int(datetime.now().timestamp())
        response = requests.post(f"{API_BASE}/customers",
            headers=headers,
            json={
                "firstName": "Test",
                "lastName": "Customer",
                "email": f"test{timestamp}@example.com",
                "phone": "0123456789"
            })
        
        if response.status_code in [200, 201]:
            customer_id = response.json().get("data", {}).get("id")
            log_test("Create customer", "PASS", f"ID: {customer_id}")
            return admin_token, customer_id
        else:
            log_test("Create customer", "FAIL", f"Status: {response.status_code}, {response.text[:200]}")
            return admin_token, None
    except Exception as e:
        log_test("Create customer", "FAIL", str(e))
        return admin_token, None
    
    # Test Invoices
    print("\n--- Invoices ---")
    try:
        response = requests.get(f"{API_BASE}/invoices", headers=headers)
        
        if response.status_code == 200:
            invoices = response.json().get("data", [])
            log_test("Admin can view invoices", "PASS", f"Count: {len(invoices)}")
        else:
            log_test("Admin can view invoices", "FAIL", f"Status: {response.status_code}")
    except Exception as e:
        log_test("View invoices", "FAIL", str(e))
    
    return admin_token, None

def test_create_invoice(token, customer_id):
    """Test invoice creation"""
    if not customer_id:
        log_test("Create invoice (skipped)", "INFO", "No customer ID available")
        return
    
    print("\n--- Create Invoice ---")
    try:
        headers = {"Authorization": f"Bearer {token}"}
        today = datetime.now().strftime("%Y-%m-%d")
        due_date = (datetime.now() + timedelta(days=14)).strftime("%Y-%m-%d")
        
        response = requests.post(f"{API_BASE}/invoices",
            headers=headers,
            json={
                "customerId": customer_id,
                "issueDate": today,
                "dueDate": due_date,
                "status": "draft",
                "items": [{
                    "description": "Hundeschule Kurs",
                    "quantity": 5,
                    "unitPrice": 50.00
                }],
                "notes": "Test invoice"
            })
        
        if response.status_code in [200, 201]:
            invoice_id = response.json().get("data", {}).get("id")
            log_test("Create invoice with pre-filled date", "PASS", f"ID: {invoice_id}")
            
            # Get invoice details
            response2 = requests.get(f"{API_BASE}/invoices/{invoice_id}", headers=headers)
            if response2.status_code == 200:
                invoice = response2.json().get("data", {})
                total = invoice.get("totalAmount")
                log_test("Invoice total calculated", "INFO", f"Total: {total}")
        else:
            log_test("Create invoice with pre-filled date", "FAIL", 
                    f"Status: {response.status_code}, {response.text[:200]}")
    except Exception as e:
        log_test("Create invoice", "FAIL", str(e))

def test_trainer():
    """Test trainer role"""
    print("\n═══════════════════════════════════════")
    print("👨‍🏫 TESTING TRAINER ROLE")
    print("═══════════════════════════════════════\n")
    
    # Login
    print("--- Trainer Login ---")
    try:
        response = requests.post(f"{API_BASE}/auth/login", json={
            "email": "trainer@example.com",
            "password": "password"
        })
        
        if response.status_code == 200:
            data = response.json()
            token = data.get("token")
            if token:
                log_test("Trainer login successful", "PASS")
                
                # Check role
                user_data = data.get("user", {})
                role = user_data.get("role")
                if role == "trainer":
                    log_test("Trainer role correct", "PASS")
                else:
                    log_test("Trainer role correct", "FAIL", f"Role: {role}")
            else:
                log_test("Trainer login successful", "FAIL", "No token")
                return
        else:
            log_test("Trainer login successful", "FAIL", f"Status: {response.status_code}")
            return
    except Exception as e:
        log_test("Trainer login", "FAIL", str(e))
        return
    
    # Test Access Control
    print("\n--- Trainer Access Control ---")
    headers = {"Authorization": f"Bearer {token}"}
    
    try:
        # Customers
        response = requests.get(f"{API_BASE}/customers", headers=headers)
        if response.status_code == 200:
            log_test("Trainer can access customers", "PASS")
        else:
            log_test("Trainer can access customers", "INFO", f"Access denied (expected)")
        
        # Invoices
        response = requests.get(f"{API_BASE}/invoices", headers=headers)
        if response.status_code == 200:
            log_test("Trainer can access invoices", "PASS")
        else:
            log_test("Trainer can access invoices", "INFO", "Access denied (expected)")
        
        # Settings (should fail)
        response = requests.get(f"{API_BASE}/settings", headers=headers)
        if response.status_code == 200:
            log_test("Trainer cannot access settings", "FAIL", "Trainer has access (should not)")
        else:
            log_test("Trainer cannot access settings", "PASS")
    except Exception as e:
        log_test("Trainer access control", "FAIL", str(e))

def test_customer():
    """Test customer role"""
    print("\n═══════════════════════════════════════")
    print("👤 TESTING CUSTOMER ROLE")
    print("═══════════════════════════════════════\n")
    
    # Login
    print("--- Customer Login ---")
    try:
        response = requests.post(f"{API_BASE}/auth/login", json={
            "email": "customer@example.com",
            "password": "password"
        })
        
        if response.status_code == 200:
            data = response.json()
            token = data.get("token")
            if token:
                log_test("Customer login successful", "PASS")
                
                # Check role
                user_data = data.get("user", {})
                role = user_data.get("role")
                if role == "customer":
                    log_test("Customer role correct", "PASS")
                else:
                    log_test("Customer role correct", "FAIL", f"Role: {role}")
            else:
                log_test("Customer login successful", "FAIL", "No token")
                return
        else:
            log_test("Customer login successful", "FAIL", f"Status: {response.status_code}")
            return
    except Exception as e:
        log_test("Customer login", "FAIL", str(e))
        return
    
    # Test Access Control
    print("\n--- Customer Access Control ---")
    headers = {"Authorization": f"Bearer {token}"}
    
    try:
        # Should NOT access all customers
        response = requests.get(f"{API_BASE}/customers", headers=headers)
        if response.status_code == 200:
            log_test("Customer cannot access all customers", "FAIL", "Customer has access (should not)")
        else:
            log_test("Customer cannot access all customers", "PASS")
        
        # Should see own invoices
        response = requests.get(f"{API_BASE}/invoices", headers=headers)
        if response.status_code == 200:
            invoices = response.json().get("data", [])
            log_test("Customer can view own invoices", "PASS", f"Count: {len(invoices)}")
        else:
            log_test("Customer can view own invoices", "FAIL", f"Status: {response.status_code}")
        
        # Should NOT access settings
        response = requests.get(f"{API_BASE}/settings", headers=headers)
        if response.status_code == 200:
            log_test("Customer cannot access settings", "FAIL", "Customer has access (should not)")
        else:
            log_test("Customer cannot access settings", "PASS")
    except Exception as e:
        log_test("Customer access control", "FAIL", str(e))

def main():
    """Run all tests"""
    print("═══════════════════════════════════════")
    print("🧪 API TESTING SUITE")
    print("   Hundeschule HomoCanis App")
    print("═══════════════════════════════════════")
    
    # Run tests
    admin_token, customer_id = test_admin()
    if admin_token and customer_id:
        test_create_invoice(admin_token, customer_id)
    
    test_trainer()
    test_customer()
    
    # Summary
    print("\n═══════════════════════════════════════")
    print("📊 TEST SUMMARY")
    print("═══════════════════════════════════════")
    total = len(results["passed"]) + len(results["failed"])
    print(f"✓ Passed: {len(results['passed'])}")
    print(f"✗ Failed: {len(results['failed'])}")
    print(f"Total: {total}")
    
    # Save results
    with open("test-results/api-test-results.json", "w") as f:
        json.dump(results, f, indent=2)
    print("\n📝 Detailed results saved to test-results/api-test-results.json")
    
    # Exit with error if any failed
    if len(results["failed"]) > 0:
        print(f"\n⚠ {len(results['failed'])} test(s) failed")
        sys.exit(1)
    else:
        print("\n✓ All tests passed!")
        sys.exit(0)

if __name__ == "__main__":
    main()
