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
// `remainingBalance`/`totalPaid` defaulten auf den unbezahlten Fall (voller
// Betrag offen), damit `canRecordPayment()` für die payable Status-Werte
// standardmäßig `true` liefert, sofern nicht explizit überschrieben.
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
    remainingBalance: 119,
    totalPaid: 0,
    payments: [],
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
    it('zeigt PDF, Bearbeiten, Löschen und Freigeben, aber nicht Zahlung erfassen/Senden/Stornieren', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'draft' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF herunterladen')
      expect(buttons).toContain('Bearbeiten')
      expect(buttons).toContain('Löschen')
      expect(buttons).toContain('Freigeben')
      expect(buttons).not.toContain('Zahlung erfassen')
      expect(buttons).not.toContain('Senden')
      expect(buttons).not.toContain('Stornieren')
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
    it('zeigt PDF, Zahlung erfassen, einen aktiven Senden-Button und Stornieren, aber nicht Bearbeiten/Löschen/Freigeben', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'sent' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF herunterladen')
      expect(buttons).toContain('Zahlung erfassen')
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
    it('zeigt PDF und Stornieren, aber keinen Senden- oder Zahlung-erfassen-Button', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'paid', remainingBalance: 0 }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('PDF herunterladen')
      expect(buttons).toContain('Stornieren')
      expect(buttons).not.toContain('Senden')
      expect(buttons).not.toContain('Zahlung erfassen')
    })
  })

  describe('Status "reminded"', () => {
    it('zeigt dieselben Aktionen wie "sent", inkl. Zahlung erfassen', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'reminded' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('Senden')
      expect(buttons).toContain('Stornieren')
      expect(buttons).toContain('Zahlung erfassen')
    })

    it('zeigt den Badge "Gemahnt"', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'reminded' }))

      expect(wrapper.text()).toContain('Gemahnt')
    })
  })

  describe('Status "overdue"', () => {
    it('zeigt "Senden", "Zahlung erfassen" und den Badge "Überfällig", aber KEIN "Stornieren" (InvoicePolicy::cancel() erlaubt "overdue" nicht)', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'overdue' }))

      const buttons = actionButtonTexts(wrapper)
      expect(buttons).toContain('Senden')
      expect(buttons).toContain('Zahlung erfassen')
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

    it('zeigt keinen "Zahlung erfassen"-Button für Kunden, selbst bei offenem Restbetrag', async () => {
      mockCustomerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'sent' }))

      expect(actionButtonTexts(wrapper)).not.toContain('Zahlung erfassen')
    })
  })

  describe('"Zahlung erfassen"-Button (canRecordPayment)', () => {
    it.each(['sent', 'reminded', 'overdue'])('ist sichtbar für Status "%s" mit offenem Restbetrag und emittiert "record-payment" mit dem invoice-Objekt', async (status) => {
      mockTrainerAuth()
      const invoice = makeInvoice({ status })
      const wrapper = await mountModal(invoice)

      const button = findActionButton(wrapper, 'Zahlung erfassen')
      expect(button).toBeDefined()

      await button?.trigger('click')

      expect(wrapper.emitted('record-payment')?.[0]).toEqual([invoice])
    })

    it.each(['draft', 'paid', 'cancelled'])('ist NICHT sichtbar für Status "%s"', async (status) => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status, remainingBalance: status === 'paid' ? 0 : 119 }))

      expect(findActionButton(wrapper, 'Zahlung erfassen')).toBeUndefined()
    })

    it('ist NICHT sichtbar, wenn der Restbetrag bereits 0 ist, obwohl der Status payable wäre', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'sent', remainingBalance: 0 }))

      expect(findActionButton(wrapper, 'Zahlung erfassen')).toBeUndefined()
    })
  })

  describe('Zahlungsliste (Bugfix: camelCase-Felder statt snake_case)', () => {
    it('zeigt Zahlungsdatum und Zahlungsart korrekt an, wenn payment.paymentDate/payment.paymentMethod (camelCase) gesetzt sind', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({
        status: 'sent',
        remainingBalance: 19,
        totalPaid: 100,
        payments: [
          { id: 1, amount: 100, paymentDate: '2026-08-05', paymentMethod: 'bank_transfer' },
        ],
      }))

      // `toLocaleDateString('de-DE')` liefert Tag/Monat ohne führende Null.
      expect(wrapper.text()).toContain('5.8.2026')
      expect(wrapper.text()).toContain('bank_transfer')
      // Regressionsschutz: die zuvor gelesenen snake_case-Felder existieren
      // im Fixture nicht, ein Rückfall auf `payment.payment_date` würde
      // `formatDate(undefined)` -> '-' liefern statt des Datums.
      expect(wrapper.text()).not.toContain('undefined')
    })

    it('zeigt payment.notes an, falls vorhanden', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({
        status: 'sent',
        remainingBalance: 19,
        totalPaid: 100,
        payments: [
          { id: 1, amount: 100, paymentDate: '2026-08-05', paymentMethod: 'cash', notes: 'Bar bezahlt' },
        ],
      }))

      expect(wrapper.text()).toContain('Bar bezahlt')
    })

    it('zeigt keine Notiz-Zeile, wenn payment.notes fehlt', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({
        status: 'sent',
        remainingBalance: 19,
        totalPaid: 100,
        payments: [
          { id: 1, amount: 100, paymentDate: '2026-08-05', paymentMethod: 'cash' },
        ],
      }))

      // Kein Absturz/leerer Text-Knoten für die optionale Notiz.
      expect(wrapper.text()).toContain('Zahlungen')
    })
  })

  describe('Restbetrag-Zusammenfassungszeile', () => {
    it('zeigt "Bezahlt: … von … — Rest: …", wenn mindestens eine Zahlung vorliegt', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({
        status: 'sent',
        totalAmount: 119,
        totalPaid: 100,
        remainingBalance: 19,
        payments: [
          { id: 1, amount: 100, paymentDate: '2026-08-05', paymentMethod: 'cash' },
        ],
      }))

      // `Intl.NumberFormat('de-DE', { style: 'currency' })` trennt Betrag und
      // Symbol mit einem geschützten Leerzeichen (U+00A0), kein normales
      // Leerzeichen.
      expect(wrapper.text()).toContain(
        'Bezahlt: 100,00 € von 119,00 € — Rest: 19,00 €'
      )
    })

    it('zeigt keine Zusammenfassungszeile, wenn keine Zahlungen vorliegen', async () => {
      mockTrainerAuth()
      const wrapper = await mountModal(makeInvoice({ status: 'sent', payments: [] }))

      expect(wrapper.text()).not.toContain('Bezahlt:')
      expect(wrapper.text()).not.toContain('Rest:')
    })
  })
})
