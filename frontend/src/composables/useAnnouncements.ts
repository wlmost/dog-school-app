import { ref } from 'vue'
import {
  announcementsApi,
  type Announcement,
  type AnnouncementFormData,
} from '@/api/announcements'

export function useAnnouncements() {
  const announcements = ref<Announcement[]>([])
  const loading = ref(false)
  // Separate error refs for load vs. mutation operations (mirrors
  // SettingsView.vue's loadError/saveError split, frontend/src/views/SettingsView.vue:565-566).
  // A single shared error ref would make a stale mutation failure (e.g. a
  // failed delete) hide the entire announcement list in the template, since
  // the list rendering is gated by "no error" — even though the list itself
  // is still fully loaded and valid.
  const loadError = ref<string | null>(null)
  const mutationError = ref<string | null>(null)

  async function loadPublic(): Promise<void> {
    loading.value = true
    loadError.value = null
    try {
      announcements.value = await announcementsApi.getPublic()
    } catch (e) {
      loadError.value = e instanceof Error ? e.message : 'Fehler beim Laden der Ankündigungen'
    } finally {
      loading.value = false
    }
  }

  async function loadAll(): Promise<void> {
    loading.value = true
    loadError.value = null
    try {
      announcements.value = await announcementsApi.getAll()
    } catch (e) {
      loadError.value = e instanceof Error ? e.message : 'Fehler beim Laden der Ankündigungen'
    } finally {
      loading.value = false
    }
  }

  async function createAnnouncement(data: AnnouncementFormData): Promise<void> {
    loading.value = true
    mutationError.value = null
    try {
      const created = await announcementsApi.create(data)
      announcements.value = [...announcements.value, created]
    } catch (e) {
      mutationError.value = e instanceof Error ? e.message : 'Fehler beim Erstellen der Ankündigung'
    } finally {
      loading.value = false
    }
  }

  async function updateAnnouncement(
    id: number,
    data: Partial<AnnouncementFormData>,
  ): Promise<void> {
    loading.value = true
    mutationError.value = null
    try {
      const updated = await announcementsApi.update(id, data)
      announcements.value = announcements.value.map((announcement) =>
        announcement.id === id ? updated : announcement,
      )
    } catch (e) {
      mutationError.value = e instanceof Error ? e.message : 'Fehler beim Aktualisieren der Ankündigung'
    } finally {
      loading.value = false
    }
  }

  async function deleteAnnouncement(id: number): Promise<void> {
    loading.value = true
    mutationError.value = null
    try {
      await announcementsApi.delete(id)
      announcements.value = announcements.value.filter((announcement) => announcement.id !== id)
    } catch (e) {
      mutationError.value = e instanceof Error ? e.message : 'Fehler beim Löschen der Ankündigung'
    } finally {
      loading.value = false
    }
  }

  return {
    announcements,
    loading,
    loadError,
    mutationError,
    loadPublic,
    loadAll,
    createAnnouncement,
    updateAnnouncement,
    deleteAnnouncement,
  }
}
