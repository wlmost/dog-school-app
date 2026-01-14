import apiClient from './client'

export interface AnamnesisTemplate {
  id: number
  trainerId: number | null
  name: string
  description: string | null
  isDefault: boolean
  questionsCount?: number
  createdAt: string
  updatedAt: string
}

export interface AnamnesisQuestion {
  id: number
  templateId: number
  questionText: string
  questionType: 'text' | 'textarea' | 'radio' | 'select' | 'checkbox'
  isRequired: boolean
  order: number
  options: string[] | null
  helpText: string | null
}

export interface AnamnesisResponse {
  id: number
  dogId: number
  dogName?: string
  customerId?: number
  customerName?: string
  templateId: number
  templateName?: string
  completedAt: string | null
  completedBy: number | null
  completedByName?: string | null
  createdAt: string
  updatedAt: string
  answers?: AnamnesisAnswer[]
}

export interface AnamnesisAnswer {
  id: number
  responseId: number
  questionId: number
  questionText?: string
  answerValue: string
}

export interface CreateAnamnesisResponse {
  dogId: number
  templateId: number
  answers?: {
    questionId: number
    answerValue: string
  }[]
}

export interface UpdateAnamnesisResponse {
  answers: {
    questionId: number
    answerValue: string
  }[]
}

// Anamnesis Templates API
export const anamnesisTemplatesApi = {
  async getAll(params?: {
    trainerId?: number
    isDefault?: boolean
    search?: string
    perPage?: number
    page?: number
  }) {
    const response = await apiClient.get<{ data: AnamnesisTemplate[] }>('/api/v1/anamnesis-templates', { params })
    return response.data
  },

  async getById(id: number) {
    const response = await apiClient.get<{ data: AnamnesisTemplate }>(`/api/v1/anamnesis-templates/${id}`)
    return response.data.data
  },

  async getQuestions(templateId: number) {
    const response = await apiClient.get<{ data: AnamnesisQuestion[] }>(`/api/v1/anamnesis-templates/${templateId}/questions`)
    return response.data
  },

  async create(data: Omit<AnamnesisTemplate, 'id' | 'createdAt' | 'updatedAt'> & { questions?: Omit<AnamnesisQuestion, 'id' | 'templateId'>[] }) {
    const response = await apiClient.post<{ data: AnamnesisTemplate }>('/api/v1/anamnesis-templates', data)
    return response.data.data
  },

  async update(id: number, data: Partial<AnamnesisTemplate>) {
    const response = await apiClient.put<{ data: AnamnesisTemplate }>(`/api/v1/anamnesis-templates/${id}`, data)
    return response.data.data
  },

  async delete(id: number) {
    await apiClient.delete(`/api/v1/anamnesis-templates/${id}`)
  }
}

// Anamnesis Responses API
export const anamnesisResponsesApi = {
  async getAll(params?: {
    dogId?: number
    templateId?: number
    customerId?: number
    completed?: boolean
    perPage?: number
    page?: number
  }) {
    const response = await apiClient.get<{ data: AnamnesisResponse[] }>('/api/v1/anamnesis-responses', { params })
    return response.data
  },

  async getById(id: number) {
    const response = await apiClient.get<{ data: AnamnesisResponse }>(`/api/v1/anamnesis-responses/${id}`)
    return response.data.data
  },

  async create(data: CreateAnamnesisResponse) {
    const response = await apiClient.post<{ data: AnamnesisResponse }>('/api/v1/anamnesis-responses', data)
    return response.data.data
  },

  async update(id: number, data: UpdateAnamnesisResponse) {
    const response = await apiClient.put<{ data: AnamnesisResponse }>(`/api/v1/anamnesis-responses/${id}`, data)
    return response.data.data
  },

  async complete(id: number) {
    const response = await apiClient.post<{ data: AnamnesisResponse }>(`/api/v1/anamnesis-responses/${id}/complete`)
    return response.data.data
  },

  async delete(id: number) {
    await apiClient.delete(`/api/v1/anamnesis-responses/${id}`)
  },

  async downloadPdf(id: number) {
    const response = await apiClient.get(`/api/v1/anamnesis-responses/${id}/pdf`, {
      responseType: 'blob'
    })
    return response.data
  }
}
