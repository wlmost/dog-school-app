import apiClient from './client'

export interface Setting {
  key: string
  value: string | number | boolean | null
  type: string
  description: string | null
  group: string
}

export interface SettingsResponse {
  data: {
    company?: Setting[]
    email?: Setting[]
    general?: Setting[]
  }
  message?: string
}

/**
 * Settings API
 */
export const settingsApi = {
  /**
   * Get all settings
   */
  async getSettings() {
    const response = await apiClient.get<SettingsResponse>('/api/v1/settings')
    return response.data
  },

  /**
   * Update settings
   */
  async updateSettings(settings: Record<string, any>) {
    const formData = new FormData()

    Object.entries(settings).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        if (value instanceof File) {
          formData.append(key, value)
        } else {
          formData.append(key, String(value))
        }
      }
    })

    // set() (not append()) after the loop so a settings key named
    // `_method` can never shadow the override via PHP's last-wins
    // multipart field semantics.
    formData.set('_method', 'PUT')

    // Sent as POST with a Laravel method-override field (`_method=PUT`)
    // instead of a real HTTP PUT. PHP only populates $_POST/$_FILES
    // natively on a real POST request; parsing a multipart body on a
    // real PUT is PHP-version-/server-dependent and drops uploaded
    // files. Laravel's enableHttpMethodParameterOverride() rewrites the
    // method from the `_method` field before routing, so the route
    // stays `Route::put('/settings', ...)`.
    const response = await apiClient.post<SettingsResponse>('/api/v1/settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },
}
