<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" @close="$emit('close')" class="relative z-50">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black bg-opacity-25" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel class="w-full max-w-md transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4 flex justify-between items-center">
                <span>Rechnung versenden</span>
                <button @click="$emit('close')" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </DialogTitle>

              <div v-if="invoice" class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Rechnung <span class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ invoice.invoiceNumber }}</span>
                </p>

                <div class="space-y-3">
                  <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">Aus der App versenden</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                      Die Rechnung wird per E-Mail an {{ invoice.customer?.user?.email }} verschickt.
                    </p>
                    <button
                      @click="$emit('send-email', invoice)"
                      class="btn bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white w-full"
                    >
                      Aus der App versenden
                    </button>
                  </div>

                  <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">Manuell versenden</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                      Das Rechnungs-PDF herunterladen und selbst versenden.
                    </p>
                    <button
                      @click="$emit('download', invoice)"
                      class="btn bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 w-full"
                    >
                      Manuell versenden (PDF herunterladen)
                    </button>
                  </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-gray-200 dark:border-gray-700">
                  <button @click="$emit('close')" class="btn bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200">
                    Schließen
                  </button>
                </div>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'

defineProps<{
  isOpen: boolean
  invoice?: any
}>()

defineEmits<{
  close: []
  download: [invoice: any]
  'send-email': [invoice: any]
}>()
</script>
