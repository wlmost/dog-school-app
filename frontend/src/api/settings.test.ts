import { describe, it, expect, vi, beforeEach } from 'vitest'
import { settingsApi } from '@/api/settings'
import apiClient from '@/api/client'

vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
  },
}))

describe('settingsApi.updateSettings', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(apiClient.post).mockResolvedValue({ data: { data: {} } })
  })

  it('sends the request via apiClient.post, not apiClient.put', async () => {
    await settingsApi.updateSettings({ company_name: 'Test' })

    expect(apiClient.post).toHaveBeenCalledTimes(1)
    expect(apiClient.put).not.toHaveBeenCalled()
  })

  it('posts to the /api/v1/settings endpoint with multipart headers', async () => {
    await settingsApi.updateSettings({ company_name: 'Test' })

    const [url, , config] = vi.mocked(apiClient.post).mock.calls[0]!
    expect(url).toBe('/api/v1/settings')
    expect(config?.headers).toEqual({ 'Content-Type': 'multipart/form-data' })
  })

  it('appends a _method=PUT field to the FormData for method override', async () => {
    await settingsApi.updateSettings({ company_name: 'Test' })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect(formData).toBeInstanceOf(FormData)
    expect((formData as FormData).get('_method')).toBe('PUT')
  })

  it('carries text fields into the FormData unchanged', async () => {
    await settingsApi.updateSettings({ company_name: 'Hundeschule Musterstadt' })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect((formData as FormData).get('company_name')).toBe('Hundeschule Musterstadt')
  })

  it('carries a File value into the FormData unchanged', async () => {
    const logo = new File(['logo-bytes'], 'logo.png', { type: 'image/png' })

    await settingsApi.updateSettings({ company_logo: logo })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    const uploaded = (formData as FormData).get('company_logo')
    expect(uploaded).toBeInstanceOf(File)
    expect((uploaded as File).name).toBe('logo.png')
  })

  it('skips null and undefined values', async () => {
    await settingsApi.updateSettings({ company_name: 'Test', unset_field: null, missing_field: undefined })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect((formData as FormData).has('unset_field')).toBe(false)
    expect((formData as FormData).has('missing_field')).toBe(false)
  })

  it('still sends only the _method field when called with an empty settings object', async () => {
    await settingsApi.updateSettings({})

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect((formData as FormData).get('_method')).toBe('PUT')
    expect(Array.from((formData as FormData).keys())).toEqual(['_method'])
  })

  it('carries multiple File values (logo and favicon) into the FormData at once', async () => {
    const logo = new File(['logo-bytes'], 'logo.png', { type: 'image/png' })
    const favicon = new File(['favicon-bytes'], 'favicon.ico', { type: 'image/vnd.microsoft.icon' })

    await settingsApi.updateSettings({ company_logo: logo, company_favicon: favicon })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    const uploadedLogo = (formData as FormData).get('company_logo')
    const uploadedFavicon = (formData as FormData).get('company_favicon')
    expect(uploadedLogo).toBeInstanceOf(File)
    expect((uploadedLogo as File).name).toBe('logo.png')
    expect(uploadedFavicon).toBeInstanceOf(File)
    expect((uploadedFavicon as File).name).toBe('favicon.ico')
  })

  it('coerces non-string primitive values (number, boolean) to strings in the FormData', async () => {
    await settingsApi.updateSettings({ max_dogs: 5, is_active: true })

    const [, formData] = vi.mocked(apiClient.post).mock.calls[0]!
    expect((formData as FormData).get('max_dogs')).toBe('5')
    expect((formData as FormData).get('is_active')).toBe('true')
  })

  it('resolves with the response data returned by apiClient.post', async () => {
    const payload = { data: { general: [] }, message: 'Settings updated' }
    vi.mocked(apiClient.post).mockResolvedValue({ data: payload })

    const result = await settingsApi.updateSettings({ company_name: 'Test' })

    expect(result).toEqual(payload)
  })
})
