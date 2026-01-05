<template>
  <div class="flex items-center justify-center" :class="containerClass">
    <div
      class="spinner"
      :class="sizeClass"
      :style="{ borderTopColor: color }"
    ></div>
    <span v-if="text" class="ml-3 text-gray-600 dark:text-gray-400">
      {{ text }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  size?: 'sm' | 'md' | 'lg'
  color?: string
  text?: string
  fullscreen?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  color: '#4F46E5'
})

const sizeClass = computed(() => {
  const sizes = {
    sm: 'w-4 h-4 border-2',
    md: 'w-8 h-8 border-3',
    lg: 'w-12 h-12 border-4'
  }
  return sizes[props.size]
})

const containerClass = computed(() => {
  return props.fullscreen 
    ? 'fixed inset-0 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm z-50'
    : 'py-8'
})
</script>

<style scoped>
.spinner {
  border-radius: 50%;
  border-style: solid;
  border-color: rgba(0, 0, 0, 0.1);
  animation: spin 0.8s linear infinite;
}

.dark .spinner {
  border-color: rgba(255, 255, 255, 0.1);
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
</style>
