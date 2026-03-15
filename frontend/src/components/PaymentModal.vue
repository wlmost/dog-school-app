<template>
  <div>
    <!-- Payment Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="isOpen"
          class="fixed inset-0 z-50 overflow-y-auto"
          @click.self="close"
        >
          <div class="flex min-h-screen items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/50 transition-opacity"></div>

            <!-- Modal Content -->
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full">
              <!-- Header -->
              <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                  Rechnung bezahlen
                </h3>
                <button
                  @click="close"
                  class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                >
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Body -->
              <div class="p-6">
                <!-- Invoice Details -->
                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 mb-6">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <p class="text-sm text-gray-500 dark:text-gray-400">Rechnungsnummer</p>
                      <p class="font-medium text-gray-900 dark:text-white">{{ invoice.invoice_number }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500 dark:text-gray-400">Datum</p>
                      <p class="font-medium text-gray-900 dark:text-white">{{ formatDate(invoice.issue_date) }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500 dark:text-gray-400">Gesamtbetrag</p>
                      <p class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(invoice.total_amount) }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500 dark:text-gray-400">Offener Betrag</p>
                      <p class="text-xl font-bold text-blue-600 dark:text-blue-400">
                        {{ formatCurrency(invoice.remaining_balance) }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Payment Method Selection -->
                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    Zahlungsmethode
                  </label>
                  <div class="grid grid-cols-1 gap-3">
                    <button
                      @click="selectedMethod = 'paypal'"
                      :class="[
                        'flex items-center justify-between p-4 border-2 rounded-lg transition-colors',
                        selectedMethod === 'paypal'
                          ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                          : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                      ]"
                    >
                      <div class="flex items-center space-x-3">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none">
                          <path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944 3.72a.77.77 0 0 1 .76-.653h7.346c1.653 0 2.925.45 3.715 1.317.688.755 1.007 1.68 1.007 2.917 0 .305-.025.623-.074.952a5.378 5.378 0 0 1-.256 1.109 5.698 5.698 0 0 1-2.081 2.738c-.853.584-1.906.88-3.127.88h-1.582a.77.77 0 0 0-.76.653l-.48 3.04-.09.566a.397.397 0 0 1-.392.336zm.649-10.5a.77.77 0 0 1 .76-.653h1.67c1.03 0 1.81-.236 2.396-.726.565-.474.956-1.238 1.145-2.283.022-.123.04-.25.052-.377.013-.128.02-.26.02-.394 0-.68-.18-1.18-.55-1.533-.372-.354-1.007-.53-1.895-.53h-5.47a.397.397 0 0 0-.392.336l-2.019 12.795h2.296l1.987-6.635z" fill="#139AD6"/>
                          <path d="M19.277 6.697c.062.39.088.795.074 1.209-.013.413-.067.84-.162 1.277a5.698 5.698 0 0 1-2.081 2.738c-.853.584-1.906.88-3.127.88h-1.582a.77.77 0 0 0-.76.653l-.577 3.656-.164 1.04a.397.397 0 0 1-.392.336H7.737l-.991 6.284a.641.641 0 0 0 .633.74h4.606a.77.77 0 0 0 .76-.653l.032-.161.607-3.844.039-.207a.77.77 0 0 1 .76-.653h.481c1.06 0 1.89-.257 2.474-.77.566-.498.945-1.254 1.116-2.24.018-.104.033-.21.045-.318.012-.108.02-.22.024-.334 0-.077.004-.154.007-.231.003-.077.003-.154.003-.231-.001-.68-.18-1.18-.55-1.533z" fill="#263B80"/>
                        </svg>
                        <div class="text-left">
                          <p class="font-medium text-gray-900 dark:text-white">PayPal</p>
                          <p class="text-sm text-gray-500 dark:text-gray-400">Sicher bezahlen mit PayPal</p>
                        </div>
                      </div>
                      <div v-if="selectedMethod === 'paypal'" class="text-blue-500">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                      </div>
                    </button>
                  </div>
                </div>

                <!-- PayPal Button -->
                <div v-if="selectedMethod === 'paypal'">
                  <PayPalButton
                    :invoice-id="invoice.id"
                    :amount="invoice.remaining_balance"
                    currency="EUR"
                    @success="handlePaymentSuccess"
                    @error="handlePaymentError"
                    @cancel="handlePaymentCancel"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import PayPalButton from './PayPalButton.vue';
import { useToastStore } from '@/stores/toast';

interface Invoice {
  id: number;
  invoice_number: string;
  issue_date: string;
  total_amount: number;
  remaining_balance: number;
}

const props = defineProps<{
  invoice: Invoice;
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'payment-success'): void;
}>();

const toast = useToastStore();
const selectedMethod = ref<'paypal'>('paypal');

const close = () => {
  emit('close');
};

const handlePaymentSuccess = (payment: any) => {
  toast.success('Zahlung erfolgreich! Die Rechnung wurde aktualisiert.');
  emit('payment-success');
  close();
};

const handlePaymentError = (error: string) => {
  // Error toast already shown by PayPalButton component
  console.error('Payment error:', error);
};

const handlePaymentCancel = () => {
  // Cancel toast already shown by PayPalButton component
};

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('de-DE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
  }).format(amount);
};
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
