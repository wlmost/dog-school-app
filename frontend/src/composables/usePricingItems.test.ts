import { describe, it, expect, vi, beforeEach } from 'vitest'
import { pricingItemsApi } from '@/api/pricingItems'
import type { PricingGroup, PricingItem } from '@/api/pricingItems'
import { usePricingItems } from './usePricingItems'

vi.mock('@/api/pricingItems', () => ({
  pricingItemsApi: {
    getPublic: vi.fn(),
    getAll: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn(),
  },
}))

const makePricingItem = (id: number): PricingItem => ({
  id,
  category: 'Einzeltraining',
  title: `Leistung ${id}`,
  price: '80.00',
  unit: null,
  description: null,
  isFromPrice: false,
  createdAt: null,
  updatedAt: null,
})

describe('usePricingItems', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('loadPublic()', () => {
    it('befüllt groups bei Erfolg, setzt loading auf false und error auf null', async () => {
      const mockGroups: PricingGroup[] = [
        { category: 'Einzeltraining', items: [makePricingItem(1)] },
      ]
      vi.mocked(pricingItemsApi.getPublic).mockResolvedValue(mockGroups)

      const { groups, loading, error, loadPublic } = usePricingItems()

      const promise = loadPublic()
      expect(loading.value).toBe(true)
      await promise

      expect(groups.value).toEqual(mockGroups)
      expect(loading.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('setzt error bei API-Fehler und belässt groups leer', async () => {
      vi.mocked(pricingItemsApi.getPublic).mockRejectedValue(new Error('Verbindungsfehler'))

      const { groups, loading, error, loadPublic } = usePricingItems()
      await loadPublic()

      expect(error.value).toBe('Verbindungsfehler')
      expect(groups.value).toEqual([])
      expect(loading.value).toBe(false)
    })

    it('setzt generische Fehlermeldung wenn kein Error-Objekt geworfen wird', async () => {
      vi.mocked(pricingItemsApi.getPublic).mockRejectedValue('Unbekannter Fehler')

      const { error, loadPublic } = usePricingItems()
      await loadPublic()

      expect(error.value).toBe('Fehler beim Laden der Preise')
    })
  })

  describe('loadAll()', () => {
    it('befüllt items bei Erfolg', async () => {
      const mockItems = [makePricingItem(1), makePricingItem(2)]
      vi.mocked(pricingItemsApi.getAll).mockResolvedValue(mockItems)

      const { items, loadAll } = usePricingItems()
      await loadAll()

      expect(items.value).toEqual(mockItems)
    })

    it('setzt loading korrekt zurück nach Erfolg', async () => {
      vi.mocked(pricingItemsApi.getAll).mockResolvedValue([])

      const { loading, loadAll } = usePricingItems()
      const promise = loadAll()
      expect(loading.value).toBe(true)
      await promise
      expect(loading.value).toBe(false)
    })
  })

  describe('createItem()', () => {
    it('ruft pricingItemsApi.create() mit korrekten Daten auf und hängt neues Item an items an', async () => {
      const created = makePricingItem(10)
      vi.mocked(pricingItemsApi.create).mockResolvedValue(created)

      const { items, createItem } = usePricingItems()
      const payload = {
        category: 'Einzeltraining',
        title: 'Leistung 10',
        price: '80.00',
        unit: null,
        description: null,
        isFromPrice: false,
      }
      await createItem(payload)

      expect(pricingItemsApi.create).toHaveBeenCalledWith(payload)
      expect(items.value).toContainEqual(created)
    })

    it('aktualisiert items direkt ohne loadAll() aufzurufen', async () => {
      const created = makePricingItem(10)
      vi.mocked(pricingItemsApi.create).mockResolvedValue(created)

      const { items, createItem } = usePricingItems()
      await createItem({
        category: 'Test',
        title: 'Test',
        price: '10.00',
        unit: null,
        description: null,
        isFromPrice: false,
      })

      expect(pricingItemsApi.getAll).not.toHaveBeenCalled()
      expect(items.value).toHaveLength(1)
    })
  })

  describe('deleteItem()', () => {
    it('ruft pricingItemsApi.delete() mit der übergebenen ID auf', async () => {
      vi.mocked(pricingItemsApi.delete).mockResolvedValue(undefined)

      const { deleteItem } = usePricingItems()
      await deleteItem(7)

      expect(pricingItemsApi.delete).toHaveBeenCalledWith(7)
    })

    it('entfernt das gelöschte Item aus items', async () => {
      const allItems = [makePricingItem(5), makePricingItem(6)]
      vi.mocked(pricingItemsApi.getAll).mockResolvedValue(allItems)
      vi.mocked(pricingItemsApi.delete).mockResolvedValue(undefined)

      const { items: stateItems, loadAll, deleteItem } = usePricingItems()
      await loadAll()
      expect(stateItems.value).toHaveLength(2)

      await deleteItem(5)

      expect(stateItems.value).toHaveLength(1)
      expect(stateItems.value[0]!.id).toBe(6)
    })
  })
})
