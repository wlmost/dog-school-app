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

    const response = await apiClient.put<SettingsResponse>('/api/v1/settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },
}
