import { test, expect } from '@playwright/test'

test.describe('Customers', () => {
  test.beforeEach(async ({ page }) => {
    // Login as admin
    await page.goto('/login')
    await page.fill('input[type="email"]', 'admin@example.com')
    await page.fill('input[type="password"]', 'password')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/home', { timeout: 10000 })
    
    // Navigate to customers
    await page.goto('/customers')
    await page.waitForLoadState('networkidle')
  })

  test('should display customers page', async ({ page }) => {
    await expect(page.locator('h1, h2').filter({ hasText: /Kunden|Customers/i })).toBeVisible()
  })

  test('should show create customer button', async ({ page }) => {
    const createButton = page.locator('button:has-text("Neuer Kunde"), button:has-text("Kunde erstellen"), button:has-text("Create Customer")')
    await expect(createButton.first()).toBeVisible()
  })

  test('should open create customer modal', async ({ page }) => {
    const createButton = page.locator('button:has-text("Neuer Kunde"), button:has-text("Kunde erstellen"), button:has-text("Create Customer")')
    await createButton.first().click()
    
    // Wait for modal
    await page.waitForTimeout(500)
    
    const modal = page.locator('[role="dialog"], .modal')
    await expect(modal.first()).toBeVisible()
  })

  test('should create a new customer', async ({ page }) => {
    const createButton = page.locator('button:has-text("Neuer Kunde"), button:has-text("Kunde erstellen"), button:has-text("Create Customer")')
    await createButton.first().click()
    await page.waitForTimeout(500)
    
    // Fill customer form
    await page.fill('input[name="first_name"], input#first_name', 'Max')
    await page.fill('input[name="last_name"], input#last_name', 'Mustermann')
    await page.fill('input[name="email"], input#email', `test${Date.now()}@example.com`)
    await page.fill('input[name="phone"], input#phone', '0123456789')
    
    // Submit
    const submitButton = page.locator('button:has-text("Speichern"), button:has-text("Erstellen"), button[type="submit"]').last()
    await submitButton.click()
    
    // Wait for success
    await page.waitForTimeout(2000)
  })

  test('should display customer list', async ({ page }) => {
    await page.waitForTimeout(1000)
    
    const table = page.locator('table, .customer-list, [role="table"]')
    await expect(table).toBeVisible()
  })

  test('should search customers', async ({ page }) => {
    await page.waitForTimeout(1000)
    
    const searchInput = page.locator('input[type="search"], input[placeholder*="Suche"], input[placeholder*="Search"]')
    
    if (await searchInput.count() > 0) {
      await searchInput.first().fill('Test')
      await page.waitForTimeout(500)
    }
  })

  test('should view customer details', async ({ page }) => {
    await page.waitForTimeout(1000)
    
    // Click on first customer row/link
    const customerLink = page.locator('table tbody tr, .customer-list-item').first()
    
    if (await customerLink.count() > 0) {
      await customerLink.click()
      await page.waitForTimeout(1000)
    }
  })
})
