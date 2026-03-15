import apiClient from './client'

export interface TrainingAttachment {
  id: number
  trainingLogId: number
  fileType: 'image' | 'video' | 'document'
  filePath: string
  fileName: string
  uploadedAt: string
  downloadUrl: string
  createdAt: string
  updatedAt: string
}

export interface UploadAttachmentData {
  trainingLogId: number
  file: File
}

export interface AttachmentsFilters {
  trainingLogId?: number
  fileType?: 'image' | 'video' | 'document'
  perPage?: number
  page?: number
}

class TrainingAttachmentsApi {
  /**
   * Get all attachments with optional filters
   */
  async getAttachments(filters?: AttachmentsFilters) {
    const response = await apiClient.get('/api/v1/training-attachments', {
      params: filters
    })
    return response.data
  }

  /**
   * Get a single attachment
   */
  async getAttachment(id: number): Promise<TrainingAttachment> {
    const response = await apiClient.get(`/api/v1/training-attachments/${id}`)
    return response.data.data
  }

  /**
   * Upload a new attachment
   */
  async uploadAttachment(data: UploadAttachmentData): Promise<TrainingAttachment> {
    const formData = new FormData()
    formData.append('trainingLogId', data.trainingLogId.toString())
    formData.append('file', data.file)

    const response = await apiClient.post('/api/v1/training-attachments', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    return response.data.data
  }

  /**
   * Delete an attachment
   */
  async deleteAttachment(id: number): Promise<void> {
    await apiClient.delete(`/api/v1/training-attachments/${id}`)
  }

  /**
   * Get download URL for an attachment
   */
  getDownloadUrl(id: number): string {
    return `${apiClient.defaults.baseURL}/api/v1/training-attachments/${id}/download`
  }

  /**
   * Get public URL for displaying an attachment
   */
  getPublicUrl(filePath: string): string {
    return `${apiClient.defaults.baseURL}/storage/${filePath}`
  }
}

export const trainingAttachmentsApi = new TrainingAttachmentsApi()
