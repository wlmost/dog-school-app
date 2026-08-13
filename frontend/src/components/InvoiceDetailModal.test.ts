import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import InvoiceDetailModal from '@/components/InvoiceDetailModal.vue'
import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

// HeadlessUI-Stubs: TransitionRoot respektiert show-Prop; restliche
// Komponenten rendern den Slot direkt, ohne Transitions-Overhead im Test.
// Stilvorbild: CustomerFormModal.test.ts.
const headlessUiStubs = {
  TransitionRoot: {
    props: ['show'],
    template: '<div v-if="show"><slot /></div>',
  },
  TransitionChild: {
    template: '<div><slot /></div>',
  },
  Dialog: {
    template: '<div><slot /></div>',
  },
  DialogPanel: {
    template: '<div><slot /></div>',
  },
  DialogTitle: {
    template: '<div><slot /></div>',
  },
}

// Alle Felder, die `InvoiceResource` liefert und die die Buttons/Badges
// steuern. Stilvorbild: InvoicesView.test.ts::makeInvoice().
function makeInvoice(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    invoiceNumber: 'RE-2026-0001',
    customer: { user: { fullName: 'Max Mustermann' } },
    invoiceDate: '2026-08-01',
    dueDate: '2026-08-15',
    items: [],
    subtotalAmount: 100,
    taxAmount: 19,
    totalAmount: 119,
    status: 'draft',
    originalInvoiceId: null,
    originalInvoiceNumber: null,
    cancellationInvoiceNumber: null,
    ...overrides,
  }
}

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

async function mountModal(invoice: ReturnType<typeof makeInvoice>) {
  vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: { company: [], email: [], general: [] } } })
  const wrapper = mount(InvoiceDetailModal, {
    props: { isOpen: true, invoice },
    global: { stubs: headlessUiStubs },
  })
  await flushPromises()
  return wrapper
}

function actionButtonTexts(wrapper: Awaited<ReturnType<typeof mountModal>>): string[] {
  return wrapper.findAll('button').map((b) => b.text()).filter((t) => t !== '')
}

function findActionButton(wrapper: Awaited<ReturnType<typeof mountModal>>, text: string) {
  return wrapper.findAll('button').find((b) => b.text() === text)
}

describe('InvoiceDetailModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('Status "draft"', () => {
    it('zeigt PDF, Bearbeiten, Löschen und Freigeben, aber nicht Bezahlt-markieren/Senden/Stornieren', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'draft' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF herunterladen')
      expect(buttons).toContain('Bearbeiten')
      expect(buttons).toContain('Löschen')
      expect(buttons).toContain('Freigeben')
      expect(buttons).not.toContain('Als bezahlt markieren')
      expect(buttons).not.toContain('Senden')
      expect(buttons).not.toContain('Stornieren')
    })

    it('zeigt keinen "Als bezahlt markieren"-Button (ein Entwurf muss zuerst freigegeben werden, siehe InvoiceController::markAsPaid())', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'draft' }))

      expect(findActionButton(wrapper, 'Als bezahlt markieren')).toBeUndefined()
    })

    it('emittiert "delete" beim Klick auf Löschen', async () => {
      mockTrainerAuth()
      const invoice = makeInvoice({ status: 'draft' })
      const wrapper = await mountModal(invoice)

      await findActionButton(wrapper, 'Löschen')?.trigger('click')

      expect(wrapper.emitted('delete')?.[0]).toEqual([invoice])
    })

    it('emittiert "finalize" beim Klick auf Freigeben', async () => {
      mockTrainerAuth()
      const invoice = makeInvoice({ status: 'draft' })
      const wrapper = await mountModal(invoice)

      await findActionButton(wrapper, 'Freigeben')?.trigger('click')

      expect(wrapper.emitted('finalize')?.[0]).toEqual([invoice])
    })
  })

  describe('Status "sent"', () => {
    it('zeigt PDF, Bezahlt-markieren, einen aktiven Senden-Button und Stornieren, aber nicht Bearbeiten/Löschen/Freigeben', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'sent' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF herunterladen')
      expect(buttons).toContain('Als bezahlt markieren')
      expect(buttons).toContain('Senden')
      expect(buttons).toContain('Stornieren')
      expect(buttons).not.toContain('Bearbeiten')
      expect(buttons).not.toContain('Löschen')
      expect(buttons).not.toContain('Freigeben')

      const sendButton = findActionButton(wrapper, 'Senden')
      expect(sendButton?.attributes('disabled')).toBeUndefined()
    })

    it('emittiert "cancel" beim Klick auf Stornieren', async () => {
      mockTrainerAuth()
      const invoice = makeInvoice({ status: 'sent' })
      const wrapper = await mountModal(invoice)

      await findActionButton(wrapper, 'Stornieren')?.trigger('click')

      expect(wrapper.emitted('cancel')?.[0]).toEqual([invoice])
    })

    it('emittiert "send" mit dem invoice-Objekt beim Klick auf Senden', async () => {
      mockTrainerAuth()
      const invoice = makeInvoice({ status: 'sent' })
      const wrapper = await mountModal(invoice)

      await findActionButton(wrapper, 'Senden')?.trigger('click')

      expect(wrapper.emitted('send')?.[0]).toEqual([invoice])
    })
  })

  describe('Status "paid"', () => {
    it('zeigt PDF und Stornieren, aber keinen Senden-Button', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'paid' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF herunterladen')
      expect(buttons).toContain('Stornieren')
      expect(buttons).not.toContain('Senden')
    })
  })

  describe('Status "reminded"', () => {
    it('zeigt dieselben Aktionen wie "sent"', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'reminded' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('Senden')
      expect(buttons).toContain('Stornieren')
    })

    it('zeigt den Badge "Gemahnt"', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'reminded' }))

      expect(wrapper.text()).toContain('Gemahnt')
    })
  })

  describe('Status "overdue"', () => {
    it('zeigt "Senden" und den Badge "Überfällig", aber KEIN "Stornieren" (InvoicePolicy::cancel() erlaubt "overdue" nicht)', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'overdue' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('Senden')
      expect(buttons).not.toContain('Stornieren')
      expect(buttons).not.toContain('Bearbeiten')
      expect(buttons).not.toContain('Löschen')
      expect(buttons).not.toContain('Freigeben')
      expect(wrapper.text()).toContain('Überfällig')
    })
  })

  describe('Status "cancelled"', () => {
    it('zeigt nur PDF und Schließen', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'cancelled' }))

      expect(actionButtonTexts(wrapper)).toEqual(['Schließen', 'PDF herunterladen'])
    })
  })

  describe('Stornorechnung (originalInvoiceId gesetzt)', () => {
    it('zeigt keinen Stornieren-Button, unabhängig vom eigenen Status', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'sent', originalInvoiceId: 42 }))

      expect(actionButtonTexts(wrapper)).not.toContain('Stornieren')
    })
  })

  describe('Storno-Referenz-Anzeige', () => {
    it('zeigt die Original-Rechnungsnummer, wenn originalInvoiceNumber gesetzt ist', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ originalInvoiceNumber: 'RE-2026-0001' }))

      expect(wrapper.text()).toContain('Stornorechnung zu:')
      expect(wrapper.text()).toContain('RE-2026-0001')
    })

    it('zeigt die Storno-Rechnungsnummer, wenn cancellationInvoiceNumber gesetzt ist', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ cancellationInvoiceNumber: 'RE-2026-0099' }))

      expect(wrapper.text()).toContain('Storniert durch:')
      expect(wrapper.text()).toContain('RE-2026-0099')
    })

    it('zeigt keine Storno-Referenz, wenn keine der beiden Nummern gesetzt ist', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice())

      expect(wrapper.text()).not.toContain('Stornorechnung zu:')
      expect(wrapper.text()).not.toContain('Storniert durch:')
    })
  })

  describe('Kunden-Ansicht', () => {
    it('versteckt alle Aktions-Buttons außer Schließen und PDF für Kunden', async () => {
      mockCustomerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'draft' }))

      expect(actionButtonTexts(wrapper)).toEqual(['Schließen', 'PDF herunterladen'])
    })
  })
})
