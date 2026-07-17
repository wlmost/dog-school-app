import { describe, it, expect, vi, beforeEach } from 'vitest'
import { announcementsApi, type Announcement } from '@/api/announcements'
import apiClient from '@/api/client'

vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

const sampleAnnouncement: Announcement = {
  id: 3,
  title: 'Kursausfall am 20.07.',
  body: '<p>Der Welpenkurs am 20.07. entfällt <strong>krankheitsbedingt</strong>.</p>',
  imageUrl: 'https://example.test/storage/announcement-images/xyz.jpg',
  displayDays: 7,
  expiresAt: '2026-07-24T10:00:00.000000Z',
  isActive: true,
  createdAt: '2026-07-17T10:00:00.000000Z',
  updatedAt: '2026-07-17T10:00:00.000000Z',
}

describe('announcementsApi.getPublic', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches the public endpoint and unwraps the data envelope', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [sampleAnnouncement] } })

    const result = await announcementsApi.getPublic()

    expect(apiClient.get).toHaveBeenCalledWith('/api/v1/announcements')
    expect(result).toEqual([sampleAnnouncement])
  })
})

describe('announcementsApi.getAll', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches the admin endpoint and unwraps the data envelope', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [sampleAnnouncement] } })

    const result = await announcementsApi.getAll()

    expect(apiClient.get).toHaveBeenCalledWith('/api/v1/admin/announcements')
    expect(result).toEqual([sampleAnnouncement])
  })
})

describe('announcementsApi.create', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(apiClient.post).mockResolvedValue({ data: { data: sampleAnnouncement } })
  })

  it('sends the request via apiClient.post to the admin endpoint', async () => {
    await announcementsApi.create({ title: 'Test', body: '<p>Test</p>', displayDays: 7 })

    expect(apiClient.post).toHaveBeenCalledTimes(1)
    const [url] = vi.mocked(apiClient.post).mock.calls[0]!
    expect(url).toBe('/api/v1/admin/announcements')
  })

  it('sends the request with explicit multipart/form-data headers', async () => {
    await announcementsApi.create({ title: 'Test', body: '<p>Test</p>', displayDays: 7 })

    const [, , config] = vi.mocked(apiClient.post).mock.calls[0]!
    expect(config?.headers).toEqual({ 'Content-Type': 'multipart/form-data' })
  })

  it('sends a FormData body with the form fields', async () => {
    await announcementsApi.create({ title: 'Test-Titel', body: '<p>Test</p>', displayDays: 14 })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect(formData).toBeInstanceOf(FormData)
    expect((formData as FormData).get('title')).toBe('Test-Titel')
    expect((formData as FormData).get('body')).toBe('<p>Test</p>')
    expect((formData as FormData).get('displayDays')).toBe('14')
  })

  it('does not append a _method override field', async () => {
    await announcementsApi.create({ title: 'Test', body: '<p>Test</p>', displayDays: 7 })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect((formData as FormData).has('_method')).toBe(false)
  })

  it('appends an image File when provided', async () => {
    const image = new File(['image-bytes'], 'banner.png', { type: 'image/png' })

    await announcementsApi.create({
      title: 'Test',
      body: '<p>Test</p>',
      displayDays: 7,
      image,
    })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    const uploaded = (formData as FormData).get('image')
    expect(uploaded).toBeInstanceOf(File)
    expect((uploaded as File).name).toBe('banner.png')
  })

  it('omits the image field when no image is provided', async () => {
    await announcementsApi.create({ title: 'Test', body: '<p>Test</p>', displayDays: 7 })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect((formData as FormData).has('image')).toBe(false)
  })

  it('resolves with the created announcement', async () => {
    const result = await announcementsApi.create({
      title: 'Test',
      body: '<p>Test</p>',
      displayDays: 7,
    })

    expect(result).toEqual(sampleAnnouncement)
  })
})

describe('announcementsApi.update', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(apiClient.post).mockResolvedValue({ data: { data: sampleAnnouncement } })
  })

  it('sends the request via apiClient.post, not apiClient.put', async () => {
    await announcementsApi.update(3, { title: 'Neuer Titel' })

    expect(apiClient.post).toHaveBeenCalledTimes(1)
    expect(apiClient.put).not.toHaveBeenCalled()
  })

  it('posts to the admin endpoint with the announcement id', async () => {
    await announcementsApi.update(3, { title: 'Neuer Titel' })

    const [url] = vi.mocked(apiClient.post).mock.calls[0]!
    expect(url).toBe('/api/v1/admin/announcements/3')
  })

  it('sends the request with explicit multipart/form-data headers', async () => {
    await announcementsApi.update(3, { title: 'Neuer Titel' })

    const [, , config] = vi.mocked(apiClient.post).mock.calls[0]!
    expect(config?.headers).toEqual({ 'Content-Type': 'multipart/form-data' })
  })

  it('appends a _method=PUT field to the FormData for method override', async () => {
    await announcementsApi.update(3, { title: 'Neuer Titel' })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect(formData).toBeInstanceOf(FormData)
    expect((formData as FormData).get('_method')).toBe('PUT')
  })

  it('sends only the changed fields for a partial update', async () => {
    await announcementsApi.update(3, { displayDays: 21 })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect((formData as FormData).get('displayDays')).toBe('21')
    expect((formData as FormData).has('title')).toBe(false)
    expect((formData as FormData).has('body')).toBe(false)
  })

  it('appends a replacement image File when provided', async () => {
    const image = new File(['new-image-bytes'], 'new-banner.png', { type: 'image/png' })

    await announcementsApi.update(3, { image })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    const uploaded = (formData as FormData).get('image')
    expect(uploaded).toBeInstanceOf(File)
    expect((uploaded as File).name).toBe('new-banner.png')
  })

  it('resolves with the updated announcement', async () => {
    const result = await announcementsApi.update(3, { title: 'Neuer Titel' })

    expect(result).toEqual(sampleAnnouncement)
  })
})

describe('announcementsApi.delete', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(apiClient.delete).mockResolvedValue({ data: null })
  })

  it('sends a DELETE request to the admin endpoint with the announcement id', async () => {
    await announcementsApi.delete(3)

    expect(apiClient.delete).toHaveBeenCalledWith('/api/v1/admin/announcements/3')
  })
})
