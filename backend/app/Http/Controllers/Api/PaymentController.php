<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Payment Controller
 *
 * Handles payment processing and tracking.
 */
class PaymentController extends Controller
{
    use AuthorizesRequests;

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
                'paid_date' => now(),
            ]);
        }

        return new PaymentResource($payment->fresh('invoice.customer.user'));
    }
}
