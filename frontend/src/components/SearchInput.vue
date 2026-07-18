<template>
  <div class="relative">
    <!-- Search icon -->
    <svg
      class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 dark:text-gray-500 pointer-events-none"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>

    <!-- Text input -->
    <input
      ref="inputRef"
      :value="modelValue"
      type="text"
      :placeholder="placeholder"
      class="input pl-10"
      :class="{ 'pr-9': modelValue }"
      @input="onInput"
      @keydown.escape="clearSearch"
    />

    <!-- Clear button — only visible when there is text -->
    <button
      v-if="modelValue"
      type="button"
      class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
      :aria-label="clearLabel"
      @click="clearSearch"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

interface Props {
  modelValue: string
  placeholder?: string
  clearLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Suchen…',
  clearLabel: 'Suche löschen',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'clear': []
}>()

const inputRef = ref<HTMLInputElement | null>(null)

function onInput(event: Event) {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}

function clearSearch() {
  emit('update:modelValue', '')
  emit('clear')
  inputRef.value?.focus()
}
</script>
