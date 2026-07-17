import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import AnnouncementBanner from '@/components/AnnouncementBanner.vue'
import { useAnnouncements } from '@/composables/useAnnouncements'
import type { Announcement } from '@/api/announcements'

vi.mock('@/composables/useAnnouncements', () => ({
  useAnnouncements: vi.fn(),
}))

const mockedUseAnnouncements = vi.mocked(useAnnouncements)

const withImage: Announcement = {
  id: 1,
  title: 'Kursausfall am 20.07.',
  body: '<p>Der Welpenkurs entfällt <strong>krankheitsbedingt</strong>.</p>',
  imageUrl: 'https://example.test/storage/announcement-images/xyz.jpg',
  displayDays: 7,
  expiresAt: '2026-07-24T10:00:00.000000Z',
  isActive: true,
  createdAt: '2026-07-17T10:00:00.000000Z',
  updatedAt: '2026-07-17T10:00:00.000000Z',
}

const withoutImage: Announcement = {
  id: 2,
  title: 'Neue Öffnungszeiten',
  body: '<p>Ab sofort gelten neue Öffnungszeiten.</p>',
  imageUrl: null,
  displayDays: 14,
  expiresAt: '2026-07-31T10:00:00.000000Z',
  isActive: true,
  createdAt: '2026-07-17T10:00:00.000000Z',
  updatedAt: '2026-07-17T10:00:00.000000Z',
}

/** Builds the mocked composable return value with the given announcements. */
function stubUseAnnouncements(announcements: Announcement[]) {
  const loadPublic = vi.fn().mockResolvedValue(undefined)
  mockedUseAnnouncements.mockReturnValue({
    announcements,
    loading: false,
    error: null,
    loadPublic,
    loadAll: vi.fn(),
    createAnnouncement: vi.fn(),
    updateAnnouncement: vi.fn(),
    deleteAnnouncement: vi.fn(),
  } as unknown as ReturnType<typeof useAnnouncements>)
  return loadPublic
}

describe('AnnouncementBanner', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('rendert keinen sichtbaren Bereich, wenn keine aktiven Ankündigungen vorhanden sind', () => {
    stubUseAnnouncements([])

    const wrapper = mount(AnnouncementBanner)

    expect(wrapper.find('section').exists()).toBe(false)
  })

  it('ruft loadPublic beim Mounten auf', () => {
    const loadPublic = stubUseAnnouncements([])

    mount(AnnouncementBanner)

    expect(loadPublic).toHaveBeenCalledTimes(1)
  })

  it('rendert eine Karte pro aktiver Ankündigung', () => {
    stubUseAnnouncements([withImage, withoutImage])

    const wrapper = mount(AnnouncementBanner)

    expect(wrapper.findAll('article')).toHaveLength(2)
    expect(wrapper.text()).toContain('Kursausfall am 20.07.')
    expect(wrapper.text()).toContain('Neue Öffnungszeiten')
  })

  it('rendert ein Bild, wenn imageUrl gesetzt ist', () => {
    stubUseAnnouncements([withImage])

    const wrapper = mount(AnnouncementBanner)

    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe(withImage.imageUrl)
    expect(img.attributes('alt')).toBe(withImage.title)
  })

  it('rendert kein Bild, wenn imageUrl null ist', () => {
    stubUseAnnouncements([withoutImage])

    const wrapper = mount(AnnouncementBanner)

    expect(wrapper.find('img').exists()).toBe(false)
  })

  it('rendert erlaubtes HTML aus body über v-html', () => {
    stubUseAnnouncements([withImage])

    const wrapper = mount(AnnouncementBanner)

    expect(wrapper.find('.announcement-body').html()).toContain('<strong>krankheitsbedingt</strong>')
  })

  it('entfernt <script>-Tags aus body (DOMPurify)', () => {
    const unsafe: Announcement = {
      ...withoutImage,
      id: 3,
      body: '<p>Text</p><script>alert(1)</script>',
    }
    stubUseAnnouncements([unsafe])

    const wrapper = mount(AnnouncementBanner)

    const html = wrapper.find('.announcement-body').html()
    expect(html).not.toContain('<script>')
    expect(html).toContain('Text')
  })

  it('entfernt nicht erlaubte Tags und Inline-Event-Attribute aus body (DOMPurify)', () => {
    const unsafe: Announcement = {
      ...withoutImage,
      id: 4,
      body: '<p onclick="alert(1)">Text</p><img src="x" onerror="alert(1)">',
    }
    stubUseAnnouncements([unsafe])

    const wrapper = mount(AnnouncementBanner)

    const html = wrapper.find('.announcement-body').html()
    expect(html).not.toContain('onclick')
    expect(html).not.toContain('onerror')
    expect(html).not.toContain('<img')
    expect(html).toContain('Text')
  })
})
