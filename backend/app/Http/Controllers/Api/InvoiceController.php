<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\DatabaseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoiceNumberGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Invoice Controller
 *
 * Handles invoice management and generation.
 */
class InvoiceController extends Controller
{
    use AuthorizesRequests;

    /**
     * Maximum number of attempts for {@see self::finalize()} to generate a
     * fresh invoice number and persist it, before giving up on a repeated
     * unique-constraint collision.
     */
    private const FINALIZE_MAX_ATTEMPTS = 3;

    /**
     * Maximum number of attempts for {@see self::cancel()} to generate a
     * fresh invoice number and persist the cancellation invoice, before
     * giving up on a repeated unique-constraint collision. Mirrors
     * {@see self::FINALIZE_MAX_ATTEMPTS}.
     */
    private const CANCEL_MAX_ATTEMPTS = 3;

    /**
     * Display a listing of invoices with optional filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Invoice::query()->with([
            'customer.user', 'items', 'payments', 'originalInvoice', 'cancellationInvoice', 'dunnings',
        ]);

        $user = $request->user();

        // Role-based filtering
        if ($user->isTrainer()) {
            // Trainer sees only invoices for their assigned customers
            $query->whereHas('customer', function ($q) use ($user) {
                $q->where('trainer_id', $user->id);
            });
        } elseif ($user->isCustomer()) {
            // Customer sees only their own invoices
            $customer = Customer::where('user_id', $user->id)->first();
            if ($customer) {
                $query->where('customer_id', $customer->id)
                    ->whereIn('status', ['sent', 'paid', 'overdue', 'reminded']);
            } else {
                // No customer record means no invoices
                $query->whereRaw('1 = 0');
            }
        }
        // Admin sees everything (no filter)

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
            $query->where('invoice_date', '>=', $request->input('startDate'));
        }

        if ($request->has('endDate')) {
            $query->where('invoice_date', '<=', $request->input('endDate'));
        }

        // Search by invoice number or customer name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', DatabaseHelper::caseInsensitiveLike(), "%{$search}%")
                    ->orWhereHas('customer.user', function ($q) use ($search) {
                        $q->where('first_name', DatabaseHelper::caseInsensitiveLike(), "%{$search}%")
                            ->orWhere('last_name', DatabaseHelper::caseInsensitiveLike(), "%{$search}%");
                    });
            });
        }

        return InvoiceResource::collection(
            $query->orderBy('issue_date', 'desc')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created invoice.
     */
    public function store(StoreInvoiceRequest $request): InvoiceResource
    {
        $this->authorize('create', Invoice::class);

        $invoice = Invoice::create($request->validatedSnakeCase());

        // Check if small business regulation applies (no VAT)
        $isSmallBusiness = Setting::get('company_small_business', false);
        $defaultTaxRate = $isSmallBusiness ? 0 : 19;

        // Create invoice items if provided
        if ($request->has('items')) {
            foreach ($request->input('items') as $item) {
                $taxRate = $item['taxRate'] ?? $defaultTaxRate;
                $unitPrice = $item['unitPrice'];
                $quantity = $item['quantity'];
                $amount = $unitPrice * $quantity;

                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'amount' => round($amount, 2),
                ]);
            }
        }

        $invoice->load(['customer.user', 'items', 'payments']);

        return new InvoiceResource($invoice);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        // Load customer for authorization check
        $invoice->load(['customer.user', 'items', 'payments', 'originalInvoice', 'cancellationInvoice', 'dunnings']);

        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice);
    }

    /**
     * Update the specified invoice.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        $invoice->update($request->validatedSnakeCase());

        return new InvoiceResource($invoice->fresh(['customer.user', 'items', 'payments']));
    }

    /**
     * Remove the specified invoice.
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
     */
    public function markAsPaid(Invoice $invoice): InvoiceResource|JsonResponse
    {
        $this->authorize('markAsPaid', $invoice);

        if ($invoice->status === 'draft') {
            return response()->json([
                'message' => 'Ein Entwurf muss zuerst freigegeben werden, bevor er als bezahlt markiert werden kann.',
            ], 422);
        }

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
     * Finalize a draft invoice: assigns the next invoice number and moves
     * it to status "sent". No email is dispatched here (see Change 2).
     *
     * The number generation + persist step is retried up to
     * {@see self::FINALIZE_MAX_ATTEMPTS} times if it collides with a
     * concurrently assigned invoice number (unique constraint violation on
     * `invoice_number`, {@see UniqueConstraintViolationException}). This
     * covers a documented gap in {@see InvoiceNumberGenerator::generate()}'s
     * locking strategy: an empty result set for the current year cannot be
     * locked via `lockForUpdate()`, so two near-simultaneous calls can
     * compute the same next number (see `task-T03.notes.md`). The retry is
     * driver-agnostic: Laravel's connection layer maps unique-constraint
     * errors from MySQL, PostgreSQL and SQLite alike to
     * `UniqueConstraintViolationException` (see `MySqlConnection`,
     * `PostgresConnection` and `SQLiteConnection::isUniqueConstraintError()`),
     * so no driver-specific branching is needed here.
     */
    public function finalize(Invoice $invoice, InvoiceNumberGenerator $numberGenerator): InvoiceResource|JsonResponse
    {
        $this->authorize('finalize', $invoice);

        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Nur Entwürfe können freigegeben werden.',
            ], 422);
        }

        $maxAttempts = self::FINALIZE_MAX_ATTEMPTS;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $invoice->update([
                    'invoice_number' => $numberGenerator->generate(),
                    'status' => 'sent',
                ]);

                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        return new InvoiceResource($invoice->fresh([
            'customer.user', 'items', 'payments', 'originalInvoice', 'cancellationInvoice', 'dunnings',
        ]));
    }

    /**
     * Cancel (storno) an invoice: creates a new cancellation invoice with
     * negated amounts and marks the original invoice as cancelled. Both
     * changes happen atomically inside a single DB transaction.
     *
     * The cancellation invoice's number generation + creation step is
     * retried up to {@see self::CANCEL_MAX_ATTEMPTS} times on a
     * `UniqueConstraintViolationException` (same documented race as
     * {@see self::finalize()}, see `task-T03.notes.md` /
     * `task-T04.notes.md`). Each retry attempt runs in its own **nested**
     * `DB::transaction()` call. Laravel maps a nested transaction to a SQL
     * `SAVEPOINT` (see `ManagesTransactions::createTransaction()` /
     * `performRollBack()`, driver-agnostic — supported by MySQL,
     * PostgreSQL and SQLite alike). This matters specifically for
     * PostgreSQL: unlike MySQL, PostgreSQL poisons an *entire* transaction
     * on any failed statement, so subsequent statements (e.g. the
     * original invoice's status update further down) would fail with
     * "current transaction is aborted" unless the failed attempt is
     * rolled back to a savepoint instead of the outer transaction. Since
     * `handleTransactionException()` calls `rollBack()` (which rolls back
     * to the current nesting level, i.e. the savepoint) before rethrowing,
     * only the failed attempt is undone — the outer transaction started
     * in this method remains healthy for the retry and for the subsequent
     * steps.
     */
    public function cancel(Invoice $invoice, InvoiceNumberGenerator $numberGenerator): InvoiceResource
    {
        $this->authorize('cancel', $invoice);

        $cancellationInvoice = DB::transaction(function () use ($invoice, $numberGenerator) {
            $cancellationInvoice = $this->createCancellationInvoiceWithRetry($invoice, $numberGenerator);

            foreach ($invoice->items as $item) {
                $cancellationInvoice->items()->create([
                    'description' => $item->description,
                    'quantity' => -$item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'amount' => -$item->amount,
                ]);
            }

            $invoice->update(['status' => 'cancelled']);

            return $cancellationInvoice;
        });

        return new InvoiceResource($cancellationInvoice->fresh([
            'customer.user', 'items', 'payments', 'originalInvoice', 'cancellationInvoice', 'dunnings',
        ]));
    }

    /**
     * Creates the cancellation invoice header (without items) for
     * {@see self::cancel()}, retrying on a unique-constraint collision of
     * the generated invoice number. See {@see self::cancel()} for why each
     * attempt runs in its own nested `DB::transaction()`.
     */
    private function createCancellationInvoiceWithRetry(Invoice $invoice, InvoiceNumberGenerator $numberGenerator): Invoice
    {
        $cancellationInvoice = null;

        for ($attempt = 1; $attempt <= self::CANCEL_MAX_ATTEMPTS; $attempt++) {
            try {
                $cancellationInvoice = DB::transaction(function () use ($invoice, $numberGenerator): Invoice {
                    return Invoice::create([
                        'customer_id' => $invoice->customer_id,
                        'invoice_number' => $numberGenerator->generate(),
                        'original_invoice_id' => $invoice->id,
                        'status' => 'sent',
                        'total_amount' => -$invoice->total_amount,
                        'issue_date' => today(),
                        'due_date' => today(),
                        'notes' => "Storno zu Rechnung {$invoice->invoice_number}",
                    ]);
                });

                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === self::CANCEL_MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        return $cancellationInvoice;
    }

    /**
     * Get overdue invoices.
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
        return $pdf->download($invoice->invoice_number.'.pdf');
    }
}
