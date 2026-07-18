import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'

// Hinweis: Dieser Test prüft die Vue-Computed-Logik hinter dem
// Seitenhintergrund, NICHT das gerenderte `style`-Attribut im DOM.
// happy-dom (die in diesem Projekt konfigurierte Test-Umgebung, siehe
// frontend/vitest.config.ts) normalisiert das `background`-Shorthand beim
// Serialisieren und verliert dabei den `linear-gradient(...)`-Layer
// (verifiziert per Spike: `style="background: ..."` liefert nach dem
// Setzen von `background: 'linear-gradient(...), url(...)'` nur noch
// `background: url("...") center center / cover fixed` zurück, die
// rgba-Werte fehlen). Ein DOM-Attribut-Assert wäre daher unabhängig vom
// Produktivcode immer fehlgeschlagen (Environment-Limitation, kein Bug).
// Die Prüfung gegen `wrapper.vm.backgroundStyle` testet stattdessen direkt
// die Komponenten-Logik, die den in `task-T01.notes.md` beschriebenen
// Bugfix ausmacht (computed reagiert jetzt auf `themeStore.isDark`).

vi.mock('vue-router', () => ({
  useRoute: vi.fn(() => ({ meta: {} })),
  useRouter: vi.fn(() => ({ push: vi.fn() })),
  RouterLink: { template: '<a><slot /></a>' },
  RouterView: { template: '<div />' },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(),
}))

vi.mock('@/stores/theme', () => ({
  useThemeStore: vi.fn(),
}))

function mockAuth(): void {
  vi.mocked(useAuthStore).mockReturnValue({
    user: {
      id: 1,
      first_name: 'Ada',
      last_name: 'Lovelace',
      role: 'admin',
      full_name: 'Ada Lovelace',
    },
    logout: vi.fn(),
  } as any)
}

function mockTheme(isDark: boolean): void {
  vi.mocked(useThemeStore).mockReturnValue({
    isDark,
    toggleTheme: vi.fn(),
  } as any)
}

function mountLayout() {
  return mount(DefaultLayout)
}

describe('DefaultLayout', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuth()
  })

  // ------------------------------------------------------------------ //
  // Regressionstest für den in task-T01 behobenen Layout-Bug:            //
  // backgroundStyle reagierte vorher nicht auf themeStore.isDark         //
  // ------------------------------------------------------------------ //
  describe('Seitenhintergrund reagiert auf den Theme-Zustand', () => {
    it('setzt im Light-Mode das ursprüngliche helle Overlay', () => {
      mockTheme(false)

      const wrapper = mountLayout()
      const background = (wrapper.vm as any).backgroundStyle.background as string

      expect(background).toContain('rgba(255, 255, 255, 0.7)')
      expect(background).toContain('rgba(255, 255, 255, 0.8)')
    })

    it('setzt im Dark-Mode ein dunkles Overlay statt des hellen Overlays', () => {
      mockTheme(true)

      const wrapper = mountLayout()
      const background = (wrapper.vm as any).backgroundStyle.background as string

      expect(background).toContain('rgba(17, 24, 39, 0.75)')
      expect(background).toContain('rgba(17, 24, 39, 0.85)')
      expect(background).not.toContain('rgba(255, 255, 255')
    })

    it('behält das Hintergrundbild in beiden Modi bei (kein Entfernen des Bildes durch den Fix)', () => {
      mockTheme(true)
      const darkWrapper = mountLayout()
      const darkBackground = (darkWrapper.vm as any).backgroundStyle.background as string

      mockTheme(false)
      const lightWrapper = mountLayout()
      const lightBackground = (lightWrapper.vm as any).backgroundStyle.background as string

      expect(darkBackground).toMatch(/url\(.*pet-01-1280x664\.jpg\)/)
      expect(lightBackground).toMatch(/url\(.*pet-01-1280x664\.jpg\)/)
    })
  })
})
