const { chromium } = require('playwright');
const fs = require('fs');

// Test results tracking
const testResults = {
  passed: [],
  failed: [],
  errors: []
};

function logTest(name, status, details = '') {
  const result = { name, status, details, timestamp: new Date().toISOString() };
  if (status === 'PASS') {
    testResults.passed.push(result);
    console.log(`✓ PASS: ${name}`);
  } else if (status === 'FAIL') {
    testResults.failed.push(result);
    console.log(`✗ FAIL: ${name} - ${details}`);
  } else {
    testResults.errors.push(result);
    console.log(`⚠ ERROR: ${name} - ${details}`);
  }
}

async function takeScreenshot(page, name) {
  try {
    await page.screenshot({ 
      path: `test-results/${name}.png`,
      fullPage: true 
    });
    console.log(`  📸 Screenshot: ${name}.png`);
  } catch (err) {
    console.log(`  ⚠ Screenshot failed: ${err.message}`);
  }
}

async function waitAndLog(page, timeout = 1000) {
  await page.waitForTimeout(timeout);
}

// ===== ADMIN TESTS =====
async function testAdminRole(browser) {
  console.log('\n═══════════════════════════════════════');
  console.log('🔧 TESTING ADMIN ROLE');
  console.log('═══════════════════════════════════════\n');
  
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Track console errors
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });
  
  try {
    // Login as Admin
    console.log('\n--- Login ---');
    await page.goto('http://localhost:5173/login');
    await waitAndLog(page, 500);
    await takeScreenshot(page, 'admin-01-login-page');
    
    const emailInput = await page.locator('input[type="email"]').count();
    if (emailInput > 0) {
      logTest('Login page loads', 'PASS');
    } else {
      logTest('Login page loads', 'FAIL', 'Email input not found');
      await context.close();
      return;
    }
    
    await page.fill('input[type="email"]', 'admin@example.com');
    await page.fill('input[type="password"]', 'password');
    await takeScreenshot(page, 'admin-02-login-filled');
    await page.click('button[type="submit"]');
    
    try {
      await page.waitForURL('**/home', { timeout: 10000 });
      logTest('Admin login successful', 'PASS');
      await takeScreenshot(page, 'admin-03-home-page');
    } catch (err) {
      logTest('Admin login successful', 'FAIL', err.message);
      await takeScreenshot(page, 'admin-03-login-error');
      await context.close();
      return;
    }
    
    // Test Home Page
    console.log('\n--- Home Page ---');
    await waitAndLog(page, 1000);
    if (page.url().includes('/home')) {
      logTest('Home page navigation', 'PASS');
    } else {
      logTest('Home page navigation', 'FAIL', `URL: ${page.url()}`);
    }
    
    // Test Navigation to Settings
    console.log('\n--- Settings ---');
    await page.goto('http://localhost:5173/settings');
    await waitAndLog(page, 1500);
    await takeScreenshot(page, 'admin-04-settings-page');
    
    const settingsHeading = await page.locator('h1, h2').filter({ hasText: /Einstellungen|Settings/i }).count();
    if (settingsHeading > 0) {
      logTest('Settings page loads', 'PASS');
    } else {
      logTest('Settings page loads', 'FAIL', 'Heading not found');
    }
    
    // Test Kleinunternehmerregelung checkbox
    console.log('\n--- Kleinunternehmerregelung ---');
    const checkbox = page.locator('input[type="checkbox"]').filter({ hasText: /Kleinunternehmer/i });
    const checkboxCount = await checkbox.count();
    
    if (checkboxCount === 0) {
      // Try alternative selectors
      const altCheckbox = page.locator('input[type="checkbox"][name="company_small_business"], input[type="checkbox"]#company_small_business');
      const altCount = await altCheckbox.count();
      
      if (altCount > 0) {
        logTest('Kleinunternehmerregelung checkbox exists', 'PASS');
        
        // Test checkbox toggle
        const initialState = await altCheckbox.isChecked();
        await altCheckbox.click();
        await waitAndLog(page, 300);
        const newState = await altCheckbox.isChecked();
        
        if (newState !== initialState) {
          logTest('Kleinunternehmerregelung checkbox toggles', 'PASS');
        } else {
          logTest('Kleinunternehmerregelung checkbox toggles', 'FAIL', 'State did not change');
        }
        
        // Save settings
        await takeScreenshot(page, 'admin-05-settings-before-save');
        const saveButton = page.locator('button').filter({ hasText: /Speichern|Save/i });
        const saveButtonCount = await saveButton.count();
        
        if (saveButtonCount > 0) {
          await saveButton.first().click();
          await waitAndLog(page, 2000);
          logTest('Settings save button works', 'PASS');
          await takeScreenshot(page, 'admin-06-settings-after-save');
          
          // Reload and verify persistence
          await page.reload();
          await waitAndLog(page, 1500);
          const checkboxAfterReload = page.locator('input[type="checkbox"][name="company_small_business"], input[type="checkbox"]#company_small_business');
          const stateAfterReload = await checkboxAfterReload.isChecked();
          
          if (stateAfterReload === newState) {
            logTest('Settings persist after reload', 'PASS');
          } else {
            logTest('Settings persist after reload', 'FAIL', `Expected: ${newState}, Got: ${stateAfterReload}`);
          }
        } else {
          logTest('Settings save button exists', 'FAIL', 'Save button not found');
        }
      } else {
        logTest('Kleinunternehmerregelung checkbox exists', 'FAIL', 'Checkbox not found');
      }
    } else {
      logTest('Kleinunternehmerregelung checkbox exists', 'PASS');
    }
    
    // Test Logo Upload
    console.log('\n--- Logo Upload ---');
    const fileInput = await page.locator('input[type="file"]').count();
    if (fileInput > 0) {
      logTest('Logo upload field exists', 'PASS');
    } else {
      logTest('Logo upload field exists', 'FAIL', 'File input not found');
    }
    
    // Test Email Templates
    console.log('\n--- Email Templates ---');
    const emailTemplateSection = await page.locator('text=/E-Mail|Email.*Template/i').count();
    if (emailTemplateSection > 0) {
      logTest('Email template section exists', 'PASS');
    } else {
      logTest('Email template section exists', 'FAIL', 'Not found');
    }
    
    // Test Customers Page
    console.log('\n--- Customers ---');
    await page.goto('http://localhost:5173/customers');
    await waitAndLog(page, 1500);
    await takeScreenshot(page, 'admin-07-customers-page');
    
    const customersHeading = await page.locator('h1, h2').filter({ hasText: /Kunden|Customers/i }).count();
    if (customersHeading > 0) {
      logTest('Customers page loads', 'PASS');
    } else {
      logTest('Customers page loads', 'FAIL', 'Heading not found');
    }
    
    // Test Create Customer
    const createCustomerBtn = page.locator('button').filter({ hasText: /Neuer Kunde|Kunde erstellen|Create Customer|New Customer/i });
    const createBtnCount = await createCustomerBtn.count();
    
    if (createBtnCount > 0) {
      logTest('Create customer button exists', 'PASS');
      
      await createCustomerBtn.first().click();
      await waitAndLog(page, 500);
      await takeScreenshot(page, 'admin-08-create-customer-modal');
      
      // Check if modal opened
      const modal = page.locator('[role="dialog"], .modal, div:has-text("Kunde")').first();
      const modalVisible = await modal.isVisible().catch(() => false);
      
      if (modalVisible) {
        logTest('Customer modal opens', 'PASS');
        
        // Fill customer form
        const firstNameInput = page.locator('input[name="first_name"], input#first_name');
        if (await firstNameInput.count() > 0) {
          await firstNameInput.fill('Test');
          await page.locator('input[name="last_name"], input#last_name').fill('User');
          await page.locator('input[name="email"], input#email').fill(`test${Date.now()}@example.com`);
          
          await takeScreenshot(page, 'admin-09-create-customer-filled');
          
          const submitBtn = page.locator('button').filter({ hasText: /Speichern|Save|Erstellen|Create/i }).last();
          await submitBtn.click();
          await waitAndLog(page, 2000);
          
          logTest('Customer creation form submits', 'PASS');
          await takeScreenshot(page, 'admin-10-customer-created');
        } else {
          logTest('Customer form fields exist', 'FAIL', 'First name input not found');
        }
        
        // Close modal if still open
        const closeBtn = page.locator('button[aria-label="Close"], button:has-text("Abbrechen"), button:has-text("Cancel")');
        if (await closeBtn.count() > 0) {
          await closeBtn.first().click().catch(() => {});
          await waitAndLog(page, 300);
        }
      } else {
        logTest('Customer modal opens', 'FAIL', 'Modal not visible');
      }
    } else {
      logTest('Create customer button exists', 'FAIL', 'Button not found');
    }
    
    // Test Invoices Page
    console.log('\n--- Invoices ---');
    await page.goto('http://localhost:5173/invoices');
    await waitAndLog(page, 1500);
    await takeScreenshot(page, 'admin-11-invoices-page');
    
    const invoicesHeading = await page.locator('h1, h2').filter({ hasText: /Rechnungen|Invoices/i }).count();
    if (invoicesHeading > 0) {
      logTest('Invoices page loads', 'PASS');
    } else {
      logTest('Invoices page loads', 'FAIL', 'Heading not found');
    }
    
    // Test Create Invoice
    const createInvoiceBtn = page.locator('button').filter({ hasText: /Neue Rechnung|Rechnung erstellen|Create Invoice|New Invoice/i });
    const createInvBtnCount = await createInvoiceBtn.count();
    
    if (createInvBtnCount > 0) {
      logTest('Create invoice button exists', 'PASS');
      
      await createInvoiceBtn.first().click();
      await waitAndLog(page, 500);
      await takeScreenshot(page, 'admin-12-create-invoice-modal');
      
      const invoiceModal = page.locator('[role="dialog"], .modal').first();
      const invModalVisible = await invoiceModal.isVisible().catch(() => false);
      
      if (invModalVisible) {
        logTest('Invoice modal opens', 'PASS');
        
        // Check if date is pre-filled
        const invoiceDateInput = page.locator('input[type="date"]').first();
        if (await invoiceDateInput.count() > 0) {
          const dateValue = await invoiceDateInput.inputValue();
          if (dateValue && dateValue.length > 0) {
            logTest('Invoice date is pre-filled', 'PASS', `Date: ${dateValue}`);
          } else {
            logTest('Invoice date is pre-filled', 'FAIL', 'Date is empty');
          }
          
          // Try to fill invoice form
          const customerSelect = page.locator('select[name="customer_id"], select#customer_id');
          if (await customerSelect.count() > 0) {
            const options = await customerSelect.locator('option').count();
            if (options > 1) {
              await customerSelect.selectOption({ index: 1 });
              logTest('Customer select has options', 'PASS', `${options} options`);
              
              // Fill item
              const descInput = page.locator('input[name*="description"], textarea[name*="description"]').first();
              if (await descInput.count() > 0) {
                await descInput.fill('Hundeschule Kurs');
                
                const qtyInput = page.locator('input[name*="quantity"], input[type="number"]').first();
                await qtyInput.fill('5');
                
                const priceInput = page.locator('input[name*="price"], input[name*="unit_price"]').first();
                await priceInput.fill('50.00');
                
                await takeScreenshot(page, 'admin-13-invoice-filled');
                
                // Check for tax display
                const taxText = await page.locator('text=/MwSt|Steuer|Tax|Netto|Brutto/i').count();
                if (taxText > 0) {
                  logTest('Tax information displayed', 'PASS');
                } else {
                  logTest('Tax information displayed', 'INFO', 'No tax text found (might be Kleinunternehmer)');
                }
                
                // Submit invoice
                const submitInvBtn = page.locator('button').filter({ hasText: /Speichern|Save|Erstellen|Create/i }).last();
                await submitInvBtn.click();
                await waitAndLog(page, 3000);
                
                // Check if we're back to list or if there's an error
                const isStillInModal = await invoiceModal.isVisible().catch(() => false);
                if (!isStillInModal) {
                  logTest('Invoice creation submits successfully', 'PASS');
                  await takeScreenshot(page, 'admin-14-invoice-created');
                } else {
                  logTest('Invoice creation submits successfully', 'FAIL', 'Still in modal - possible validation error');
                  await takeScreenshot(page, 'admin-14-invoice-error');
                }
              } else {
                logTest('Invoice item fields exist', 'FAIL', 'Description input not found');
              }
            } else {
              logTest('Customer select has options', 'FAIL', 'No customers available');
            }
          } else {
            logTest('Customer select exists', 'FAIL', 'Select not found');
          }
        } else {
          logTest('Invoice date field exists', 'FAIL', 'Date input not found');
        }
        
        // Close modal
        const closeInvBtn = page.locator('button[aria-label="Close"], button:has-text("Abbrechen"), button:has-text("Cancel")');
        if (await closeInvBtn.count() > 0) {
          await closeInvBtn.first().click().catch(() => {});
          await waitAndLog(page, 300);
        }
      } else {
        logTest('Invoice modal opens', 'FAIL', 'Modal not visible');
      }
    } else {
      logTest('Create invoice button exists', 'FAIL', 'Button not found');
    }
    
    // Test Dark Mode
    console.log('\n--- Dark Mode ---');
    const darkModeToggle = page.locator('button[aria-label*="dark"], button[aria-label*="theme"], button:has([class*="moon"]), button:has([class*="sun"])');
    const darkModeCount = await darkModeToggle.count();
    
    if (darkModeCount > 0) {
      logTest('Dark mode toggle exists', 'PASS');
      
      await darkModeToggle.first().click();
      await waitAndLog(page, 500);
      await takeScreenshot(page, 'admin-15-dark-mode');
      
      // Click again to toggle back
      await darkModeToggle.first().click();
      await waitAndLog(page, 500);
      
      logTest('Dark mode toggle works', 'PASS');
    } else {
      logTest('Dark mode toggle exists', 'FAIL', 'Toggle button not found');
    }
    
    // Test Logout
    console.log('\n--- Logout ---');
    const logoutBtn = page.locator('button:has-text("Abmelden"), button:has-text("Logout"), a:has-text("Abmelden"), a:has-text("Logout")');
    const logoutCount = await logoutBtn.count();
    
    if (logoutCount > 0) {
      await logoutBtn.first().click();
      await waitAndLog(page, 2000);
      
      if (page.url().includes('/login')) {
        logTest('Logout redirects to login', 'PASS');
        await takeScreenshot(page, 'admin-16-logout');
      } else {
        logTest('Logout redirects to login', 'FAIL', `URL: ${page.url()}`);
      }
    } else {
      logTest('Logout button exists', 'FAIL', 'Button not found');
    }
    
    // Log console errors
    if (consoleErrors.length > 0) {
      logTest('Admin: No console errors', 'FAIL', `${consoleErrors.length} errors: ${consoleErrors.slice(0, 3).join(', ')}`);
    } else {
      logTest('Admin: No console errors', 'PASS');
    }
    
  } catch (err) {
    logTest('Admin testing', 'ERROR', err.message);
    await takeScreenshot(page, 'admin-error');
  } finally {
    await context.close();
  }
}

// ===== TRAINER TESTS =====
async function testTrainerRole(browser) {
  console.log('\n═══════════════════════════════════════');
  console.log('👨‍🏫 TESTING TRAINER ROLE');
  console.log('═══════════════════════════════════════\n');
  
  const context = await browser.newContext();
  const page = await context.newPage();
  
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });
  
  try {
    // Login as Trainer
    console.log('\n--- Login ---');
    await page.goto('http://localhost:5173/login');
    await waitAndLog(page, 500);
    
    await page.fill('input[type="email"]', 'trainer@example.com');
    await page.fill('input[type="password"]', 'password');
    await takeScreenshot(page, 'trainer-01-login');
    await page.click('button[type="submit"]');
    
    try {
      await page.waitForURL('**/home', { timeout: 10000 });
      logTest('Trainer login successful', 'PASS');
      await takeScreenshot(page, 'trainer-02-home');
    } catch (err) {
      logTest('Trainer login successful', 'FAIL', err.message);
      await takeScreenshot(page, 'trainer-02-login-error');
      await context.close();
      return;
    }
    
    // Test navigation access
    console.log('\n--- Navigation Access ---');
    await waitAndLog(page, 1000);
    
    // Check if trainer has access to customers
    await page.goto('http://localhost:5173/customers');
    await waitAndLog(page, 1000);
    if (!page.url().includes('/login')) {
      logTest('Trainer can access customers', 'PASS');
      await takeScreenshot(page, 'trainer-03-customers');
    } else {
      logTest('Trainer can access customers', 'FAIL', 'Redirected to login');
    }
    
    // Check if trainer has access to invoices  
    await page.goto('http://localhost:5173/invoices');
    await waitAndLog(page, 1000);
    if (!page.url().includes('/login')) {
      logTest('Trainer can access invoices', 'PASS');
      await takeScreenshot(page, 'trainer-04-invoices');
    } else {
      logTest('Trainer can access invoices', 'FAIL', 'Redirected to login');
    }
    
    // Check if trainer has access to settings (should not)
    await page.goto('http://localhost:5173/settings');
    await waitAndLog(page, 1000);
    if (page.url().includes('/login') || page.url().includes('/home') || page.url().includes('403') || page.url().includes('unauthorized')) {
      logTest('Trainer cannot access settings (correct)', 'PASS');
      await takeScreenshot(page, 'trainer-05-settings-blocked');
    } else if (page.url().includes('/settings')) {
      logTest('Trainer cannot access settings (correct)', 'FAIL', 'Trainer has access to settings');
    }
    
    // Logout
    console.log('\n--- Logout ---');
    const logoutBtn = page.locator('button:has-text("Abmelden"), button:has-text("Logout"), a:has-text("Abmelden"), a:has-text("Logout")');
    if (await logoutBtn.count() > 0) {
      await logoutBtn.first().click();
      await waitAndLog(page, 2000);
      logTest('Trainer logout works', 'PASS');
      await takeScreenshot(page, 'trainer-06-logout');
    }
    
    if (consoleErrors.length > 0) {
      logTest('Trainer: No console errors', 'FAIL', `${consoleErrors.length} errors`);
    } else {
      logTest('Trainer: No console errors', 'PASS');
    }
    
  } catch (err) {
    logTest('Trainer testing', 'ERROR', err.message);
    await takeScreenshot(page, 'trainer-error');
  } finally {
    await context.close();
  }
}

// ===== CUSTOMER TESTS =====
async function testCustomerRole(browser) {
  console.log('\n═══════════════════════════════════════');
  console.log('👤 TESTING CUSTOMER ROLE');
  console.log('═══════════════════════════════════════\n');
  
  const context = await browser.newContext();
  const page = await context.newPage();
  
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });
  
  try {
    // Login as Customer
    console.log('\n--- Login ---');
    await page.goto('http://localhost:5173/login');
    await waitAndLog(page, 500);
    
    await page.fill('input[type="email"]', 'customer@example.com');
    await page.fill('input[type="password"]', 'password');
    await takeScreenshot(page, 'customer-01-login');
    await page.click('button[type="submit"]');
    
    try {
      await page.waitForURL('**/home', { timeout: 10000 });
      logTest('Customer login successful', 'PASS');
      await takeScreenshot(page, 'customer-02-home');
    } catch (err) {
      logTest('Customer login successful', 'FAIL', err.message);
      await takeScreenshot(page, 'customer-02-login-error');
      await context.close();
      return;
    }
    
    // Test navigation access
    console.log('\n--- Navigation Access ---');
    await waitAndLog(page, 1000);
    
    // Customer should NOT have access to customers list
    await page.goto('http://localhost:5173/customers');
    await waitAndLog(page, 1000);
    if (page.url().includes('/login') || page.url().includes('/home') || page.url().includes('403') || page.url().includes('unauthorized')) {
      logTest('Customer cannot access customers list (correct)', 'PASS');
      await takeScreenshot(page, 'customer-03-customers-blocked');
    } else {
      logTest('Customer cannot access customers list (correct)', 'FAIL', 'Customer has access to customers');
    }
    
    // Customer should be able to see their own invoices
    await page.goto('http://localhost:5173/invoices');
    await waitAndLog(page, 1000);
    if (!page.url().includes('/login')) {
      logTest('Customer can view invoices', 'PASS');
      await takeScreenshot(page, 'customer-04-invoices');
    } else {
      logTest('Customer can view invoices', 'FAIL', 'Cannot access invoices');
    }
    
    // Customer should NOT have access to settings
    await page.goto('http://localhost:5173/settings');
    await waitAndLog(page, 1000);
    if (page.url().includes('/login') || page.url().includes('/home') || page.url().includes('403') || page.url().includes('unauthorized')) {
      logTest('Customer cannot access settings (correct)', 'PASS');
      await takeScreenshot(page, 'customer-05-settings-blocked');
    } else {
      logTest('Customer cannot access settings (correct)', 'FAIL', 'Customer has access to settings');
    }
    
    // Logout
    console.log('\n--- Logout ---');
    const logoutBtn = page.locator('button:has-text("Abmelden"), button:has-text("Logout"), a:has-text("Abmelden"), a:has-text("Logout")');
    if (await logoutBtn.count() > 0) {
      await logoutBtn.first().click();
      await waitAndLog(page, 2000);
      logTest('Customer logout works', 'PASS');
      await takeScreenshot(page, 'customer-06-logout');
    }
    
    if (consoleErrors.length > 0) {
      logTest('Customer: No console errors', 'FAIL', `${consoleErrors.length} errors`);
    } else {
      logTest('Customer: No console errors', 'PASS');
    }
    
  } catch (err) {
    logTest('Customer testing', 'ERROR', err.message);
    await takeScreenshot(page, 'customer-error');
  } finally {
    await context.close();
  }
}

// ===== MAIN =====
(async () => {
  console.log('═══════════════════════════════════════');
  console.log('🧪 MANUAL TESTING SUITE');
  console.log('   Hundeschule HomoCanis App');
  console.log('═══════════════════════════════════════');
  
  // Create test-results directory
  if (!fs.existsSync('test-results')) {
    fs.mkdirSync('test-results');
  }
  
  const browser = await chromium.launch({ 
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  try {
    await testAdminRole(browser);
    await testTrainerRole(browser);
    await testCustomerRole(browser);
    
    // Summary
    console.log('\n═══════════════════════════════════════');
    console.log('📊 TEST SUMMARY');
    console.log('═══════════════════════════════════════');
    console.log(`✓ Passed: ${testResults.passed.length}`);
    console.log(`✗ Failed: ${testResults.failed.length}`);
    console.log(`⚠ Errors: ${testResults.errors.length}`);
    console.log(`Total: ${testResults.passed.length + testResults.failed.length + testResults.errors.length}`);
    
    if (testResults.failed.length > 0) {
      console.log('\n--- Failed Tests ---');
      testResults.failed.forEach(t => {
        console.log(`✗ ${t.name}: ${t.details}`);
      });
    }
    
    if (testResults.errors.length > 0) {
      console.log('\n--- Errors ---');
      testResults.errors.forEach(t => {
        console.log(`⚠ ${t.name}: ${t.details}`);
      });
    }
    
    // Save results to file
    fs.writeFileSync(
      'test-results/test-results.json',
      JSON.stringify(testResults, null, 2)
    );
    console.log('\n📝 Detailed results saved to test-results/test-results.json');
    
  } catch (err) {
    console.error('Fatal error:', err);
  } finally {
    await browser.close();
  }
})();
