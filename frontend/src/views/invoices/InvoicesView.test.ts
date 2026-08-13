import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import InvoicesView from '@/views/invoices/InvoicesView.vue'
import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { handleApiError, showSuccess } from '@/utils/errorHandler'

vi.mock('vue-router', () => ({
  useRoute: vi.fn(() => ({ params: {} })),
  RouterLink: { template: '<a><slot /></a>' },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/utils/errorHandler', () => ({
  handleApiError: vi.fn(),
  showSuccess: vi.fn(),
  showWarning: vi.fn(),
}))

// --- Fixtures ---
// Alle Felder, die `InvoiceResource` liefert und die die Buttons/Badges
// steuern. `isOverdue` ist bewusst unabhängig vom `status`-String gesetzt,
// da es sich um ein rein datumsbasiert berechnetes Feld handelt (siehe
// design.md Decision D3).
function makeInvoice(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    invoiceNumber: 'RE-2026-0001',
    customer: { user: { fullName: 'Max Mustermann' } },
    invoiceDate: '2026-08-01',
    dueDate: '2026-08-15',
    totalAmount: 100,
    status: 'draft',
    isOverdue: false,
    paidDate: null,
    remindedAt: null,
    originalInvoiceId: null,
    ...overrides,
  }
}

// --- Auth-Store-Helfer ---
function mockTrainerAuth(): void {
  vi.mocked(useAuthStore).mockReturnValue({
    isAuthenticated: true,
    isTrainer: true,
    isAdmin: false,
    isCustomer: false,
  } as any)
}

function mockCustomerAuth(): void {
  vi.mocked(useAuthStore).mockReturnValue({
    isAuthenticated: true,
    isTrainer: false,
    isAdmin: false,
    isCustomer: true,
  } as any)
}

function mockConfirm(returnValue: boolean): void {
  vi.stubGlobal('confirm', vi.fn().mockReturnValue(returnValue))
}

// --- Stubs für Unter-Komponenten ---
const globalStubs = {
  InvoiceFormModal: {
    template: '<div data-testid="invoice-form-modal" />',
  },
  InvoiceDetailModal: {
    name: 'InvoiceDetailModal',
    props: ['isOpen', 'invoice'],
    emits: ['close', 'download', 'edit', 'mark-paid', 'delete', 'finalize', 'cancel', 'send'],
    template: '<div data-testid="invoice-detail-modal" />',
  },
  InvoiceSendDialog: {
    name: 'InvoiceSendDialog',
    props: ['isOpen', 'invoice'],
    emits: ['close', 'download', 'send-email'],
    template: '<div data-testid="invoice-send-dialog" />',
  },
}

function mountView() {
  return mount(InvoicesView, {
    global: {
      stubs: globalStubs,
    },
  })
}

async function mountWithInvoice(invoice: ReturnType<typeof makeInvoice>) {
  mockTrainerAuth()
  vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: [invoice] } })
  const wrapper = mountView()
  await flushPromises()
  return wrapper
}

// Beschränkt sich bewusst auf Buttons innerhalb der Tabelle (`tbody`), damit
// der Header-Button "Neue Rechnung" und etwaige Pagination-Buttons die
// Aktions-Button-Assertions pro Zeile nicht verfälschen.
function actionButtonTexts(wrapper: ReturnType<typeof mountView>): string[] {
  return wrapper.findAll('tbody button').map((b) => b.text())
}

function findActionButton(wrapper: ReturnType<typeof mountView>, text: string) {
  return wrapper.findAll('tbody button').find((b) => b.text() === text)
}

describe('InvoicesView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.unstubAllGlobals()
  })

  // ------------------------------------------------------------------ //
  // Status-Filter                                                        //
  // ------------------------------------------------------------------ //
  it('bietet die Filteroption "Gemahnt" für den Status reminded an', async () => {
    mockTrainerAuth()
    vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: [] } })
    const wrapper = mountView()
    await flushPromises()

    const options = wrapper.findAll('option').map((o) => o.text())
    expect(options).toContain('Gemahnt')
  })

  // ------------------------------------------------------------------ //
  // Status: draft                                                        //
  // ------------------------------------------------------------------ //
  describe('Status "draft"', () => {
    it('zeigt PDF, Bearbeiten, Löschen und Freigeben, aber nicht Senden/Stornieren', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'draft', invoiceNumber: null }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF')
      expect(buttons).toContain('Bearbeiten')
      expect(buttons).toContain('Löschen')
      expect(buttons).toContain('Freigeben')
      expect(buttons).not.toContain('Senden')
      expect(buttons).not.toContain('Stornieren')
    })

    it('löst beim Löschen einen DELETE-Aufruf und ein Neuladen aus', async () => {
      mockConfirm(true)
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'draft', invoiceNumber: null }))
      vi.mocked(apiClient.delete).mockResolvedValueOnce({ data: {} })
      vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: [] } })

      await findActionButton(wrapper, 'Löschen')?.trigger('click')
      await flushPromises()

      expect(apiClient.delete).toHaveBeenCalledWith('/api/v1/invoices/1')
      expect(apiClient.get).toHaveBeenCalledTimes(2)
      expect(showSuccess).toHaveBeenCalled()
    })

    it('löst beim Freigeben einen POST-Aufruf gegen /finalize aus', async () => {
      mockConfirm(true)
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'draft', invoiceNumber: null }))
      vi.mocked(apiClient.post).mockResolvedValueOnce({ data: {} })
      vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: [] } })

      await findActionButton(wrapper, 'Freigeben')?.trigger('click')
      await flushPromises()

      expect(apiClient.post).toHaveBeenCalledWith('/api/v1/invoices/1/finalize')
      expect(showSuccess).toHaveBeenCalled()
    })

    it('bricht Löschen/Freigeben ab, wenn der confirm()-Dialog abgelehnt wird', async () => {
      mockConfirm(false)
      await mountWithInvoice(makeInvoice({ status: 'draft', invoiceNumber: null }))

      expect(apiClient.delete).not.toHaveBeenCalled()
      expect(apiClient.post).not.toHaveBeenCalled()
    })
  })

  // ------------------------------------------------------------------ //
  // Status: sent                                                         //
  // ------------------------------------------------------------------ //
  describe('Status "sent"', () => {
    it('zeigt PDF, einen aktiven Senden-Button und Stornieren, aber nicht Bearbeiten/Löschen/Freigeben', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'sent' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF')
      expect(buttons).toContain('Senden')
      expect(buttons).toContain('Stornieren')
      expect(buttons).not.toContain('Bearbeiten')
      expect(buttons).not.toContain('Löschen')
      expect(buttons).not.toContain('Freigeben')

      const sendButton = findActionButton(wrapper, 'Senden')
      expect(sendButton?.attributes('disabled')).toBeUndefined()
    })

    it('öffnet beim Klick auf Senden den InvoiceSendDialog mit der Rechnung', async () => {
      const invoice = makeInvoice({ status: 'sent' })
      const wrapper = await mountWithInvoice(invoice)

      const dialog = wrapper.findComponent({ name: 'InvoiceSendDialog' })
      expect(dialog.props('isOpen')).toBe(false)

      await findActionButton(wrapper, 'Senden')?.trigger('click')
      await nextTick()

      expect(dialog.props('isOpen')).toBe(true)
      expect(dialog.props('invoice')).toEqual(invoice)
      expect(apiClient.post).not.toHaveBeenCalled()
    })

    it('löst beim Stornieren einen POST-Aufruf gegen /cancel aus und lädt die Liste neu', async () => {
      mockConfirm(true)
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'sent' }))
      vi.mocked(apiClient.post).mockResolvedValueOnce({ data: {} })
      vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: [] } })

      await findActionButton(wrapper, 'Stornieren')?.trigger('click')
      await flushPromises()

      expect(apiClient.post).toHaveBeenCalledWith('/api/v1/invoices/1/cancel')
      expect(apiClient.get).toHaveBeenCalledTimes(2)
      expect(showSuccess).toHaveBeenCalled()
    })

    it('meldet einen Fehler über handleApiError, wenn das Stornieren fehlschlägt', async () => {
      mockConfirm(true)
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'sent' }))
      vi.mocked(apiClient.post).mockRejectedValueOnce(new Error('fail'))

      await findActionButton(wrapper, 'Stornieren')?.trigger('click')
      await flushPromises()

      expect(handleApiError).toHaveBeenCalled()
    })
  })

  // ------------------------------------------------------------------ //
  // InvoiceSendDialog-Interaktion                                       //
  // ------------------------------------------------------------------ //
  describe('InvoiceSendDialog-Interaktion', () => {
    async function openDialog(invoice: ReturnType<typeof makeInvoice>) {
      const wrapper = await mountWithInvoice(invoice)
      await findActionButton(wrapper, 'Senden')?.trigger('click')
      await nextTick()
      const dialog = wrapper.findComponent({ name: 'InvoiceSendDialog' })
      return { wrapper, dialog }
    }

    it('sendet die Rechnung per E-Mail, schließt den Dialog und zeigt einen Erfolgs-Toast', async () => {
      const invoice = makeInvoice({ status: 'sent' })
      const { dialog } = await openDialog(invoice)
      vi.mocked(apiClient.post).mockResolvedValueOnce({ data: {} })

      await dialog.vm.$emit('send-email', invoice)
      await flushPromises()

      expect(apiClient.post).toHaveBeenCalledWith('/api/v1/invoices/1/send-email')
      expect(dialog.props('isOpen')).toBe(false)
      expect(showSuccess).toHaveBeenCalled()
    })

    it('zeigt bei fehlgeschlagenem Versand einen Fehler-Toast, der Dialog bleibt offen', async () => {
      const invoice = makeInvoice({ status: 'sent' })
      const { dialog } = await openDialog(invoice)
      vi.mocked(apiClient.post).mockRejectedValueOnce(new Error('fail'))

      await dialog.vm.$emit('send-email', invoice)
      await flushPromises()

      expect(handleApiError).toHaveBeenCalled()
      expect(dialog.props('isOpen')).toBe(true)
    })

    it('reicht die Backend-Fehlermeldung "keine E-Mail-Adresse hinterlegt" (HTTP 422) unverändert an handleApiError weiter', async () => {
      const invoice = makeInvoice({ status: 'sent' })
      const { dialog } = await openDialog(invoice)
      const backendError = Object.assign(new Error('Request failed with status code 422'), {
        response: {
          status: 422,
          data: { message: 'Für diesen Kunden ist keine E-Mail-Adresse hinterlegt.' },
        },
      })
      vi.mocked(apiClient.post).mockRejectedValueOnce(backendError)

      await dialog.vm.$emit('send-email', invoice)
      await flushPromises()

      expect(handleApiError).toHaveBeenCalledWith(backendError, expect.any(String))
      expect(dialog.props('isOpen')).toBe(true)
    })

    it('löst beim download-Event denselben PDF-Download wie der bestehende PDF-Button aus', async () => {
      const invoice = makeInvoice({ status: 'sent' })
      const { dialog } = await openDialog(invoice)
      vi.mocked(apiClient.get).mockResolvedValueOnce({ data: new Blob() })

      await dialog.vm.$emit('download', invoice)
      await flushPromises()

      expect(apiClient.get).toHaveBeenCalledWith('/api/v1/invoices/1/pdf', { responseType: 'blob' })
    })

    it('schließt den Dialog beim close-Event', async () => {
      const invoice = makeInvoice({ status: 'sent' })
      const { dialog } = await openDialog(invoice)

      await dialog.vm.$emit('close')
      await nextTick()

      expect(dialog.props('isOpen')).toBe(false)
    })

    it('öffnet den InvoiceSendDialog, wenn InvoiceDetailModal ein send-Event emittiert', async () => {
      const invoice = makeInvoice({ status: 'sent' })
      const wrapper = await mountWithInvoice(invoice)
      const detailModal = wrapper.findComponent({ name: 'InvoiceDetailModal' })
      const dialog = wrapper.findComponent({ name: 'InvoiceSendDialog' })

      await detailModal.vm.$emit('send', invoice)
      await nextTick()

      expect(dialog.props('isOpen')).toBe(true)
      expect(dialog.props('invoice')).toEqual(invoice)
    })

    it('lässt das Detail-Modal nach dem Schließen eines aus ihm heraus geöffneten Send-Dialogs weiterhin korrekt sichtbar (Reviewer-Befund: geteilter selectedInvoice-Ref)', async () => {
      const invoice = makeInvoice({ status: 'sent' })
      const wrapper = await mountWithInvoice(invoice)

      // Detail-Modal öffnen (Klick auf die Tabellenzeile)
      await wrapper.find('tbody tr').trigger('click')
      await nextTick()

      const detailModal = wrapper.findComponent({ name: 'InvoiceDetailModal' })
      expect(detailModal.props('isOpen')).toBe(true)
      expect(detailModal.props('invoice')).toEqual(invoice)

      // Send-Dialog aus dem Detail-Modal heraus öffnen: design.md Decision D8
      // sieht vor, dass er über dem weiterhin offenen Detail-Modal erscheint.
      await detailModal.vm.$emit('send', invoice)
      await nextTick()

      const dialog = wrapper.findComponent({ name: 'InvoiceSendDialog' })
      expect(dialog.props('isOpen')).toBe(true)
      expect(detailModal.props('isOpen')).toBe(true)

      // Erfolgreicher App-Mail-Versand schließt nur den Send-Dialog
      vi.mocked(apiClient.post).mockResolvedValueOnce({ data: {} })
      await dialog.vm.$emit('send-email', invoice)
      await flushPromises()

      expect(dialog.props('isOpen')).toBe(false)
      expect(detailModal.props('isOpen')).toBe(true)
      expect(detailModal.props('invoice')).toEqual(invoice)
    })
  })

  // ------------------------------------------------------------------ //
  // Status: paid                                                         //
  // ------------------------------------------------------------------ //
  describe('Status "paid"', () => {
    it('zeigt PDF und Stornieren sowie das Zahlungseingangsdatum, aber keinen Senden-Button', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'paid', paidDate: '2026-08-05' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF')
      expect(buttons).toContain('Stornieren')
      expect(buttons).not.toContain('Senden')
      expect(wrapper.text()).toContain(`Bezahlt am ${new Date('2026-08-05').toLocaleDateString('de-DE')}`)
    })
  })

  // ------------------------------------------------------------------ //
  // Status: reminded                                                     //
  // ------------------------------------------------------------------ //
  describe('Status "reminded"', () => {
    it('zeigt dieselben Aktionen wie "sent" sowie das Mahndatum', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'reminded', remindedAt: '2026-08-10' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF')
      expect(buttons).toContain('Senden')
      expect(buttons).toContain('Stornieren')
      expect(wrapper.text()).toContain(`Gemahnt am ${new Date('2026-08-10').toLocaleDateString('de-DE')}`)
    })
  })

  // ------------------------------------------------------------------ //
  // Status: overdue (persistierter Statuswert, siehe design.md Decision D3 //
  // — wird von keinem Produktivpfad mehr aktiv geschrieben, muss aber für  //
  // evtl. vorhandene Altdaten weiterhin korrekt dargestellt werden)        //
  // ------------------------------------------------------------------ //
  describe('Status "overdue"', () => {
    it('zeigt "Senden", aber KEIN "Stornieren" (InvoicePolicy::cancel() erlaubt "overdue" nicht)', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'overdue', isOverdue: false }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF')
      expect(buttons).toContain('Senden')
      expect(buttons).not.toContain('Stornieren')
      expect(buttons).not.toContain('Bearbeiten')
      expect(buttons).not.toContain('Löschen')
      expect(buttons).not.toContain('Freigeben')

      const badges = wrapper.findAll('span').filter((s) => s.text() === 'Überfällig')
      expect(badges).toHaveLength(1)
    })
  })

  // ------------------------------------------------------------------ //
  // Stornieren-Button bei tatsächlich überfälligen Rechnungen           //
  // (isOverdue === true, Status weiterhin `sent`/`reminded` — siehe     //
  // design.md Decision D3: `overdue` wird nicht mehr aktiv geschrieben) //
  // ------------------------------------------------------------------ //
  describe('Stornieren-Button bei isOverdue === true', () => {
    it('zeigt "Stornieren" für eine überfällige Rechnung mit Status "sent"', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'sent', isOverdue: true }))

      expect(actionButtonTexts(wrapper)).toContain('Stornieren')
    })

    it('zeigt "Stornieren" für eine überfällige Rechnung mit Status "reminded"', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'reminded', isOverdue: true }))

      expect(actionButtonTexts(wrapper)).toContain('Stornieren')
    })
  })

  // ------------------------------------------------------------------ //
  // Überfällig-Markierung (isOverdue, unabhängig vom Status-String)      //
  // ------------------------------------------------------------------ //
  describe('Überfällig-Markierung', () => {
    it('zeigt die Überfällig-Markierung, wenn isOverdue true ist', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'sent', isOverdue: true }))

      const badges = wrapper.findAll('span').filter((s) => s.text() === 'Überfällig')
      expect(badges).toHaveLength(1)
    })

    it('zeigt keine Überfällig-Markierung, wenn isOverdue false ist', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'sent', isOverdue: false }))

      const badges = wrapper.findAll('span').filter((s) => s.text() === 'Überfällig')
      expect(badges).toHaveLength(0)
    })
  })

  // ------------------------------------------------------------------ //
  // Status: cancelled                                                    //
  // ------------------------------------------------------------------ //
  describe('Status "cancelled"', () => {
    it('zeigt nur den PDF-Button', async () => {
      const wrapper = await mountWithInvoice(makeInvoice({ status: 'cancelled' }))

      expect(actionButtonTexts(wrapper)).toEqual(['PDF'])
    })
  })

  // ------------------------------------------------------------------ //
  // Stornorechnungen (originalInvoiceId !== null)                        //
  // ------------------------------------------------------------------ //
  describe('Stornorechnung (originalInvoiceId gesetzt)', () => {
    it('zeigt keinen Stornieren-Button, unabhängig vom eigenen Status', async () => {
      const wrapper = await mountWithInvoice(
        makeInvoice({ status: 'sent', originalInvoiceId: 42 }),
      )

      expect(actionButtonTexts(wrapper)).not.toContain('Stornieren')
    })
  })

  // ------------------------------------------------------------------ //
  // Kunden-Ansicht                                                        //
  // ------------------------------------------------------------------ //
  describe('Kunden-Ansicht', () => {
    it('versteckt alle Aktions-Buttons außer PDF für Kunden', async () => {
      mockCustomerAuth()
      vi.mocked(apiClient.get).mockResolvedValueOnce({
        data: { data: [makeInvoice({ status: 'draft', invoiceNumber: null })] },
      })
      const wrapper = mountView()
      await flushPromises()

      expect(actionButtonTexts(wrapper)).toEqual(['PDF'])
    })
  })
})
