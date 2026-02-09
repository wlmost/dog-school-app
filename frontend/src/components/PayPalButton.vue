<template>
  <div class="paypal-button-container">
    <!-- Loading state -->
    <div v-if="loading" class="flex items-center justify-center space-x-2 py-4">
      <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
      <span class="text-gray-600 dark:text-gray-400">PayPal wird geladen...</span>
    </div>

    <!-- Error state -->
    <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
      <div class="flex items-start">
        <svg class="h-5 w-5 text-red-400 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <p class="text-sm text-red-800 dark:text-red-200">{{ error }}</p>
      </div>
    </div>

    <!-- PayPal button container -->
    <div ref="paypalButtonContainer" :class="{ 'opacity-50 pointer-events-none': processing }"></div>

    <!-- Processing overlay -->
    <div v-if="processing" class="mt-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
      <div class="flex items-center space-x-3">
        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
        <span class="text-sm text-blue-800 dark:text-blue-200">Zahlung wird verarbeitet...</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { createPayPalOrder, capturePayPalOrder } from '@/api/paypal';
import { useToastStore } from '@/stores/toast';

const props = defineProps<{
  invoiceId: number;
  amount: number;
  currency?: string;
}>();

const emit = defineEmits<{
  (e: 'success', payment: any): void;
  (e: 'error', error: string): void;
  (e: 'cancel'): void;
}>();

const toast = useToastStore();
const paypalButtonContainer = ref<HTMLElement | null>(null);
const loading = ref(true);
const processing = ref(false);
const error = ref<string | null>(null);

// Load PayPal SDK script
const loadPayPalScript = (): Promise<void> => {
  return new Promise((resolve, reject) => {
    // Check if PayPal SDK is already loaded
    if (window.paypal) {
      resolve();
      return;
    }

    const script = document.createElement('script');
    script.src = `https://www.paypal.com/sdk/js?client-id=${import.meta.env.VITE_PAYPAL_CLIENT_ID}&currency=${props.currency || 'EUR'}`;
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('PayPal SDK konnte nicht geladen werden'));
    document.head.appendChild(script);
  });
};

// Initialize PayPal button
const initPayPalButton = () => {
  if (!paypalButtonContainer.value || !window.paypal) {
    return;
  }

  // Clear any existing buttons
  paypalButtonContainer.value.innerHTML = '';

  window.paypal.Buttons({
    style: {
      layout: 'vertical',
      color: 'blue',
      shape: 'rect',
      label: 'paypal',
    },

    // Create order
    createOrder: async () => {
      try {
        processing.value = true;
        error.value = null;

        const response = await createPayPalOrder(props.invoiceId);
        return response.order_id;

      } catch (err: any) {
        error.value = err.response?.data?.message || 'PayPal-Bestellung konnte nicht erstellt werden';
        toast.error(error.value);
        throw err;
      } finally {
        processing.value = false;
      }
    },

    // Capture order after approval
    onApprove: async (data: any) => {
      try {
        processing.value = true;
        error.value = null;

        const response = await capturePayPalOrder(data.orderID, props.invoiceId);
        
        toast.success('Zahlung erfolgreich abgeschlossen');
        emit('success', response.payment);

      } catch (err: any) {
        error.value = err.response?.data?.message || 'Zahlung konnte nicht abgeschlossen werden';
        toast.error(error.value);
        emit('error', error.value);
      } finally {
        processing.value = false;
      }
    },

    // Handle cancellation
    onCancel: () => {
      toast.info('Zahlung wurde abgebrochen');
      emit('cancel');
    },

    // Handle errors
    onError: (err: any) => {
      error.value = 'Ein Fehler ist bei der Zahlung aufgetreten';
      toast.error(error.value);
      emit('error', error.value);
      console.error('PayPal error:', err);
    },

  }).render(paypalButtonContainer.value);
};

// Setup PayPal
const setupPayPal = async () => {
  try {
    loading.value = true;
    error.value = null;

    await loadPayPalScript();
    initPayPalButton();

  } catch (err: any) {
    error.value = err.message || 'PayPal konnte nicht initialisiert werden';
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  setupPayPal();
});

// Re-initialize if invoice changes
watch(() => props.invoiceId, () => {
  if (window.paypal) {
    initPayPalButton();
  }
});

// Extend window type for PayPal
declare global {
  interface Window {
    paypal?: any;
  }
}
</script>

<style scoped>
.paypal-button-container {
  min-height: 150px;
}
</style>
