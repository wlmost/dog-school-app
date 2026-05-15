<script setup lang="ts">
import { reactive, computed, watch } from 'vue'

export interface RecurrenceRule {
  type: 'weekly' | 'monthly'
  weekday?: number // 0–6 (0=Sonntag, 1=Montag, …, 6=Samstag), nur bei type='weekly'
  dayOfMonth?: number // 1–28, nur bei type='monthly'
  startTime: string // 'HH:MM'
  endTime: string // 'HH:MM'
  startDate: string // 'YYYY-MM-DD'
  count: number // 1–52
  location?: string | null
  maxParticipants?: number | null
}

const props = defineProps<{ modelValue: RecurrenceRule | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: RecurrenceRule] }>()

// 0=Sonntag, 1=Montag, …, 6=Samstag
const WEEKDAY_NAMES: ReadonlyArray<string> = [
  'Sonntag',
  'Montag',
  'Dienstag',
  'Mittwoch',
  'Donnerstag',
  'Freitag',
  'Samstag',
]

const WEEKDAY_OPTIONS = [
  { value: 1, label: 'Montag' },
  { value: 2, label: 'Dienstag' },
  { value: 3, label: 'Mittwoch' },
  { value: 4, label: 'Donnerstag' },
  { value: 5, label: 'Freitag' },
  { value: 6, label: 'Samstag' },
  { value: 0, label: 'Sonntag' },
] as const

interface FormState {
  type: 'weekly' | 'monthly'
  weekday: number
  dayOfMonth: number
  startTime: string
  endTime: string
  startDate: string
  count: number
  location: string // leer = null im emittierten Objekt
  maxParticipants: string // leer = null im emittierten Objekt
}

function buildFormState(source: RecurrenceRule | null): FormState {
  return {
    type: source?.type ?? 'weekly',
    weekday: source?.weekday ?? 1,
    dayOfMonth: source?.dayOfMonth ?? 1,
    startTime: source?.startTime ?? '',
    endTime: source?.endTime ?? '',
    startDate: source?.startDate ?? '',
    count: source?.count ?? 1,
    location: source?.location ?? '',
    maxParticipants: source?.maxParticipants != null ? String(source.maxParticipants) : '',
  }
}

const state = reactive<FormState>(buildFormState(props.modelValue))

watch(
  () => props.modelValue,
  (newValue) => {
    Object.assign(state, buildFormState(newValue))
  },
)

const currentValue = computed<RecurrenceRule>(() => {
  const maxParticipants =
    state.maxParticipants !== '' ? Number(state.maxParticipants) : null

  const base = {
    type: state.type,
    startTime: state.startTime,
    endTime: state.endTime,
    startDate: state.startDate,
    count: state.count,
    location: state.location !== '' ? state.location : null,
    maxParticipants,
  }

  if (state.type === 'weekly') {
    return { ...base, weekday: state.weekday }
  }
  return { ...base, dayOfMonth: state.dayOfMonth }
})

watch(currentValue, (value) => {
  emit('update:modelValue', value)
}, { immediate: true })

const previewText = computed<string>(() => {
  if (!state.startDate) return ''

  const parts = state.startDate.split('-')
  if (parts.length !== 3) return ''
  const [year, month, day] = parts
  const dateStr = `${day}.${month}.${year}`

  const timeStr =
    state.startTime && state.endTime ? `, ${state.startTime}–${state.endTime} Uhr` : ''

  const countStr = `, ${state.count} ${state.count === 1 ? 'Einheit' : 'Einheiten'}`

  if (state.type === 'weekly') {
    const weekdayName = WEEKDAY_NAMES[state.weekday] ?? ''
    return `Jeden ${weekdayName} ab ${dateStr}${timeStr}${countStr}`
  }

  return `Jeden ${state.dayOfMonth}. des Monats ab ${dateStr}${timeStr}${countStr}`
})
</script>

<template>
  <div class="space-y-4">
    <!-- Typ-Auswahl -->
    <div>
      <label for="rr-type" class="block text-sm font-medium text-gray-700 mb-1">
        Wiederholungstyp
      </label>
      <select id="rr-type" v-model="state.type" class="input">
        <option value="weekly">Wöchentlich</option>
        <option value="monthly">Monatlich</option>
      </select>
    </div>

    <!-- Wochentag-Auswahl (nur bei weekly) -->
    <div v-if="state.type === 'weekly'">
      <label for="rr-weekday" class="block text-sm font-medium text-gray-700 mb-1">
        Wochentag
      </label>
      <select id="rr-weekday" v-model.number="state.weekday" class="input">
        <option v-for="opt in WEEKDAY_OPTIONS" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>
    </div>

    <!-- Tag des Monats (nur bei monthly) -->
    <div v-if="state.type === 'monthly'">
      <label for="rr-day-of-month" class="block text-sm font-medium text-gray-700 mb-1">
        Tag des Monats
      </label>
      <input
        id="rr-day-of-month"
        v-model.number="state.dayOfMonth"
        type="number"
        min="1"
        max="28"
        class="input"
      />
    </div>

    <!-- Startdatum / Zeiten in einem Grid -->
    <div class="grid grid-cols-2 gap-4">
      <div class="col-span-2">
        <label for="rr-start-date" class="block text-sm font-medium text-gray-700 mb-1">
          Startdatum
        </label>
        <input id="rr-start-date" v-model="state.startDate" type="date" class="input" />
      </div>

      <div>
        <label for="rr-start-time" class="block text-sm font-medium text-gray-700 mb-1">
          Startzeit
        </label>
        <input id="rr-start-time" v-model="state.startTime" type="time" class="input" />
      </div>

      <div>
        <label for="rr-end-time" class="block text-sm font-medium text-gray-700 mb-1">
          Endzeit
        </label>
        <input id="rr-end-time" v-model="state.endTime" type="time" class="input" />
      </div>
    </div>

    <!-- Anzahl Einheiten -->
    <div>
      <label for="rr-count" class="block text-sm font-medium text-gray-700 mb-1">
        Anzahl Einheiten
      </label>
      <input
        id="rr-count"
        v-model.number="state.count"
        type="number"
        min="1"
        max="52"
        class="input"
      />
    </div>

    <!-- Optionale Felder -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label for="rr-location" class="block text-sm font-medium text-gray-700 mb-1">
          Ort <span class="text-gray-400 font-normal">(optional)</span>
        </label>
        <input
          id="rr-location"
          v-model="state.location"
          type="text"
          placeholder="z. B. Platz A"
          class="input"
        />
      </div>

      <div>
        <label for="rr-max-participants" class="block text-sm font-medium text-gray-700 mb-1">
          Max. Teilnehmer <span class="text-gray-400 font-normal">(optional)</span>
        </label>
        <input
          id="rr-max-participants"
          v-model="state.maxParticipants"
          type="number"
          min="1"
          max="50"
          placeholder="—"
          class="input"
        />
      </div>
    </div>

    <!-- Vorschau -->
    <div
      v-if="previewText"
      class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800"
    >
      <span class="font-medium">Vorschau:</span> {{ previewText }}
    </div>
  </div>
</template>
