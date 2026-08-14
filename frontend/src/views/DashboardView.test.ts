import { mount, flushPromises, RouterLinkStub } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import DashboardView from '@/views/DashboardView.vue'
import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'

// Nur der Card-Block "Überfällige & gemahnte Rechnungen" (T09) ist Gegenstand
// dieser Datei — sie deckt bewusst nicht jeden bestehenden Dashboard-Block ab
// (z. B. Hundeanmeldungen, Stornierungsanfragen), da diese vor T09 ungetestet
// waren und nicht Teil dieser Task sind.
//
// `DashboardView.vue` verwendet `<router-link>` direkt im Template (statt es
// zu importieren), ohne einen echten Router zu installieren. `RouterLinkStub`
// von `@vue/test-utils` wird daher explizit über `global.stubs` unter dem
// im Template verwendeten Tag-Namen registriert (kein `vi.mock('vue-router')`
// nötig, da keine Composables aus `vue-router` importiert werden).

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
// Felder exakt wie von `DashboardController::mapOverdueOrRemindedInvoice()`
// geliefert (siehe task-T06.notes.md): id, invoiceNumber, customerName,
// dueDate (d.m.Y), status, dunningLevel, remainingBalance.
function makeOverdueInvoice(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    invoiceNumber: 'RE-2026-0001',
    customerName: 'Max Mustermann',
    dueDate: '01.08.2026',
    status: 'overdue',
    dunningLevel: null,
    remainingBalance: 150.5,
    ...overrides,
  }
}

function baseDashboardResponse(overrides: Record<string, unknown> = {}) {
  return {
    stats: {
      customers: 0,
      dogs: 0,
      courses: 0,
      invoices: 0,
      bookings: 0,
      pendingDogRequests: 0,
      pendingDogDeletionRequests: 0,
    },
    upcomingSessions: [],
    recentBookings: [],
    pendingDogRegistrations: [],
    pendingDogDeletionRequests: [],
    pendingCancellationRequests: [],
    overdueOrRemindedInvoices: [],
    ...overrides,
  }
}

// --- Auth-Store-Helfer ---
function mockAuth(role: 'admin' | 'trainer' | 'customer'): void {
  vi.mocked(useAuthStore).mockReturnValue({
    user: {
      id: 1,
      first_name: 'Ada',
      last_name: 'Lovelace',
      role,
      full_name: 'Ada Lovelace',
    },
  } as any)
}

async function mountWithResponse(data: Record<string, unknown>) {
  vi.mocked(apiClient.get).mockResolvedValueOnce({ data })
  const wrapper = mount(DashboardView, {
    global: {
      stubs: {
        'router-link': RouterLinkStub,
      },
    },
  })
  await flushPromises()
  return wrapper
}

function overdueCard(wrapper: ReturnType<typeof mount>) {
  const heading = wrapper
    .findAll('h4')
    .find((h) => h.text() === 'Überfällige & gemahnte Rechnungen')
  return heading?.element.closest('.card') ?? null
}

describe('DashboardView — Widget "Überfällige & gemahnte Rechnungen"', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('Sichtbarkeit', () => {
    it('zeigt die Karte für Admins', async () => {
      mockAuth('admin')
      const wrapper = await mountWithResponse(
        baseDashboardResponse({ overdueOrRemindedInvoices: [makeOverdueInvoice()] })
      )

      expect(overdueCard(wrapper)).not.toBeNull()
    })

    it('zeigt die Karte für Trainer', async () => {
      mockAuth('trainer')
      const wrapper = await mountWithResponse(
        baseDashboardResponse({ overdueOrRemindedInvoices: [makeOverdueInvoice()] })
      )

      expect(overdueCard(wrapper)).not.toBeNull()
    })

    it('zeigt die Karte NICHT für Kunden', async () => {
      mockAuth('customer')
      const wrapper = await mountWithResponse(baseDashboardResponse())

      expect(overdueCard(wrapper)).toBeNull()
    })
  })

  describe('Rechnungsliste', () => {
    it('rendert Rechnungsnummer, Kundenname, Fälligkeitsdatum, Status und Restbetrag je Zeile', async () => {
      mockAuth('admin')
      const wrapper = await mountWithResponse(
        baseDashboardResponse({
          overdueOrRemindedInvoices: [
            makeOverdueInvoice({
              invoiceNumber: 'RE-2026-0042',
              customerName: 'Erika Musterfrau',
              dueDate: '15.07.2026',
              status: 'overdue',
              dunningLevel: null,
              remainingBalance: 89.9,
            }),
          ],
        })
      )

      const card = overdueCard(wrapper)
      expect(card?.textContent).toContain('RE-2026-0042')
      expect(card?.textContent).toContain('Erika Musterfrau')
      expect(card?.textContent).toContain('15.07.2026')
      expect(card?.textContent).toContain('Überfällig')
      expect(card?.textContent).toContain('89,90')
    })

    it('zeigt die Mahnstufe, wenn dunningLevel gesetzt ist', async () => {
      mockAuth('admin')
      const wrapper = await mountWithResponse(
        baseDashboardResponse({
          overdueOrRemindedInvoices: [
            makeOverdueInvoice({ status: 'reminded', dunningLevel: 2 }),
          ],
        })
      )

      expect(overdueCard(wrapper)?.textContent).toContain('Mahnstufe 2')
    })

    it('zeigt keine Mahnstufe, wenn dunningLevel null ist', async () => {
      mockAuth('admin')
      const wrapper = await mountWithResponse(
        baseDashboardResponse({
          overdueOrRemindedInvoices: [makeOverdueInvoice({ dunningLevel: null })],
        })
      )

      expect(overdueCard(wrapper)?.textContent).not.toContain('Mahnstufe')
    })

    it('rendert für jeden Eintrag aus overdueOrRemindedInvoices eine eigene Zeile', async () => {
      mockAuth('admin')
      const wrapper = await mountWithResponse(
        baseDashboardResponse({
          overdueOrRemindedInvoices: [
            makeOverdueInvoice({ id: 1, invoiceNumber: 'RE-2026-0001' }),
            makeOverdueInvoice({ id: 2, invoiceNumber: 'RE-2026-0002' }),
            makeOverdueInvoice({ id: 3, invoiceNumber: 'RE-2026-0003' }),
          ],
        })
      )

      const card = overdueCard(wrapper)
      expect(card?.querySelectorAll('.divide-y > div').length).toBe(3)
    })
  })

  describe('Leerer Zustand', () => {
    it('zeigt den Hinweistext, wenn overdueOrRemindedInvoices leer ist', async () => {
      mockAuth('admin')
      const wrapper = await mountWithResponse(
        baseDashboardResponse({ overdueOrRemindedInvoices: [] })
      )

      expect(overdueCard(wrapper)?.textContent).toContain(
        'Keine überfälligen oder gemahnten Rechnungen'
      )
    })

    it('fällt auf eine leere Liste zurück, wenn overdueOrRemindedInvoices im Response fehlt', async () => {
      mockAuth('admin')
      const response = baseDashboardResponse()
      delete (response as Record<string, unknown>).overdueOrRemindedInvoices
      const wrapper = await mountWithResponse(response)

      expect(overdueCard(wrapper)?.textContent).toContain(
        'Keine überfälligen oder gemahnten Rechnungen'
      )
    })
  })

  describe('Link zur Rechnungsübersicht', () => {
    it('enthält einen Link zur Rechnungsliste (name: "Invoices")', async () => {
      mockAuth('admin')
      const wrapper = await mountWithResponse(
        baseDashboardResponse({ overdueOrRemindedInvoices: [makeOverdueInvoice()] })
      )

      const link = wrapper
        .findAllComponents(RouterLinkStub)
        .find((c) => c.text().trim() === 'Zur Rechnungsübersicht')
      expect(link).toBeTruthy()
      expect(link?.props('to')).toEqual({ name: 'Invoices' })
    })
  })
})
