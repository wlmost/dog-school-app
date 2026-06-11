<template>
  <div v-if="lastPage > 1" class="flex items-center justify-between px-2 py-3">
    <p class="text-sm text-gray-700 dark:text-gray-300">
      Seite <span class="font-medium">{{ currentPage }}</span> von
      <span class="font-medium">{{ lastPage }}</span>
      <template v-if="total !== undefined">
        &nbsp;({{ total }} Einträge)
      </template>
    </p>
    <div class="flex gap-2">
      <button
        class="btn btn-secondary"
        :disabled="currentPage <= 1"
        @click="emit('update:currentPage', currentPage - 1)"
      >
        Vorherige
      </button>
      <button
        class="btn btn-secondary"
        :disabled="currentPage >= lastPage"
        @click="emit('update:currentPage', currentPage + 1)"
      >
        Nächste
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  currentPage: number
  lastPage: number
  total?: number
}>()

const emit = defineEmits<{
  'update:currentPage': [page: number]
}>()
</script>
