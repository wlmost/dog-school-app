import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import InvoiceSendDialog from '@/components/InvoiceSendDialog.vue'

// HeadlessUI-Stubs: TransitionRoot respektiert show-Prop; restliche
// Komponenten rendern den Slot direkt, ohne Transitions-Overhead im Test.
// Stilvorbild: InvoiceDetailModal.test.ts.
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

function makeInvoice(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    invoiceNumber: 'RE-2026-0001',
    status: 'sent',
    customer: { user: { fullName: 'Max Mustermann', email: 'max@example.com' } },
    ...overrides,
  }
}

function mountDialog(invoice: ReturnType<typeof makeInvoice> | undefined = makeInvoice(), isOpen = true) {
  return mount(InvoiceSendDialog, {
    props: { isOpen, invoice },
    global: { stubs: headlessUiStubs },
  })
}

function findButton(wrapper: ReturnType<typeof mountDialog>, text: string) {
  return wrapper.findAll('button').find((b) => b.text() === text)
}

describe('InvoiceSendDialog', () => {
  it('zeigt beide Versandoptionen an, wenn eine E-Mail-Adresse vorhanden ist', () => {
    const wrapper = mountDialog(makeInvoice({ customer: { user: { email: 'max@example.com' } } }))

    expect(findButton(wrapper, 'Aus der App versenden')).toBeDefined()
    expect(findButton(wrapper, 'Manuell versenden (PDF herunterladen)')).toBeDefined()
  })

  it('zeigt beide Versandoptionen an, auch wenn keine E-Mail-Adresse vorhanden ist (kein hasEmail-Zweig, User-Gate-1-Entscheidung 4)', () => {
    const wrapper = mountDialog(makeInvoice({ customer: { user: { email: null } } }))

    expect(findButton(wrapper, 'Aus der App versenden')).toBeDefined()
    expect(findButton(wrapper, 'Manuell versenden (PDF herunterladen)')).toBeDefined()
  })

  it('zeigt die Rechnungsnummer als Kontext-Info', () => {
    const wrapper = mountDialog(makeInvoice({ invoiceNumber: 'RE-2026-0042' }))

    expect(wrapper.text()).toContain('RE-2026-0042')
  })

  it('zeigt die Kunden-E-Mail-Adresse als Hinweistext', () => {
    const wrapper = mountDialog(makeInvoice({ customer: { user: { email: 'kunde@example.com' } } }))

    expect(wrapper.text()).toContain('kunde@example.com')
  })

  it('emittiert "send-email" mit dem Invoice-Objekt beim Klick auf "Aus der App versenden", ohne eigenen API-Aufruf', async () => {
    const invoice = makeInvoice()
    const wrapper = mountDialog(invoice)

    await findButton(wrapper, 'Aus der App versenden')?.trigger('click')

    expect(wrapper.emitted('send-email')?.[0]).toEqual([invoice])
    expect(wrapper.emitted('download')).toBeUndefined()
    expect(wrapper.emitted('close')).toBeUndefined()
  })

  it('emittiert "download" mit dem Invoice-Objekt beim Klick auf "Manuell versenden"', async () => {
    const invoice = makeInvoice()
    const wrapper = mountDialog(invoice)

    await findButton(wrapper, 'Manuell versenden (PDF herunterladen)')?.trigger('click')

    expect(wrapper.emitted('download')?.[0]).toEqual([invoice])
    expect(wrapper.emitted('send-email')).toBeUndefined()
  })

  it('emittiert "close" beim Klick auf Schließen', async () => {
    const wrapper = mountDialog()

    await findButton(wrapper, 'Schließen')?.trigger('click')

    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('rendert nichts, wenn isOpen false ist', () => {
    const wrapper = mountDialog(makeInvoice(), false)

    expect(findButton(wrapper, 'Aus der App versenden')).toBeUndefined()
  })
})
