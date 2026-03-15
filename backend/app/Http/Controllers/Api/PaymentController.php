<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PayPalService;
use App\Services\PayPalWebhookValidator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * Payment Controller
 *
 * Handles payment processing and tracking.
 */
class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ?PayPalService $payPalService = null,
        private ?PayPalWebhookValidator $webhookValidator = null
    ) {
        if ($this->payPalService === null) {
            $this->payPalService = app(PayPalService::class);
        }
        if ($this->webhookValidator === null) {
            $this->webhookValidator = app(PayPalWebhookValidator::class);
        }
    }

    /**
     * Display a listing of payments with optional filtering.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::query()->with(['invoice.customer.user']);

        // Filter by invoice
        if ($request->has('invoiceId')) {
            $query->where('invoice_id', $request->input('invoiceId'));
        }

        // Filter by payment method
        if ($request->has('paymentMethod')) {
            $query->where('payment_method', $request->input('paymentMethod'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter completed payments
        if ($request->boolean('completedOnly')) {
            $query->completed();
        }

        // Filter by date range
        if ($request->has('startDate')) {
            $query->where('payment_date', '>=', $request->input('startDate'));
        }

        if ($request->has('endDate')) {
            $query->where('payment_date', '<=', $request->input('endDate'));
        }

        return PaymentResource::collection(
            $query->orderBy('payment_date', 'desc')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created payment.
     *
     * @param StorePaymentRequest $request
     * @return PaymentResource
     */
    public function store(StorePaymentRequest $request): PaymentResource
    {
        $this->authorize('create', Payment::class);

        $payment = Payment::create($request->validatedSnakeCase());

        // Update invoice status if fully paid
        $invoice = $payment->invoice;
        $totalPaid = $invoice->payments()->completed()->sum('amount');

        if ($totalPaid >= $invoice->total_amount) {
            $invoice->update([
                'status' => 'paid',
                'paid_date' => now(),
            ]);
        }

        return new PaymentResource($payment->load('invoice.customer.user'));
    }

    /**
     * Display the specified payment.
     *
     * @param Payment $payment
     * @return PaymentResource
     */
    public function show(Payment $payment): PaymentResource
    {
        // Load invoice for authorization check
        $payment->load('invoice.customer.user');
        
        $this->authorize('view', $payment);
        
        return new PaymentResource($payment);
    }

    /**
     * Update the specified payment.
     *
     * @param UpdatePaymentRequest $request
     * @param Payment $payment
     * @return PaymentResource
     */
    public function update(UpdatePaymentRequest $request, Payment $payment): PaymentResource
    {
        $this->authorize('update', $payment);

        $payment->update($request->validatedSnakeCase());

        return new PaymentResource($payment->fresh('invoice.customer.user'));
    }

    /**
     * Remove the specified payment.
     *
     * @param Payment $payment
     * @return JsonResponse
     */
    public function destroy(Payment $payment): JsonResponse
    {
        $this->authorize('delete', $payment);

        // Don't allow deletion of completed payments
        if ($payment->isCompleted()) {
            return response()->json([
                'message' => 'Abgeschlossene Zahlungen können nicht gelöscht werden.',
            ], 422);
        }

        $payment->delete();

        return response()->json(null, 204);
    }

    /**
     * Mark payment as completed.
     *
     * @param Payment $payment
     * @return PaymentResource|JsonResponse
     */
    public function markAsCompleted(Payment $payment): PaymentResource|JsonResponse
    {
        $this->authorize('update', $payment);

        if ($payment->isCompleted()) {
            return response()->json([
                'message' => 'Zahlung ist bereits abgeschlossen.',
            ], 422);
        }

        $payment->update(['status' => 'completed']);

        // Update invoice if fully paid
        $invoice = $payment->invoice;
        $totalPaid = $invoice->payments()->completed()->sum('amount');

        if ($totalPaid >= $invoice->total_amount) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        return new PaymentResource($payment->fresh());
    }

    // ==================== PayPal Integration ====================

    /**
     * Create a PayPal order for an invoice
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPayPalOrder(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        // Check authorization
        $invoice->load('customer.user');
        if (!$request->user()->hasRole('admin') && $invoice->customer->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Nicht autorisiert'], 403);
        }

        // Check if invoice can be paid
        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'Rechnung ist bereits bezahlt'], 400);
        }

        if ($invoice->status === 'cancelled') {
            return response()->json(['message' => 'Rechnung wurde storniert'], 400);
        }

        if ($invoice->remaining_balance <= 0) {
            return response()->json(['message' => 'Rechnung hat keinen offenen Betrag'], 400);
        }

        try {
            $order = $this->payPalService->createOrder($invoice);

            return response()->json([
                'order_id' => $order['id'],
                'status' => $order['status'],
                'links' => $order['links'],
            ]);

        } catch (\Exception $e) {
            Log::error('PayPal order creation failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'PayPal-Bestellung konnte nicht erstellt werden',
                'error' => config('app.debug') ? $e->getMessage() : 'Zahlungsverarbeitungsfehler',
            ], 500);
        }
    }

    /**
     * Capture a PayPal order
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function capturePayPalOrder(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string',
            'invoice_id' => 'required|exists:invoices,id',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        // Check authorization
        $invoice->load('customer.user');
        if (!$request->user()->hasRole('admin') && $invoice->customer->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Nicht autorisiert'], 403);
        }

        try {
            $payment = $this->payPalService->captureOrder($request->order_id, $invoice);

            return response()->json([
                'message' => 'Zahlung erfolgreich abgeschlossen',
                'payment' => new PaymentResource($payment->load('invoice.customer.user')),
            ]);

        } catch (\Exception $e) {
            Log::error('PayPal capture failed', [
                'order_id' => $request->order_id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Zahlung konnte nicht abgeschlossen werden',
                'error' => config('app.debug') ? $e->getMessage() : 'Zahlungserfassungsfehler',
            ], 500);
        }
    }

    /**
     * Handle PayPal webhook notifications
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // Validate PayPal webhook signature
        if (!$this->webhookValidator->validate($request)) {
            Log::warning('PayPal webhook signature validation failed');
            return response()->json(['status' => 'invalid signature'], 401);
        }

        Log::info('PayPal webhook received', [
            'event_type' => $request->input('event_type'),
            'resource' => $request->input('resource'),
        ]);

        $eventType = $request->input('event_type');
        $resource = $request->input('resource');

        try {
            switch ($eventType) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $this->handlePaymentCaptureCompleted($resource);
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.DECLINED':
                    $this->handlePaymentCaptureFailed($resource);
                    break;

                case 'PAYMENT.CAPTURE.REFUNDED':
                    $this->handlePaymentRefunded($resource);
                    break;

                default:
                    Log::info('Unhandled PayPal webhook event', ['event_type' => $eventType]);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle successful payment capture from webhook
     *
     * @param array $resource
     * @return void
     */
    private function handlePaymentCaptureCompleted(array $resource): void
    {
        $transactionId = $resource['id'] ?? null;

        if (!$transactionId) {
            return;
        }

        $payment = Payment::where('transaction_id', $transactionId)->first();

        if ($payment && $payment->status !== 'completed') {
            $payment->update(['status' => 'completed']);

            // Update invoice
            $invoice = $payment->invoice;
            if ($invoice->remaining_balance <= 0.01) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_date' => now(),
                ]);
            }

            Log::info('Payment status updated to completed', ['payment_id' => $payment->id]);
        }
    }

    /**
     * Handle failed payment capture from webhook
     *
     * @param array $resource
     * @return void
     */
    private function handlePaymentCaptureFailed(array $resource): void
    {
        $transactionId = $resource['id'] ?? null;

        if (!$transactionId) {
            return;
        }

        $payment = Payment::where('transaction_id', $transactionId)->first();

        if ($payment && $payment->status !== 'failed') {
            $payment->update(['status' => 'failed']);
            Log::warning('Payment marked as failed', ['payment_id' => $payment->id]);
        }
    }

    /**
     * Handle payment refund from webhook
     *
     * @param array $resource
     * @return void
     */
    private function handlePaymentRefunded(array $resource): void
    {
        $transactionId = $resource['id'] ?? null;

        if (!$transactionId) {
            return;
        }

        $payment = Payment::where('transaction_id', $transactionId)->first();

        if ($payment && $payment->status !== 'refunded') {
            $payment->update(['status' => 'refunded']);

            // Update invoice status if it was marked as paid
            $invoice = $payment->invoice;
            if ($invoice->status === 'paid') {
                $invoice->update([
                    'status' => 'sent',
                    'paid_date' => null,
                ]);
            }

            Log::info('Payment refunded', ['payment_id' => $payment->id]);
        }
    }
}
