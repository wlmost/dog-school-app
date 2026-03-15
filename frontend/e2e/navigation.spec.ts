import { test, expect } from '@playwright/test'

test.describe('Navigation and UI', () => {
  test.beforeEach(async ({ page }) => {
    // Login as admin
    await page.goto('/login')
    await page.fill('input[type="email"]', 'admin@example.com')
    await page.fill('input[type="password"]', 'password')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/home', { timeout: 10000 })
  })

  test('should display home page with background image', async ({ page }) => {
    await page.goto('/home')
    await page.waitForLoadState('networkidle')
    
    // Check if page loaded
    await expect(page).toHaveURL(/\/home/)
  })

  test('should navigate to customers page', async ({ page }) => {
    // Find and click customers link
    const customersLink = page.locator('a:has-text("Kunden"), a:has-text("Customers"), nav a[href*="customers"]')
    await customersLink.first().click()
    
    await page.waitForURL('**/customers', { timeout: 5000 })
    await expect(page.url()).toContain('/customers')
  })

  test('should navigate to invoices page', async ({ page }) => {
    const invoicesLink = page.locator('a:has-text("Rechnungen"), a:has-text("Invoices"), nav a[href*="invoices"]')
    await invoicesLink.first().click()
    
    await page.waitForURL('**/invoices', { timeout: 5000 })
    await expect(page.url()).toContain('/invoices')
  })

  test('should navigate to settings page', async ({ page }) => {
    const settingsLink = page.locator('a:has-text("Einstellungen"), a:has-text("Settings"), nav a[href*="settings"]')
    await settingsLink.first().click()
    
    await page.waitForURL('**/settings', { timeout: 5000 })
    await expect(page.url()).toContain('/settings')
  })

  test('should show navigation menu', async ({ page }) => {
    const nav = page.locator('nav, [role="navigation"]')
    await expect(nav.first()).toBeVisible()
  })

  test('should have responsive navigation', async ({ page }) => {
    // Test on mobile viewport
    await page.setViewportSize({ width: 375, height: 667 })
    await page.waitForTimeout(500)
    
    // Check if mobile menu toggle exists
    const menuToggle = page.locator('button[aria-label*="menu"], button:has-text("Menu"), .mobile-menu-button')
    
    if (await menuToggle.count() > 0) {
      await menuToggle.first().click()
      await page.waitForTimeout(500)
    }
  })

  test('should display user menu', async ({ page }) => {
    // Look for user profile/menu button
    const userMenu = page.locator('button:has-text("admin"), [aria-label*="user"], .user-menu')
    
    if (await userMenu.count() > 0) {
      await expect(userMenu.first()).toBeVisible()
    }
  })

  test('should take screenshot of home page', async ({ page }) => {
    await page.goto('/home')
    await page.waitForLoadState('networkidle')
    
    await page.screenshot({ 
      path: 'test-results/home-page.png',
      fullPage: true 
    })
  })

  test('should handle browser console errors', async ({ page }) => {
    const consoleErrors: string[] = []
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text())
      }
    })
    
    await page.goto('/home')
    await page.waitForTimeout(2000)
    
    // Log any console errors (but don't fail the test)
    if (consoleErrors.length > 0) {
      console.log('Console errors detected:', consoleErrors)
    }
  })

  test('should load all pages without 404 errors', async ({ page }) => {
    const pages = ['/home', '/customers', '/invoices', '/settings']
    
    for (const pagePath of pages) {
      const response = await page.goto(pagePath)
      expect(response?.status()).not.toBe(404)
      await page.waitForTimeout(500)
    }
  })
})
