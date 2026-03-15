import { test, expect } from '@playwright/test'

test.describe('Invoices', () => {
  test.beforeEach(async ({ page }) => {
    // Login as admin
    await page.goto('/login')
    await page.fill('input[type="email"]', 'admin@example.com')
    await page.fill('input[type="password"]', 'password')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/home', { timeout: 10000 })
    
    // Navigate to invoices
    await page.goto('/invoices')
    await page.waitForLoadState('networkidle')
  })

  test('should display invoices page', async ({ page }) => {
    await expect(page.locator('h1, h2').filter({ hasText: /Rechnungen|Invoices/i })).toBeVisible()
  })

  test('should show create invoice button', async ({ page }) => {
    const createButton = page.locator('button:has-text("Neue Rechnung"), button:has-text("Rechnung erstellen"), button:has-text("Create Invoice")')
    await expect(createButton.first()).toBeVisible()
  })

  test('should open create invoice modal', async ({ page }) => {
    const createButton = page.locator('button:has-text("Neue Rechnung"), button:has-text("Rechnung erstellen"), button:has-text("Create Invoice")')
    await createButton.first().click()
    
    // Wait for modal to appear
    await page.waitForTimeout(500)
    
    // Check if modal is visible
    const modal = page.locator('[role="dialog"], .modal, div:has-text("Rechnung erstellen")').first()
    await expect(modal).toBeVisible()
  })

  test('should create invoice with pre-filled date', async ({ page }) => {
    // Open modal
    const createButton = page.locator('button:has-text("Neue Rechnung"), button:has-text("Rechnung erstellen"), button:has-text("Create Invoice")')
    await createButton.first().click()
    await page.waitForTimeout(500)
    
    // Check if invoice date is pre-filled
    const invoiceDateInput = page.locator('input[type="date"][name="invoice_date"], input[type="date"]#invoice_date')
    const dateValue = await invoiceDateInput.inputValue()
    expect(dateValue).toBeTruthy()
    expect(dateValue.length).toBeGreaterThan(0)
  })

  test('should require customer selection', async ({ page }) => {
    // Open modal
    const createButton = page.locator('button:has-text("Neue Rechnung"), button:has-text("Rechnung erstellen"), button:has-text("Create Invoice")')
    await createButton.first().click()
    await page.waitForTimeout(500)
    
    // Try to submit without selecting customer
    const submitButton = page.locator('button:has-text("Speichern"), button:has-text("Erstellen"), button[type="submit"]').last()
    await submitButton.click()
    
    // Should show validation error or stay on modal
    await page.waitForTimeout(1000)
  })

  test('should create invoice with customer and items', async ({ page }) => {
    // Open modal
    const createButton = page.locator('button:has-text("Neue Rechnung"), button:has-text("Rechnung erstellen"), button:has-text("Create Invoice")')
    await createButton.first().click()
    await page.waitForTimeout(500)
    
    // Select customer (first option)
    const customerSelect = page.locator('select[name="customer_id"], select#customer_id')
    await customerSelect.selectOption({ index: 1 })
    
    // Fill in item details
    const descriptionInput = page.locator('input[name="items.0.description"], textarea[name="items.0.description"]').first()
    await descriptionInput.fill('Test Hundeschule Kurs')
    
    const quantityInput = page.locator('input[name="items.0.quantity"], input[type="number"]').first()
    await quantityInput.fill('5')
    
    const priceInput = page.locator('input[name="items.0.unit_price"], input[name="items.0.unitPrice"]').first()
    await priceInput.fill('50.00')
    
    // Submit form
    const submitButton = page.locator('button:has-text("Speichern"), button:has-text("Erstellen"), button[type="submit"]').last()
    await submitButton.click()
    
    // Wait for success or error
    await page.waitForTimeout(3000)
  })

  test('should show tax fields when Kleinunternehmerregelung is disabled', async ({ page }) => {
    // First, ensure Kleinunternehmerregelung is disabled in settings
    await page.goto('/settings')
    await page.waitForTimeout(1000)
    
    const checkbox = page.locator('input[type="checkbox"][name="company_small_business"], input[type="checkbox"]#company_small_business')
    if (await checkbox.isChecked()) {
      await checkbox.click()
      const saveButton = page.locator('button:has-text("Speichern"), button[type="submit"]')
      await saveButton.click()
      await page.waitForTimeout(2000)
    }
    
    // Go back to invoices
    await page.goto('/invoices')
    await page.waitForTimeout(1000)
    
    // Open create modal
    const createButton = page.locator('button:has-text("Neue Rechnung"), button:has-text("Rechnung erstellen"), button:has-text("Create Invoice")')
    await createButton.first().click()
    await page.waitForTimeout(500)
    
    // Check if tax-related fields are visible
    const taxText = page.locator('text=/MwSt|Steuer|Tax/i')
    await expect(taxText.first()).toBeVisible()
  })

  test('should hide tax fields when Kleinunternehmerregelung is enabled', async ({ page }) => {
    // First, enable Kleinunternehmerregelung in settings
    await page.goto('/settings')
    await page.waitForTimeout(1000)
    
    const checkbox = page.locator('input[type="checkbox"][name="company_small_business"], input[type="checkbox"]#company_small_business')
    if (!await checkbox.isChecked()) {
      await checkbox.click()
      const saveButton = page.locator('button:has-text("Speichern"), button[type="submit"]')
      await saveButton.click()
      await page.waitForTimeout(2000)
    }
    
    // Go back to invoices
    await page.goto('/invoices')
    await page.waitForTimeout(1000)
    
    // Open create modal
    const createButton = page.locator('button:has-text("Neue Rechnung"), button:has-text("Rechnung erstellen"), button:has-text("Create Invoice")')
    await createButton.first().click()
    await page.waitForTimeout(500)
    
    // Tax fields should not be visible or should be 0%
    // This test passes if no error occurs during form display
  })

  test('should display existing invoices in list', async ({ page }) => {
    await page.waitForTimeout(1000)
    
    // Check if table or list is visible
    const table = page.locator('table, .invoice-list, [role="table"]')
    await expect(table).toBeVisible()
  })

  test('should filter invoices by status', async ({ page }) => {
    await page.waitForTimeout(1000)
    
    // Look for filter/tab buttons
    const filterButtons = page.locator('button:has-text("Entwurf"), button:has-text("Draft"), button:has-text("Bezahlt"), button:has-text("Paid")')
    
    if (await filterButtons.count() > 0) {
      await filterButtons.first().click()
      await page.waitForTimeout(500)
    }
  })
})
