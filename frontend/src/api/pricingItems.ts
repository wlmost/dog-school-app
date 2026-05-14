import apiClient from './client'

export interface PricingItem {
  id: number
  category: string
  title: string
  price: string
  unit: string | null
  description: string | null
  isFromPrice: boolean
  createdAt: string | null
  updatedAt: string | null
}

export interface PricingGroup {
  category: string
  items: PricingItem[]
}

export const pricingItemsApi = {
  async getPublic(): Promise<PricingGroup[]> {
    const response = await apiClient.get<{ data: PricingGroup[] }>('/api/v1/pricing-items')
    return response.data.data
  },

  async getAll(): Promise<PricingItem[]> {
    const response = await apiClient.get<{ data: PricingItem[] }>('/api/v1/admin/pricing-items')
    return response.data.data
  },

  async create(
    data: Omit<PricingItem, 'id' | 'createdAt' | 'updatedAt'>,
  ): Promise<PricingItem> {
    const response = await apiClient.post<{ data: PricingItem }>('/api/v1/admin/pricing-items', data)
    return response.data.data
  },

  async update(
    id: number,
    data: Partial<Omit<PricingItem, 'id' | 'createdAt' | 'updatedAt'>>,
  ): Promise<PricingItem> {
    const response = await apiClient.put<{ data: PricingItem }>(
      `/api/v1/admin/pricing-items/${id}`,
      data,
    )
    return response.data.data
  },

  async delete(id: number): Promise<void> {
    await apiClient.delete(`/api/v1/admin/pricing-items/${id}`)
  },
}
