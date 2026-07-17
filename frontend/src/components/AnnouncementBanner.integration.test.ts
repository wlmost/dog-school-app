import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import AnnouncementBanner from '@/components/AnnouncementBanner.vue'
import { announcementsApi } from '@/api/announcements'

// Diese Datei mockt bewusst NICHT das useAnnouncements-Composable (im
// Unterschied zu AnnouncementBanner.test.ts), sondern nur die
// darunterliegende API-Schicht. So wird das reale Fehlerverhalten von
// useAnnouncements().loadPublic() (try/catch, error-State bleibt intern,
// announcements bleibt leer) im Zusammenspiel mit der Komponente geprüft
// — nicht nur ein vorgetäuschtes leeres Ergebnis über einen Composable-Mock.
vi.mock('@/api/announcements', async () => {
  const actual = await vi.importActual<typeof import('@/api/announcements')>(
    '@/api/announcements',
  )
  return {
    ...actual,
    announcementsApi: {
      getPublic: vi.fn(),
      getAll: vi.fn(),
      create: vi.fn(),
      update: vi.fn(),
      delete: vi.fn(),
    },
  }
})

describe('AnnouncementBanner mit echtem useAnnouncements-Composable', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('rendert keinen sichtbaren Bereich und stürzt nicht ab, wenn loadPublic einen Fehler wirft', async () => {
    vi.mocked(announcementsApi.getPublic).mockRejectedValue(new Error('Netzwerkfehler'))

    let mountError: unknown = null
    let wrapper: ReturnType<typeof mount> | undefined
    try {
      wrapper = mount(AnnouncementBanner)
      await flushPromises()
    } catch (e) {
      mountError = e
    }

    expect(mountError).toBeNull()
    expect(wrapper!.find('section').exists()).toBe(false)
  })

  it('rendert eine Karte pro aktiver Ankündigung, wenn loadPublic erfolgreich lädt', async () => {
    vi.mocked(announcementsApi.getPublic).mockResolvedValue([
      {
        id: 1,
        title: 'Kursausfall',
        body: '<p>Text</p>',
        imageUrl: null,
        displayDays: 7,
        expiresAt: '2099-01-01T00:00:00.000Z',
        isActive: true,
        createdAt: '2026-01-01T00:00:00.000Z',
        updatedAt: '2026-01-01T00:00:00.000Z',
      },
    ])

    const wrapper = mount(AnnouncementBanner)
    await flushPromises()

    expect(wrapper.find('section').exists()).toBe(true)
    expect(wrapper.findAll('article')).toHaveLength(1)
  })
})
