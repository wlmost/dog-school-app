import apiClient from './client'

export interface Announcement {
  id: number
  title: string
  body: string
  imageUrl: string | null
  displayDays: number
  expiresAt: string | null
  isActive: boolean
  createdAt: string | null
  updatedAt: string | null
}

export interface AnnouncementFormData {
  title: string
  body: string
  displayDays: number
  image?: File | null
}

/**
 * Builds a multipart FormData payload from partial announcement form data.
 * Only defined keys are appended so `update()` can send partial updates
 * without overwriting untouched fields with empty strings.
 */
function buildFormData(data: Partial<AnnouncementFormData>): FormData {
  const formData = new FormData()
  if (data.title !== undefined) formData.append('title', data.title)
  if (data.body !== undefined) formData.append('body', data.body)
  if (data.displayDays !== undefined) formData.append('displayDays', String(data.displayDays))
  if (data.image) formData.append('image', data.image)
  return formData
}

/**
 * Announcements API
 */
export const announcementsApi = {
  /**
   * Get all currently active announcements (public, no auth required)
   */
  async getPublic(): Promise<Announcement[]> {
    const response = await apiClient.get<{ data: Announcement[] }>('/api/v1/announcements')
    return response.data.data
  },

  /**
   * Get all announcements including expired ones (admin only)
   */
  async getAll(): Promise<Announcement[]> {
    const response = await apiClient.get<{ data: Announcement[] }>('/api/v1/admin/announcements')
    return response.data.data
  },

  /**
   * Create a new announcement (admin only). Sent as multipart/form-data
   * because an optional image upload may be included.
   */
  async create(data: AnnouncementFormData): Promise<Announcement> {
    const response = await apiClient.post<{ data: Announcement }>(
      '/api/v1/admin/announcements',
      buildFormData(data),
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      },
    )
    return response.data.data
  },

  /**
   * Update an existing announcement (admin only).
   *
   * Sent as POST with a Laravel method-override field (`_method=PUT`)
   * instead of a real HTTP PUT. PHP only populates $_POST/$_FILES
   * natively on a real POST request; parsing a multipart body on a
   * real PUT is PHP-version-/server-dependent and drops uploaded
   * files. Laravel's enableHttpMethodParameterOverride() rewrites the
   * method from the `_method` field before routing, so the route
   * stays `Route::put('/admin/announcements/{announcement}', ...)`.
   */
  async update(id: number, data: Partial<AnnouncementFormData>): Promise<Announcement> {
    const formData = buildFormData(data)

    // set() (not append()) so the override field cannot be shadowed by
    // any future form field also named `_method`.
    formData.set('_method', 'PUT')

    const response = await apiClient.post<{ data: Announcement }>(
      `/api/v1/admin/announcements/${id}`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      },
    )
    return response.data.data
  },

  /**
   * Delete an announcement (admin only)
   */
  async delete(id: number): Promise<void> {
    await apiClient.delete(`/api/v1/admin/announcements/${id}`)
  },
}
