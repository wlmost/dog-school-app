import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import InvoicePaymentDialog from '@/components/InvoicePaymentDialog.vue'
import invoicePaymentDialogSource from '@/components/InvoicePaymentDialog.vue?raw'

// HeadlessUI-Stubs: TransitionRoot respektiert show-Prop; restliche
// Komponenten rendern den Slot direkt, ohne Transitions-Overhead im Test.
// Stilvorbild: InvoiceSendDialog.test.ts.
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
    totalAmount: 200,
    remainingBalance: 150,
    ...overrides,
  }
}

function mountDialog(
  invoice: ReturnType<typeof makeInvoice> | undefined = makeInvoice(),
  isOpen = true,
  isSubmitting = false,
) {
  return mount(InvoicePaymentDialog, {
    props: { isOpen, invoice, isSubmitting },
    global: { stubs: headlessUiStubs },
  })
}

function amountInput(wrapper: ReturnType<typeof mountDialog>) {
  return wrapper.find('input[type="number"]')
}

function submitButton(wrapper: ReturnType<typeof mountDialog>) {
  return wrapper.find('button[type="submit"]')
}

function fullBalanceButton(wrapper: ReturnType<typeof mountDialog>) {
  return wrapper.findAll('button').find((b) => b.text() === 'Volle Restsumme')
}

describe('InvoicePaymentDialog', () => {
  it('belegt das Betragsfeld mit remainingBalance vor', () => {
    const wrapper = mountDialog(makeInvoice({ remainingBalance: 150 }))

    expect((amountInput(wrapper).element as HTMLInputElement).value).toBe('150')
  })

  it('lehnt einen Betrag über remainingBalance ab (Submit bleibt disabled, Fehlertext erscheint)', async () => {
    const wrapper = mountDialog(makeInvoice({ remainingBalance: 150 }))

    await amountInput(wrapper).setValue('200')

    expect(wrapper.text()).toContain('Der Betrag darf den Restbetrag von')
    expect(submitButton(wrapper)?.attributes('disabled')).toBeDefined()
  })

  it('lehnt einen Betrag <= 0 ab (Submit bleibt disabled, Fehlertext erscheint)', async () => {
    const wrapper = mountDialog(makeInvoice({ remainingBalance: 150 }))

    await amountInput(wrapper).setValue('0')

    expect(wrapper.text()).toContain('Der Betrag muss größer als 0 sein.')
    expect(submitButton(wrapper)?.attributes('disabled')).toBeDefined()
  })

  it('emittiert "record-payment" mit dem korrekten Payload beim Klick auf "Zahlung erfassen"', async () => {
    const wrapper = mountDialog(makeInvoice({ remainingBalance: 150 }))

    await amountInput(wrapper).setValue('75')
    await wrapper.find('select').setValue('bank_transfer')
    await wrapper.find('textarea').setValue('Bar erhalten')

    await wrapper.find('form').trigger('submit.prevent')

    const emitted = wrapper.emitted('record-payment')
    expect(emitted).toHaveLength(1)
    const payload = emitted?.[0]?.[0]
    expect(payload).toMatchObject({
      amount: 75,
      paymentMethod: 'bank_transfer',
      notes: 'Bar erhalten',
    })
    expect((payload as { paymentDate: string }).paymentDate).toMatch(/^\d{4}-\d{2}-\d{2}$/)
  })

  it('setzt den Betrag über "Volle Restsumme" auf remainingBalance zurück', async () => {
    const wrapper = mountDialog(makeInvoice({ remainingBalance: 150 }))

    await amountInput(wrapper).setValue('30')
    expect((amountInput(wrapper).element as HTMLInputElement).value).toBe('30')

    await fullBalanceButton(wrapper)?.trigger('click')

    expect((amountInput(wrapper).element as HTMLInputElement).value).toBe('150')
  })

  it('emittiert "close" beim Klick auf Abbrechen', async () => {
    const wrapper = mountDialog()

    await wrapper.findAll('button').find((b) => b.text() === 'Abbrechen')?.trigger('click')

    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('deaktiviert die Buttons während isSubmitting === true', () => {
    const wrapper = mountDialog(makeInvoice({ remainingBalance: 150 }), true, true)

    expect(submitButton(wrapper)?.attributes('disabled')).toBeDefined()
    expect(fullBalanceButton(wrapper)?.attributes('disabled')).toBeDefined()
  })

  it('rendert nichts, wenn isOpen false ist', () => {
    const wrapper = mountDialog(makeInvoice(), false)

    expect(amountInput(wrapper).exists()).toBe(false)
  })

  it('importiert keinen apiClient (reines Presentation-Layer, kein eigener API-Aufruf)', () => {
    expect(invoicePaymentDialogSource).not.toContain('apiClient')
  })

  it('bietet exakt die vom Backend akzeptierten Zahlungsart-Werte an (StorePaymentRequest-Enum)', () => {
    const wrapper = mountDialog(makeInvoice())

    const values = wrapper
      .findAll('select option')
      .map((option) => option.attributes('value'))

    // Muss exakt mit StorePaymentRequest::rules()['paymentMethod']
    // (`in:cash,bank_transfer,paypal,stripe,credit_card`) übereinstimmen.
    expect(values).toEqual(['cash', 'bank_transfer', 'paypal', 'stripe', 'credit_card'])
  })
})
