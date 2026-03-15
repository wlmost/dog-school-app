<template>
  <div class="space-y-4">
    <div
      v-for="i in count"
      :key="i"
      class="card animate-pulse"
    >
      <div class="flex items-center space-x-4">
        <!-- Avatar -->
        <div
          v-if="avatar"
          class="rounded-full bg-gray-300 dark:bg-gray-700"
          :class="avatarSize"
        ></div>

        <div class="flex-1 space-y-3">
          <!-- Title -->
          <div class="h-4 bg-gray-300 dark:bg-gray-700 rounded w-3/4"></div>
          
          <!-- Description lines -->
          <div
            v-for="line in lines"
            :key="line"
            class="h-3 bg-gray-200 dark:bg-gray-600 rounded"
            :class="line === lines ? 'w-1/2' : 'w-full'"
          ></div>
        </div>

        <!-- Action -->
        <div
          v-if="action"
          class="h-8 w-20 bg-gray-300 dark:bg-gray-700 rounded"
        ></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  count?: number
  lines?: number
  avatar?: boolean
  action?: boolean
}

withDefaults(defineProps<Props>(), {
  count: 3,
  lines: 2,
  avatar: false,
  action: false
})

const avatarSize = 'w-12 h-12'
</script>
