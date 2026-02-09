import apiClient from './client';

export interface PayPalOrderResponse {
  order_id: string;
  status: string;
  links: Array<{
    href: string;
    rel: string;
    method: string;
  }>;
}

export interface PayPalCaptureResponse {
  message: string;
  payment: {
    id: number;
    invoice_id: number;
    payment_date: string;
    amount: number;
    payment_method: string;
    transaction_id: string;
    status: string;
  };
}

/**
 * Create a PayPal order for an invoice
 */
export const createPayPalOrder = async (invoiceId: number): Promise<PayPalOrderResponse> => {
  const response = await apiClient.post<PayPalOrderResponse>('/api/v1/payments/paypal/create-order', {
    invoice_id: invoiceId,
  });
  return response.data;
};

/**
 * Capture a PayPal order after user approval
 */
export const capturePayPalOrder = async (
  orderId: string,
  invoiceId: number
): Promise<PayPalCaptureResponse> => {
  const response = await apiClient.post<PayPalCaptureResponse>('/api/v1/payments/paypal/capture-order', {
    order_id: orderId,
    invoice_id: invoiceId,
  });
  return response.data;
};
