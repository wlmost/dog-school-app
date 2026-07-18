<template>
  <div class="html-editor border border-gray-300 dark:border-gray-600 rounded-md overflow-hidden bg-white dark:bg-gray-800 focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-1 px-2 py-1 bg-gray-50 dark:bg-gray-700 border-b border-gray-300 dark:border-gray-600" role="toolbar" aria-label="Textformatierung">
      <!-- Bold -->
      <button
        type="button"
        title="Fett (Strg+B)"
        :class="['p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors', editor?.isActive('bold') ? 'bg-gray-200 dark:bg-gray-600 text-primary-700 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300']"
        @click="editor?.chain().focus().toggleBold().run()"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M15.6 11.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z"/>
        </svg>
      </button>

      <!-- Italic -->
      <button
        type="button"
        title="Kursiv (Strg+I)"
        :class="['p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors', editor?.isActive('italic') ? 'bg-gray-200 dark:bg-gray-600 text-primary-700 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300']"
        @click="editor?.chain().focus().toggleItalic().run()"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/>
        </svg>
      </button>

      <!-- Heading 2 -->
      <button
        type="button"
        title="Überschrift"
        :class="['p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors', editor?.isActive('heading', { level: 2 }) ? 'bg-gray-200 dark:bg-gray-600 text-primary-700 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300']"
        @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M5 4v3h5.5v12h3V7H19V4z"/>
        </svg>
      </button>

      <!-- Bullet List -->
      <button
        type="button"
        title="Aufzählung"
        :class="['p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors', editor?.isActive('bulletList') ? 'bg-gray-200 dark:bg-gray-600 text-primary-700 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300']"
        @click="editor?.chain().focus().toggleBulletList().run()"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/>
        </svg>
      </button>

      <!-- Ordered List -->
      <button
        type="button"
        title="Nummerierte Liste"
        :class="['p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors', editor?.isActive('orderedList') ? 'bg-gray-200 dark:bg-gray-600 text-primary-700 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300']"
        @click="editor?.chain().focus().toggleOrderedList().run()"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/>
        </svg>
      </button>
    </div>

    <!-- Editor content -->
    <EditorContent
      :editor="editor"
      class="px-3 py-2 min-h-[80px] text-gray-900 dark:text-gray-100 focus:outline-none"
    />
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import DOMPurify from 'dompurify'

const props = defineProps<{
  modelValue: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const editor = useEditor({
  extensions: [StarterKit],
  content: DOMPurify.sanitize(props.modelValue || ''),
  onUpdate({ editor }) {
    emit('update:modelValue', editor.getHTML())
  },
})

// Sync when the v-model value changes from outside
watch(
  () => props.modelValue,
  (newValue) => {
    if (!editor.value) return
    const currentHTML = editor.value.getHTML()
    if (currentHTML !== newValue) {
      editor.value.commands.setContent(DOMPurify.sanitize(newValue || ''))
    }
  }
)

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<style scoped>
.html-editor :deep(.ProseMirror) {
  outline: none;
  min-height: 80px;
}

.html-editor :deep(.ProseMirror p) {
  margin: 0 0 0.5rem 0;
}

.html-editor :deep(.ProseMirror p:last-child) {
  margin-bottom: 0;
}

.html-editor :deep(.ProseMirror h2) {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0.5rem 0;
}

.html-editor :deep(.ProseMirror ul),
.html-editor :deep(.ProseMirror ol) {
  padding-left: 1.25rem;
  margin: 0.25rem 0;
}

.html-editor :deep(.ProseMirror li) {
  margin: 0.125rem 0;
}

.html-editor :deep(.ProseMirror ul) {
  list-style-type: disc;
}

.html-editor :deep(.ProseMirror ol) {
  list-style-type: decimal;
}

.html-editor :deep(.ProseMirror strong) {
  font-weight: 600;
}

.html-editor :deep(.ProseMirror em) {
  font-style: italic;
}
</style>

