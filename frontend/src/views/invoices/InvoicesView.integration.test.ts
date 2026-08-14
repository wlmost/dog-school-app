import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import InvoicesView from '@/views/invoices/InvoicesView.vue'
import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'

// Echter Integrationstest für den Event-Vertrag zwischen `InvoicesView.vue`
// (T07) und `InvoiceDetailModal.vue` (T08): beide Dateien wurden in
// getrennten, parallelen Worktrees entwickelt (siehe task-T07.notes.md/
// task-T08.notes.md "Offene Punkte für Reviewer/Tester" — der jeweilige
// Entwickler-Agent hat den Event-Namen/die Payload-Struktur der jeweils
// anderen Datei nur durch Lesen der Task-Beschreibung angenommen, nicht
// durch tatsächliches Zusammenspiel verifiziert). `InvoicesView.test.ts`
// stubbt `InvoiceDetailModal` komplett (inkl. einer manuell in der
// `emits`-Liste des Stubs nachgetragenen `'remind'`-Deklaration) und simuliert
// den Emit direkt über `detailModal.vm.$emit('remind', invoice)` — das
// beweist nur, dass `InvoicesView.vue` auf ein `remind`-Event *reagieren
// kann*, nicht, dass die *echte* `InvoiceDetailModal.vue` dieses Event mit
// demselben Namen/derselben Payload tatsächlich auslöst. Dieser Test
// mountet beide Komponenten echt (nur die HeadlessUI-Innereien sowie die
// hier nicht relevanten Geschwister-Modals bleiben gestubbt) und prüft den
// kompletten Pfad: Tabellenzeile anklicken -> echtes Detail-Modal öffnet
// -> echter "Mahnen"-Button darin anklicken -> `InvoicesView.vue` löst
// tatsächlich den erwarteten `POST .../remind`-Aufruf aus.

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

// HeadlessUI-Stubs für das echte `InvoiceDetailModal.vue` — 1:1 aus
// `InvoiceDetailModal.test.ts` übernommen (dort bereits als Stilvorbild
// für HeadlessUI-Mounting in diesem Projekt etabliert).
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

// Die drei hier nicht relevanten Geschwister-Modals bleiben gestubbt
// (analog zu `InvoicesView.test.ts`) — nur `InvoiceDetailModal` wird echt
// gemountet, das ist der Gegenstand dieses Integrationstests.
const siblingModalStubs = {
  InvoiceFormModal: {
    template: '<div data-testid="invoice-form-modal" />',
  },
  InvoiceSendDialog: {
    name: 'InvoiceSendDialog',
    props: ['isOpen', 'invoice'],
    emits: ['close', 'download', 'send-email'],
    template: '<div data-testid="invoice-send-dialog" />',
  },
  InvoicePaymentDialog: {
    name: 'InvoicePaymentDialog',
    props: ['isOpen', 'invoice', 'isSubmitting'],
    emits: ['close', 'record-payment'],
    template: '<div data-testid="invoice-payment-dialog" />',
  },
}

function makeInvoice(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    invoiceNumber: 'RE-2026-0001',
    customer: { user: { fullName: 'Max Mustermann' } },
    invoiceDate: '2026-08-01',
    dueDate: '2026-08-15',
    items: [],
    totalAmount: 100,
    remainingBalance: 100,
    totalPaid: 0,
    payments: [],
    status: 'sent',
    isOverdue: false,
    paidDate: null,
    remindedAt: null,
    originalInvoiceId: null,
    originalInvoiceNumber: null,
    cancellationInvoiceNumber: null,
    nextDunningLevel: 1,
    nextDunningFeeAmount: 5,
    dunnings: [],
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

function mockConfirm(returnValue: boolean): void {
  vi.stubGlobal('confirm', vi.fn().mockReturnValue(returnValue))
}

async function mountWithInvoice(invoice: ReturnType<typeof makeInvoice>) {
  mockTrainerAuth()
  vi.mocked(apiClient.get).mockImplementation((url: string) => {
    if (url === '/api/v1/settings') {
      return Promise.resolve({ data: { data: { company: [], email: [], general: [] } } })
    }

    // Erster Aufruf (Rechnungsliste) liefert die Fixture, jeder weitere
    // (z. B. der Reload nach erfolgreichem Mahnen) eine leere Liste.
    return Promise.resolve({ data: { data: [invoice] } })
  })

  const wrapper = mount(InvoicesView, {
    global: {
      stubs: { ...siblingModalStubs, ...headlessUiStubs },
    },
  })
  await flushPromises()

  return wrapper
}

describe('Event-Vertrag InvoicesView <-> echtes InvoiceDetailModal (Integrationstest)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.unstubAllGlobals()
  })

  it('löst über den "Mahnen"-Button im echten InvoiceDetailModal tatsächlich den POST gegen /remind in InvoicesView aus', async () => {
    mockConfirm(true)
    const invoice = makeInvoice({ status: 'sent', nextDunningLevel: 2, nextDunningFeeAmount: 10 })
    const wrapper = await mountWithInvoice(invoice)

    // Echtes Detail-Modal über den Tabellenzeilen-Klick öffnen (kein Stub,
    // siehe `siblingModalStubs` oben — `InvoiceDetailModal` ist bewusst
    // nicht enthalten).
    await wrapper.find('tbody tr').trigger('click')
    await flushPromises()

    const detailModal = wrapper.findComponent({ name: 'InvoiceDetailModal' })
    expect(detailModal.exists()).toBe(true)
    expect(detailModal.props('isOpen')).toBe(true)

    // Der "Mahnen"-Button muss im echten, gerenderten Markup des Modals
    // existieren (nicht nur laut Task-Beschreibung angenommen).
    const remindButton = detailModal
      .findAll('button')
      .find((b) => b.text() === 'Mahnen')
    expect(remindButton, 'Der "Mahnen"-Button fehlt im echten InvoiceDetailModal-Markup').toBeDefined()

    vi.mocked(apiClient.post).mockResolvedValueOnce({ data: {} })

    await remindButton!.trigger('click')
    await flushPromises()

    // Bestätigt den vollständigen Vertrag: echter Klick im echten Modal
    // -> echtes `remind`-Event mit dem Invoice-Objekt als Payload -> von
    // `InvoicesView.vue` korrekt empfangen -> korrekter POST-Aufruf.
    expect(confirm).toHaveBeenCalledWith(expect.stringContaining('Mahnung Stufe 2'))
    expect(apiClient.post).toHaveBeenCalledWith('/api/v1/invoices/1/remind')
  })

  it('zeigt im echten InvoiceDetailModal keinen "Mahnen"-Button mehr, wenn bereits die maximale Mahnstufe erreicht ist', async () => {
    const invoice = makeInvoice({ status: 'reminded', nextDunningLevel: null, nextDunningFeeAmount: null })
    const wrapper = await mountWithInvoice(invoice)

    await wrapper.find('tbody tr').trigger('click')
    await flushPromises()

    const detailModal = wrapper.findComponent({ name: 'InvoiceDetailModal' })
    expect(detailModal.props('isOpen')).toBe(true)

    const remindButton = detailModal
      .findAll('button')
      .find((b) => b.text() === 'Mahnen')
    expect(remindButton).toBeUndefined()
  })
})
