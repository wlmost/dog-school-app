<template>
  <div v-if="visible" class="fixed inset-0 z-50 flex items-start justify-center bg-black/50" @click.self="emit('close')">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl mx-4 mt-20 max-h-[80vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Preisübersicht</h2>
        <button
          type="button"
          class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
          aria-label="Modal schließen"
          @click="emit('close')"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <!-- Content -->
      <div class="overflow-y-auto px-6 py-4 flex-1">
        <p v-if="groups.length === 0" class="text-gray-500 dark:text-gray-400 text-center py-8">
          Noch keine Preise hinterlegt.
        </p>

        <div v-for="group in groups" :key="group.category" class="mb-8 last:mb-0">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
            {{ group.category }}
          </h3>
          <ul class="space-y-3">
            <li
              v-for="item in group.items"
              :key="item.id"
              class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1"
            >
              <div class="flex-1">
                <span class="font-medium text-gray-900 dark:text-white">{{ item.title }}</span>
                <span v-if="item.unit" class="ml-1 text-sm text-gray-500 dark:text-gray-400">({{ item.unit }})</span>
                <p v-if="item.description" class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                  {{ item.description }}
                </p>
              </div>
              <div class="shrink-0 text-gray-900 dark:text-white font-semibold sm:text-right">
                <span v-if="item.isFromPrice">ab </span>{{ formatPrice(item.price) }}
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline'
import type { PricingGroup } from '@/api/pricingItems'

defineProps<{
  visible: boolean
  groups: PricingGroup[]
}>()

const emit = defineEmits<{ close: [] }>()

function formatPrice(price: string): string {
  return (
    parseFloat(price).toLocaleString('de-DE', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }) + ' €'
  )
}
</script>
