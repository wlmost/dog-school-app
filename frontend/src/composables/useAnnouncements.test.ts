import { describe, it, expect, vi, beforeEach } from 'vitest'
import { announcementsApi } from '@/api/announcements'
import type { Announcement } from '@/api/announcements'
import { useAnnouncements } from './useAnnouncements'

vi.mock('@/api/announcements', () => ({
  announcementsApi: {
    getPublic: vi.fn(),
    getAll: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn(),
  },
}))

const makeAnnouncement = (id: number): Announcement => ({
  id,
  title: `Ankündigung ${id}`,
  body: `<p>Text ${id}</p>`,
  imageUrl: null,
  displayDays: 7,
  expiresAt: '2099-01-01T00:00:00.000Z',
  isActive: true,
  createdAt: '2026-01-01T00:00:00.000Z',
  updatedAt: '2026-01-01T00:00:00.000Z',
})

describe('useAnnouncements', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('loadPublic()', () => {
    it('befüllt announcements bei Erfolg, setzt loading auf false und loadError auf null', async () => {
      const items = [makeAnnouncement(1)]
      vi.mocked(announcementsApi.getPublic).mockResolvedValue(items)

      const { announcements, loading, loadError, loadPublic } = useAnnouncements()

      const promise = loadPublic()
      expect(loading.value).toBe(true)
      await promise

      expect(announcements.value).toEqual(items)
      expect(loading.value).toBe(false)
      expect(loadError.value).toBeNull()
    })

    it('setzt loadError bei API-Fehler und belässt announcements leer', async () => {
      vi.mocked(announcementsApi.getPublic).mockRejectedValue(new Error('Verbindungsfehler'))

      const { announcements, loading, loadError, loadPublic } = useAnnouncements()
      await loadPublic()

      expect(loadError.value).toBe('Verbindungsfehler')
      expect(announcements.value).toEqual([])
      expect(loading.value).toBe(false)
    })

    it('setzt generische Fehlermeldung wenn kein Error-Objekt geworfen wird', async () => {
      vi.mocked(announcementsApi.getPublic).mockRejectedValue('Unbekannter Fehler')

      const { loadError, loadPublic } = useAnnouncements()
      await loadPublic()

      expect(loadError.value).toBe('Fehler beim Laden der Ankündigungen')
    })
  })

  describe('loadAll()', () => {
    it('befüllt announcements bei Erfolg (inkl. abgelaufener Einträge)', async () => {
      const items = [makeAnnouncement(1), { ...makeAnnouncement(2), isActive: false }]
      vi.mocked(announcementsApi.getAll).mockResolvedValue(items)

      const { announcements, loadAll } = useAnnouncements()
      await loadAll()

      expect(announcements.value).toEqual(items)
    })

    it('setzt loadError bei API-Fehler', async () => {
      vi.mocked(announcementsApi.getAll).mockRejectedValue(new Error('Serverfehler'))

      const { loadError, loadAll } = useAnnouncements()
      await loadAll()

      expect(loadError.value).toBe('Serverfehler')
    })
  })

  describe('createAnnouncement()', () => {
    it('ruft announcementsApi.create() mit den Formulardaten auf und hängt die neue Ankündigung an', async () => {
      const created = makeAnnouncement(10)
      vi.mocked(announcementsApi.create).mockResolvedValue(created)

      const { announcements, createAnnouncement } = useAnnouncements()
      const payload = { title: 'Neu', body: '<p>Neu</p>', displayDays: 14 }
      await createAnnouncement(payload)

      expect(announcementsApi.create).toHaveBeenCalledWith(payload)
      expect(announcements.value).toContainEqual(created)
    })

    it('setzt mutationError bei fehlgeschlagenem Erstellen und ändert announcements nicht', async () => {
      vi.mocked(announcementsApi.create).mockRejectedValue(new Error('Validierungsfehler'))

      const { announcements, mutationError, createAnnouncement } = useAnnouncements()
      await createAnnouncement({ title: '', body: '', displayDays: 400 })

      expect(mutationError.value).toBe('Validierungsfehler')
      expect(announcements.value).toEqual([])
    })
  })

  describe('updateAnnouncement()', () => {
    it('ruft announcementsApi.update() mit der ID auf und ersetzt die Ankündigung in der Liste', async () => {
      const original = makeAnnouncement(5)
      const updated = { ...original, title: 'Geänderter Titel' }
      vi.mocked(announcementsApi.getAll).mockResolvedValue([original])
      vi.mocked(announcementsApi.update).mockResolvedValue(updated)

      const { announcements, loadAll, updateAnnouncement } = useAnnouncements()
      await loadAll()
      await updateAnnouncement(5, { title: 'Geänderter Titel' })

      expect(announcementsApi.update).toHaveBeenCalledWith(5, { title: 'Geänderter Titel' })
      expect(announcements.value).toEqual([updated])
    })

    it('setzt mutationError bei fehlgeschlagenem Aktualisieren', async () => {
      vi.mocked(announcementsApi.update).mockRejectedValue(new Error('Konflikt'))

      const { mutationError, updateAnnouncement } = useAnnouncements()
      await updateAnnouncement(5, { title: 'x' })

      expect(mutationError.value).toBe('Konflikt')
    })
  })

  describe('deleteAnnouncement()', () => {
    it('ruft announcementsApi.delete() mit der ID auf und entfernt die Ankündigung aus der Liste', async () => {
      const items = [makeAnnouncement(1), makeAnnouncement(2)]
      vi.mocked(announcementsApi.getAll).mockResolvedValue(items)
      vi.mocked(announcementsApi.delete).mockResolvedValue(undefined)

      const { announcements, loadAll, deleteAnnouncement } = useAnnouncements()
      await loadAll()
      await deleteAnnouncement(1)

      expect(announcementsApi.delete).toHaveBeenCalledWith(1)
      expect(announcements.value).toHaveLength(1)
      expect(announcements.value[0]!.id).toBe(2)
    })

    it('setzt mutationError bei fehlgeschlagenem Löschen und belässt die Liste unverändert', async () => {
      const items = [makeAnnouncement(1)]
      vi.mocked(announcementsApi.getAll).mockResolvedValue(items)
      vi.mocked(announcementsApi.delete).mockRejectedValue(new Error('Löschen fehlgeschlagen'))

      const { announcements, mutationError, loadAll, deleteAnnouncement } = useAnnouncements()
      await loadAll()
      await deleteAnnouncement(1)

      expect(mutationError.value).toBe('Löschen fehlgeschlagen')
      expect(announcements.value).toEqual(items)
    })

    it('löscht eine Ankündigung erfolgreich auch wenn zuvor ein Lade-Fehler aufgetreten war, ohne dass ein stiller mutationError zurückbleibt', async () => {
      const items = [makeAnnouncement(1)]
      vi.mocked(announcementsApi.getAll)
        .mockRejectedValueOnce(new Error('Serverfehler'))
        .mockResolvedValueOnce(items)
      vi.mocked(announcementsApi.delete).mockResolvedValue(undefined)

      const { announcements, loadError, mutationError, loadAll, deleteAnnouncement } =
        useAnnouncements()
      await loadAll()
      expect(loadError.value).toBe('Serverfehler')

      await loadAll()
      await deleteAnnouncement(1)

      expect(mutationError.value).toBeNull()
      expect(announcements.value).toEqual([])
    })
  })
})
