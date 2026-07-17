<template>
  <section v-if="announcements.length" class="py-8 bg-amber-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <article
        v-for="announcement in announcements"
        :key="announcement.id"
        class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 flex flex-col sm:flex-row gap-6"
      >
        <img
          v-if="announcement.imageUrl"
          :src="announcement.imageUrl"
          :alt="announcement.title"
          class="w-full sm:w-48 h-48 object-cover rounded-lg flex-shrink-0"
        >
        <div class="flex-1 min-w-0">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
            {{ announcement.title }}
          </h2>
          <!-- eslint-disable-next-line vue/no-v-html -->
          <div
            class="text-gray-600 dark:text-gray-300 announcement-body"
            v-html="sanitizeHtml(announcement.body)"
          ></div>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import DOMPurify from 'dompurify'
import { useAnnouncements } from '@/composables/useAnnouncements'

const { announcements, loadPublic } = useAnnouncements()

/**
 * Allowed HTML tags consistent with the backend sanitization allowlist,
 * mirroring frontend/src/views/courses/CoursesView.vue's ALLOWED_TAGS.
 */
const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre']

function sanitizeHtml(html: string): string {
  if (!html) return ''
  return DOMPurify.sanitize(html, { ALLOWED_TAGS, ALLOWED_ATTR: [] })
}

onMounted(() => loadPublic())
</script>

<style scoped>
.announcement-body :deep(p) {
  margin: 0 0 0.5rem 0;
}

.announcement-body :deep(p:last-child) {
  margin-bottom: 0;
}

.announcement-body :deep(ul),
.announcement-body :deep(ol) {
  padding-left: 1.25rem;
  margin: 0.25rem 0;
}

.announcement-body :deep(ul) {
  list-style-type: disc;
}

.announcement-body :deep(ol) {
  list-style-type: decimal;
}
</style>
