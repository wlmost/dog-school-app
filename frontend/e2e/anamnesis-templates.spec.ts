import { test, expect } from '@playwright/test'

// Echter Browser-Roundtrip für den in
// openspec/changes/fix-anamnesis-template-questions-not-saved/ behobenen Bug:
// Fragen einer Anamnesebogen-Vorlage gingen beim Anlegen/Bearbeiten scheinbar
// verloren (siehe proposal.md). Dieser Test durchläuft die volle Kette
// Vue-Formular -> Axios -> echtes Backend -> DB -> erneutes Laden, die der
// T05-Entwickler-Agent laut task-T05.notes.md mangels Docker-Zugriff nur per
// Code-Trace verifizieren konnte.
test.describe('Anamnesis template questions', () => {
  test.beforeEach(async ({ page }) => {
    // Login als Trainer (nicht Admin: AnamnesisTemplatePolicy verbietet Admins
    // den Zugriff auf Vorlagen, siehe AnamnesisTemplateApiTest.php
    // "admin cannot list/view/update/delete/create ...").
    await page.goto('/login')
    await page.fill('input[type="email"]', 'trainer@example.com')
    await page.fill('input[type="password"]', 'password')
    await page.click('button[type="submit"]')
    // Ziel-Route nach Login ist die Dashboard-Route unter dem `/app`-Parent
    // (siehe frontend/src/router/index.ts, Route "Dashboard" mit path: '' unter
    // parent path: '/app', sowie router.push({ name: 'Dashboard' }) in
    // frontend/src/views/auth/LoginView.vue). Die anderen, vorbestehenden
    // e2e-Spec-Dateien (customers.spec.ts, invoices.spec.ts, navigation.spec.ts)
    // warten noch auf die veraltete Route '**/home' und schlagen deswegen
    // aktuell alle fehl — ein vorbestehender, unabhängiger Befund, siehe
    // test-report.md.
    await page.waitForURL('**/app', { timeout: 10000 })

    await page.goto('/app/anamnesis')
    await page.waitForLoadState('networkidle')
  })

  test('trainer can create a template with questions and see edits persist after saving', async ({ page }) => {
    // Browser-Dialog für die Löschbestätigung am Ende automatisch bestätigen.
    page.on('dialog', (dialog) => dialog.accept())

    const templateName = `E2E Vorlage ${Date.now()}`
    const questionOneOriginal = 'Wie alt ist dein Hund?'
    const questionTwoRemoved = 'Wird wieder entfernt'
    const questionOneModified = 'Wie alt ist dein Hund in Jahren?'
    const questionThreeAdded = 'Hat dein Hund Allergien?'

    // --- Anlegen: neue Vorlage mit zwei Fragen ---
    await page.locator('button:has-text("Neue Vorlage")').click()
    await page.waitForTimeout(500)

    await page.locator('input[placeholder="z.B. Welpen-Anamnese"]').fill(templateName)

    await page.locator('button:has-text("Frage hinzufügen")').click()
    await page.locator('button:has-text("Frage hinzufügen")').click()

    const questionTextInputs = page.locator('input[placeholder="Frage eingeben..."]')
    await expect(questionTextInputs).toHaveCount(2)
    await questionTextInputs.nth(0).fill(questionOneOriginal)
    await questionTextInputs.nth(1).fill(questionTwoRemoved)

    await page.locator('button:has-text("Speichern")').click()
    await page.waitForTimeout(1000)
    await page.waitForLoadState('networkidle')

    // --- Anzeigeproblem (T02/T04): Kachel zeigt die tatsächliche Anzahl ---
    const card = page.locator('div.border-gray-200.rounded-lg').filter({ hasText: templateName })
    await expect(card).toBeVisible()
    await expect(card).toContainText('2 Fragen')

    // --- Bearbeiten öffnen: T04 muss die Vorlage inkl. Fragen nachladen ---
    await card.locator('button[title="Bearbeiten"]').click()
    await page.waitForTimeout(500)

    await expect(page.locator('h4:has-text("Fragen (2)")')).toBeVisible()
    const loadedQuestionInputs = page.locator('input[placeholder="Frage eingeben..."]')
    await expect(loadedQuestionInputs).toHaveCount(2)
    await expect(loadedQuestionInputs.nth(0)).toHaveValue(questionOneOriginal)
    await expect(loadedQuestionInputs.nth(1)).toHaveValue(questionTwoRemoved)

    // --- Bearbeiten: Frage 1 ändern (Update per id), Frage 2 entfernen
    // (Löschung), neue Frage 3 hinzufügen (Create ohne id) — genau die
    // Mischung, die T05 durchs Formular in den Payload bringen muss. ---
    await loadedQuestionInputs.nth(0).fill(questionOneModified)

    const removeButtons = page.locator('button[title="Frage entfernen"]')
    await removeButtons.nth(1).click()

    await page.locator('button:has-text("Frage hinzufügen")').click()
    const inputsAfterEdit = page.locator('input[placeholder="Frage eingeben..."]')
    await expect(inputsAfterEdit).toHaveCount(2)
    await inputsAfterEdit.nth(1).fill(questionThreeAdded)

    await page.locator('button:has-text("Speichern")').click()
    await page.waitForTimeout(1000)
    await page.waitForLoadState('networkidle')

    // --- Verifikation: Kachel zeigt weiterhin die korrekte Anzahl ---
    await expect(card).toContainText('2 Fragen')

    // --- Erneut öffnen: bestätigt, dass Update/Create/Delete tatsächlich in
    // der DB angekommen sind (nicht nur im lokalen Formular-State) ---
    await card.locator('button[title="Bearbeiten"]').click()
    await page.waitForTimeout(500)

    await expect(page.locator('h4:has-text("Fragen (2)")')).toBeVisible()
    const persistedQuestionInputs = page.locator('input[placeholder="Frage eingeben..."]')
    await expect(persistedQuestionInputs).toHaveCount(2)

    const persistedTexts = await persistedQuestionInputs.evaluateAll((inputs) =>
      inputs.map((input) => (input as HTMLInputElement).value)
    )
    expect(persistedTexts).toContain(questionOneModified)
    expect(persistedTexts).toContain(questionThreeAdded)
    expect(persistedTexts).not.toContain(questionOneOriginal)
    expect(persistedTexts).not.toContain(questionTwoRemoved)

    await page.locator('button:has-text("Abbrechen")').click()

    // --- Aufräumen: Test-Vorlage wieder löschen ---
    await card.locator('button[title="Löschen"]').click()
    await page.waitForTimeout(1000)
    await expect(page.locator('div.border-gray-200.rounded-lg').filter({ hasText: templateName })).toHaveCount(0)
  })
})
