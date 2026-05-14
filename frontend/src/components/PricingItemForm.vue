<template>
  <div
    v-if="visible"
    class="fixed inset-0 z-50 flex items-start justify-center bg-black/50"
    @click.self="emit('cancel')"
  >
    <div
      class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 mt-20 flex flex-col"
    >
      <!-- Header -->
      <div
        class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700"
      >
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
          {{ props.item ? 'Preis bearbeiten' : 'Neuen Preis anlegen' }}
        </h2>
        <button
          type="button"
          class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
          aria-label="Formular schließen"
          @click="emit('cancel')"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <!-- Form -->
      <form class="p-6 space-y-4" @submit.prevent="handleSubmit">
        <!-- General error -->
        <div
          v-if="errors.general"
          class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-md p-3"
        >
          <p class="text-sm text-red-800 dark:text-red-400">{{ errors.general }}</p>
        </div>

        <!-- Kategorie -->
        <div>
          <label
            for="pf-category"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
          >
            Kategorie <span class="text-red-500">*</span>
          </label>
          <input
            id="pf-category"
            v-model="form.category"
            type="text"
            placeholder="z. B. Verhaltensberatung"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            :class="{
              'border-red-300 focus:border-red-500 focus:ring-red-500': errors.category,
            }"
          />
          <p v-if="errors.category" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ errors.category }}
          </p>
        </div>

        <!-- Leistung -->
        <div>
          <label
            for="pf-title"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
          >
            Leistung <span class="text-red-500">*</span>
          </label>
          <input
            id="pf-title"
            v-model="form.title"
            type="text"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': errors.title }"
          />
          <p v-if="errors.title" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ errors.title }}
          </p>
        </div>

        <!-- Preis -->
        <div>
          <label
            for="pf-price"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
          >
            Preis (EUR) <span class="text-red-500">*</span>
          </label>
          <input
            id="pf-price"
            v-model.number="form.price"
            type="number"
            min="0"
            step="0.01"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': errors.price }"
          />
          <p v-if="errors.price" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ errors.price }}
          </p>
        </div>

        <!-- Einheit -->
        <div>
          <label
            for="pf-unit"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
          >
            Einheit
          </label>
          <input
            id="pf-unit"
            v-model="form.unit"
            type="text"
            placeholder="je Einheit / pro Kurs"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          />
        </div>

        <!-- Zusatzinfo -->
        <div>
          <label
            for="pf-description"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
          >
            Zusatzinfo
          </label>
          <textarea
            id="pf-description"
            v-model="form.description"
            rows="3"
            placeholder="z. B. max 6 Teilnehmer"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          ></textarea>
        </div>

        <!-- Ab-Preis -->
        <div class="flex items-start">
          <div class="flex items-center h-5">
            <input
              id="pf-is-from-price"
              v-model="form.isFromPrice"
              type="checkbox"
              class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 dark:border-gray-600 rounded"
            />
          </div>
          <div class="ml-3 text-sm">
            <label
              for="pf-is-from-price"
              class="font-medium text-gray-700 dark:text-gray-300"
            >
              Ab-Preis (zeigt "ab X €")
            </label>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3 pt-2">
          <button
            type="button"
            :disabled="saving"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="emit('cancel')"
          >
            Abbrechen
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="saving">Speichern...</span>
            <span v-else>{{ props.item ? 'Aktualisieren' : 'Anlegen' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import type { PricingItem } from '@/api/pricingItems'
import { usePricingItems } from '@/composables/usePricingItems'

const props = defineProps<{
  visible: boolean
  item: PricingItem | null
}>()

const emit = defineEmits<{
  saved: []
  cancel: []
}>()

const form = reactive<{
  category: string
  title: string
  price: number | string
  unit: string
  description: string
  isFromPrice: boolean
}>({
  category: '',
  title: '',
  price: 0,
  unit: '',
  description: '',
  isFromPrice: false,
})
const errors = reactive<Record<string, string>>({})
const saving = ref(false)

watch(
  () => props.visible,
  (val) => {
    if (val) {
      if (props.item) {
        form.category = props.item.category
        form.title = props.item.title
        form.price = parseFloat(props.item.price)
        form.unit = props.item.unit ?? ''
        form.description = props.item.description ?? ''
        form.isFromPrice = props.item.isFromPrice
      } else {
        form.category = ''
        form.title = ''
        form.price = 0
        form.unit = ''
        form.description = ''
        form.isFromPrice = false
      }
      Object.keys(errors).forEach((k) => delete errors[k])
    }
  },
)

const { createItem, updateItem, error: apiError } = usePricingItems()

async function handleSubmit() {
  Object.keys(errors).forEach((k) => delete errors[k])
  if (!form.category.trim()) errors.category = 'Kategorie ist erforderlich'
  if (!form.title.trim()) errors.title = 'Leistungsbezeichnung ist erforderlich'
  if (form.price === '' || form.price == null || isNaN(Number(form.price)) || !isFinite(Number(form.price))) errors.price = 'Bitte einen gültigen Preis eingeben'
  else if (Number(form.price) < 0) errors.price = 'Preis darf nicht negativ sein'
  if (Object.keys(errors).length > 0) return

  saving.value = true
  const payload = {
    category: form.category,
    title: form.title,
    price: String(form.price),
    unit: form.unit || null,
    description: form.description || null,
    isFromPrice: form.isFromPrice,
  }
  try {
    if (props.item) {
      await updateItem(props.item.id, payload)
    } else {
      await createItem(payload)
    }
    if (apiError.value) {
      errors.general = apiError.value
    } else {
      emit('saved')
    }
  } finally {
    saving.value = false
  }
}
</script>
