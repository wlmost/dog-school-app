<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Invoice Controller
 *
 * Handles invoice management and generation.
 */
class InvoiceController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of invoices with optional filtering.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Invoice::query()->with(['customer.user', 'items', 'payments']);

        // Filter by customer
        if ($request->has('customerId')) {
            $query->where('customer_id', $request->input('customerId'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter unpaid invoices
        if ($request->boolean('unpaidOnly')) {
            $query->unpaid();
        }

        // Filter overdue invoices
        if ($request->boolean('overdueOnly')) {
            $query->overdue();
        }

        // Filter by date range
        if ($request->has('startDate')) {
            $query->where('issue_date', '>=', $request->input('startDate'));
        }

        if ($request->has('endDate')) {
            $query->where('issue_date', '<=', $request->input('endDate'));
        }

        return InvoiceResource::collection(
            $query->orderBy('issue_date', 'desc')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created invoice.
     *
     * @param StoreInvoiceRequest $request
     * @return InvoiceResource
     */
    public function store(StoreInvoiceRequest $request): InvoiceResource
    {
        $this->authorize('create', Invoice::class);

        $invoice = Invoice::create($request->validatedSnakeCase());

        // Create invoice items if provided
        if ($request->has('items')) {
            foreach ($request->input('items') as $item) {
                $taxRate = $item['taxRate'] ?? 0;
                $unitPrice = $item['unitPrice'];
                $quantity = $item['quantity'];
                $amount = $unitPrice * $quantity;
                
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'amount' => $amount,
                ]);
            }
        }

        return new InvoiceResource($invoice->load(['customer.user', 'items', 'payments']));
    }

    /**
     * Display the specified invoice.
     *
     * @param Invoice $invoice
     * @return InvoiceResource
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        // Load customer for authorization check
        $invoice->load(['customer.user', 'items', 'payments']);
        
        $this->authorize('view', $invoice);
        
        return new InvoiceResource($invoice);
    }

    /**
     * Update the specified invoice.
     *
     * @param UpdateInvoiceRequest $request
     * @param Invoice $invoice
     * @return InvoiceResource
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        $invoice->update($request->validatedSnakeCase());

        return new InvoiceResource($invoice->fresh(['customer.user', 'items', 'payments']));
    }

    /**
     * Remove the specified invoice.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        // Check if invoice has payments
        if ($invoice->payments()->completed()->exists()) {
            return response()->json([
                'message' => 'Rechnung kann nicht gelöscht werden, da bereits Zahlungen vorhanden sind.',
            ], 422);
        }

        $invoice->delete();

        return response()->json(null, 204);
    }

    /**
     * Mark invoice as paid.
     *
     * @param Invoice $invoice
     * @return InvoiceResource|JsonResponse
     */
    public function markAsPaid(Invoice $invoice): InvoiceResource|JsonResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->isPaid()) {
            return response()->json([
                'message' => 'Rechnung ist bereits bezahlt.',
            ], 422);
        }

        $invoice->update([
            'status' => 'paid',
            'paid_date' => now(),
        ]);

        return new InvoiceResource($invoice->fresh(['customer.user', 'items', 'payments']));
    }

    /**
     * Get overdue invoices.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function overdue(Request $request): AnonymousResourceCollection
    {
        $query = Invoice::query()
            ->with(['customer.user', 'items', 'payments'])
            ->overdue();

        return InvoiceResource::collection(
            $query->orderBy('due_date')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Generate and download invoice as PDF.
     *
     * @param Invoice $invoice
     * @return Response
     */
    public function downloadPdf(Invoice $invoice): Response
    {
        // Load relationships for authorization and PDF generation
        $invoice->load(['customer.user', 'items', 'payments']);
        
        $this->authorize('view', $invoice);

        // Generate PDF
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        // Return PDF download
        return $pdf->download($invoice->invoice_number . '.pdf');
    }
}
