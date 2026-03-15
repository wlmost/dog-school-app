<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Log;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use PaypalServerSdkLib\Controllers\OrdersController;
use PaypalServerSdkLib\Models\OrderRequest;
use PaypalServerSdkLib\Models\PurchaseUnitRequest;
use PaypalServerSdkLib\Models\AmountWithBreakdown;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\Models\ApplicationContext;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;

class PayPalService
{
    private PaypalServerSdkClient $client;
    private OrdersController $ordersController;

    public function __construct()
    {
        $this->client = PaypalServerSdkClientBuilder::init()
            ->clientCredentialsAuthCredentials(
                ClientCredentialsAuthCredentialsBuilder::init(
                    config('paypal.client_id'),
                    config('paypal.client_secret')
                )
            )
            ->environment(config('paypal.mode') === 'live' ? Environment::PRODUCTION : Environment::SANDBOX)
            ->build();

        $this->ordersController = $this->client->getOrdersController();
    }

    /**
     * Create a PayPal order for an invoice
     *
     * @param Invoice $invoice
     * @return array
     * @throws Exception
     */
    public function createOrder(Invoice $invoice): array
    {
        try {
            $amount = (string) number_format($invoice->remaining_balance, 2, '.', '');

            $request = new OrderRequest();
            $request->intent = CheckoutPaymentIntent::CAPTURE;
            
            $purchaseUnit = new PurchaseUnitRequest();
            $purchaseUnit->referenceId = $invoice->invoice_number;
            $purchaseUnit->description = "Rechnung #{$invoice->invoice_number}";
            
            $amountWithBreakdown = new AmountWithBreakdown();
            $amountWithBreakdown->currencyCode = config('paypal.currency');
            $amountWithBreakdown->value = $amount;
            
            $purchaseUnit->amount = $amountWithBreakdown;
            $request->purchaseUnits = [$purchaseUnit];

            $applicationContext = new ApplicationContext();
            $applicationContext->returnUrl = config('paypal.return_url') . '?invoice_id=' . $invoice->id;
            $applicationContext->cancelUrl = config('paypal.cancel_url') . '?invoice_id=' . $invoice->id;
            $applicationContext->brandName = config('app.name');
            $request->applicationContext = $applicationContext;

            $response = $this->ordersController->ordersCreate([], $request);
            $order = $response->getResult();

            Log::info('PayPal order created', [
                'order_id' => $order->getId(),
                'invoice_id' => $invoice->id,
                'amount' => $amount,
            ]);

            return [
                'id' => $order->getId(),
                'status' => $order->getStatus(),
                'links' => $order->getLinks(),
            ];

        } catch (Exception $e) {
            Log::error('PayPal order creation failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Capture a PayPal order payment
     *
     * @param string $orderId
     * @param Invoice $invoice
     * @return Payment
     * @throws Exception
     */
    public function captureOrder(string $orderId, Invoice $invoice): Payment
    {
        try {
            $response = $this->ordersController->ordersCapture($orderId);
            $order = $response->getResult();

            $captureDetails = $order->getPurchaseUnits()[0]->getPayments()->getCaptures()[0] ?? null;

            if (!$captureDetails) {
                throw new Exception('No capture details found in PayPal response');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_date' => now(),
                'amount' => (float) $captureDetails->getAmount()->getValue(),
                'payment_method' => 'paypal',
                'transaction_id' => $captureDetails->getId(),
                'status' => strtolower($captureDetails->getStatus()),
            ]);

            // Update invoice status if fully paid
            if ($invoice->remaining_balance <= 0.01) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_date' => now(),
                ]);
            }

            Log::info('PayPal payment captured', [
                'order_id' => $orderId,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ]);

            return $payment;

        } catch (Exception $e) {
            Log::error('PayPal capture failed', [
                'order_id' => $orderId,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get details of a PayPal order
     *
     * @param string $orderId
     * @return array
     * @throws Exception
     */
    public function getOrderDetails(string $orderId): array
    {
        try {
            $response = $this->ordersController->ordersGet($orderId);
            $order = $response->getResult();

            return [
                'id' => $order->getId(),
                'status' => $order->getStatus(),
                'purchase_units' => $order->getPurchaseUnits(),
            ];

        } catch (Exception $e) {
            Log::error('Failed to get PayPal order details', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
