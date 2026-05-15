import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import { nextTick } from 'vue'
import CourseRecurrenceForm from '@/components/CourseRecurrenceForm.vue'
import type { RecurrenceRule } from '@/components/CourseRecurrenceForm.vue'

const baseRule: RecurrenceRule = {
  type: 'weekly',
  weekday: 1,
  startDate: '2025-03-03',
  startTime: '10:00',
  endTime: '11:00',
  count: 8,
  location: null,
  maxParticipants: null,
}

describe('CourseRecurrenceForm', () => {
  describe('type = weekly', () => {
    it('zeigt den Wochentag-Selector an', () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: { ...baseRule, type: 'weekly' } },
      })

      expect(wrapper.find('#rr-weekday').exists()).toBe(true)
      expect(wrapper.find('#rr-day-of-month').exists()).toBe(false)
    })

    it('emittiert ein Objekt mit weekday, aber ohne dayOfMonth', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: { ...baseRule, type: 'weekly', weekday: 2 } },
      })

      // Typwechsel triggert einen Emit; wir warten einen Tick
      await nextTick()
      await flushPromises()

      const emitted = wrapper.emitted<RecurrenceRule[]>('update:modelValue')
      // Nimm das letzte emittierte Event
      const lastEmit = emitted?.[emitted.length - 1]?.[0]

      expect(lastEmit).toBeDefined()
      expect(lastEmit).toHaveProperty('weekday')
      expect(lastEmit).not.toHaveProperty('dayOfMonth')
      expect(lastEmit?.type).toBe('weekly')
    })

    it('emittiert weekday nach Änderung des Wochentag-Selectors', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: { ...baseRule, type: 'weekly', weekday: 1 } },
      })

      const select = wrapper.find<HTMLSelectElement>('#rr-weekday')
      await select.setValue('3') // Mittwoch
      await nextTick()
      await flushPromises()

      const emitted = wrapper.emitted<RecurrenceRule[]>('update:modelValue')
      const lastEmit = emitted?.[emitted.length - 1]?.[0]

      expect(lastEmit?.weekday).toBe(3)
      expect(lastEmit).not.toHaveProperty('dayOfMonth')
    })
  })

  describe('type = monthly', () => {
    const monthlyRule: RecurrenceRule = {
      type: 'monthly',
      dayOfMonth: 15,
      startDate: '2025-03-15',
      startTime: '10:00',
      endTime: '11:00',
      count: 6,
      location: null,
      maxParticipants: null,
    }

    it('zeigt den Monatstag-Input an', () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: monthlyRule },
      })

      expect(wrapper.find('#rr-day-of-month').exists()).toBe(true)
      expect(wrapper.find('#rr-weekday').exists()).toBe(false)
    })

    it('emittiert ein Objekt mit dayOfMonth, aber ohne weekday', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: monthlyRule },
      })

      await nextTick()
      await flushPromises()

      const emitted = wrapper.emitted<RecurrenceRule[]>('update:modelValue')
      const lastEmit = emitted?.[emitted.length - 1]?.[0]

      expect(lastEmit).toBeDefined()
      expect(lastEmit).toHaveProperty('dayOfMonth')
      expect(lastEmit).not.toHaveProperty('weekday')
      expect(lastEmit?.type).toBe('monthly')
    })

    it('emittiert den aktualisierten dayOfMonth', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: monthlyRule },
      })

      const input = wrapper.find<HTMLInputElement>('#rr-day-of-month')
      await input.setValue('20')
      await nextTick()
      await flushPromises()

      const emitted = wrapper.emitted<RecurrenceRule[]>('update:modelValue')
      const lastEmit = emitted?.[emitted.length - 1]?.[0]

      expect(lastEmit?.dayOfMonth).toBe(20)
      expect(lastEmit).not.toHaveProperty('weekday')
    })
  })

  describe('Vorschau-Text', () => {
    it('enthält den Wochentagnamen bei type = weekly', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: {
          modelValue: { ...baseRule, type: 'weekly', weekday: 1 },
        },
      })

      await nextTick()

      const preview = wrapper.find('[class*="bg-blue-50"]')
      expect(preview.exists()).toBe(true)
      expect(preview.text()).toContain('Montag')
    })

    it('enthält den korrekten Wochentagnamen für Mittwoch (weekday=3)', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: {
          modelValue: { ...baseRule, type: 'weekly', weekday: 3 },
        },
      })

      await nextTick()

      const preview = wrapper.find('[class*="bg-blue-50"]')
      expect(preview.text()).toContain('Mittwoch')
    })

    it('enthält das formatierte Datum', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: {
          modelValue: { ...baseRule, startDate: '2025-03-03' },
        },
      })

      await nextTick()

      const preview = wrapper.find('[class*="bg-blue-50"]')
      expect(preview.text()).toContain('03.03.2025')
    })

    it('enthält Startzeit und Endzeit', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: {
          modelValue: { ...baseRule, startTime: '10:00', endTime: '11:00' },
        },
      })

      await nextTick()

      const preview = wrapper.find('[class*="bg-blue-50"]')
      expect(preview.text()).toContain('10:00')
      expect(preview.text()).toContain('11:00')
    })

    it('zeigt keinen Vorschau-Text wenn startDate fehlt', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: {
          modelValue: { ...baseRule, startDate: '' },
        },
      })

      await nextTick()

      expect(wrapper.find('[class*="bg-blue-50"]').exists()).toBe(false)
    })

    it('enthält den Tag des Monats bei type = monthly', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: {
          modelValue: {
            type: 'monthly',
            dayOfMonth: 15,
            startDate: '2025-03-15',
            startTime: '10:00',
            endTime: '11:00',
            count: 6,
            location: null,
            maxParticipants: null,
          },
        },
      })

      await nextTick()

      const preview = wrapper.find('[class*="bg-blue-50"]')
      expect(preview.text()).toContain('15.')
    })
  })

  describe('Typ-Wechsel', () => {
    it('wechselt von weekly zu monthly und zeigt Monatstag-Input', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: { ...baseRule, type: 'weekly' } },
      })

      expect(wrapper.find('#rr-weekday').exists()).toBe(true)
      expect(wrapper.find('#rr-day-of-month').exists()).toBe(false)

      await wrapper.find<HTMLSelectElement>('#rr-type').setValue('monthly')
      await nextTick()

      expect(wrapper.find('#rr-weekday').exists()).toBe(false)
      expect(wrapper.find('#rr-day-of-month').exists()).toBe(true)
    })
  })

  describe('API-Schema-Konformität', () => {
    it('emittiertes Objekt enthält alle Pflichtfelder', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: baseRule },
      })

      await nextTick()
      await flushPromises()

      const emitted = wrapper.emitted<RecurrenceRule[]>('update:modelValue')
      const lastEmit = emitted?.[emitted.length - 1]?.[0]

      expect(lastEmit).toMatchObject({
        type: expect.any(String),
        startTime: expect.any(String),
        endTime: expect.any(String),
        startDate: expect.any(String),
        count: expect.any(Number),
      })
    })

    it('location wird als null emittiert wenn leer', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: { ...baseRule, location: null } },
      })

      await nextTick()
      await flushPromises()

      const emitted = wrapper.emitted<RecurrenceRule[]>('update:modelValue')
      const lastEmit = emitted?.[emitted.length - 1]?.[0]

      expect(lastEmit?.location).toBeNull()
    })

    it('maxParticipants wird als null emittiert wenn leer', async () => {
      const wrapper = mount(CourseRecurrenceForm, {
        props: { modelValue: { ...baseRule, maxParticipants: null } },
      })

      await nextTick()
      await flushPromises()

      const emitted = wrapper.emitted<RecurrenceRule[]>('update:modelValue')
      const lastEmit = emitted?.[emitted.length - 1]?.[0]

      expect(lastEmit?.maxParticipants).toBeNull()
    })
  })
})
