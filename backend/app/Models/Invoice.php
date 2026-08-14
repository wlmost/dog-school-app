<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\DunningFeeSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Invoice Model
 *
 * Represents an invoice for a customer.
 *
 * @property int $id
 * @property int $customer_id
 * @property string|null $invoice_number
 * @property int|null $original_invoice_id
 * @property string|null $document_type
 * @property Carbon $invoice_date
 * @property Carbon $due_date
 * @property float $subtotal
 * @property float $tax_rate
 * @property float $tax_amount
 * @property float $total
 * @property string $status
 * @property Carbon|null $payment_date
 * @property string|null $payment_method
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, InvoiceDunning> $dunnings
 * @property-read Invoice|null $originalInvoice
 * @property-read Invoice|null $cancellationInvoice
 * @property-read Collection<int, Invoice> $dunningFeeInvoices
 * @property-read int|null $nextDunningLevel
 * @property-read float|null $nextDunningFeeAmount
 */
class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'invoice_number',
        'original_invoice_id',
        'document_type',
        'status',
        'total_amount',
        'issue_date',
        'due_date',
        'paid_date',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
            'total_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the customer this invoice belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all items for this invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get all payments for this invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get all dunnings (reminder levels) for this invoice.
     */
    public function dunnings(): HasMany
    {
        return $this->hasMany(InvoiceDunning::class);
    }

    /**
     * Get the invoice this invoice is a cancellation of, if any.
     */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    /**
     * Get the cancellation invoice created for this invoice, if any.
     *
     * Filtered by `document_type` (see design.md Decision D1): without
     * this filter, a dunning-fee document (which also sets
     * `original_invoice_id`, see {@see dunningFeeInvoices()}) could be
     * returned here instead of the real cancellation invoice.
     */
    public function cancellationInvoice(): HasOne
    {
        return $this->hasOne(self::class, 'original_invoice_id')
            ->where('document_type', 'cancellation');
    }

    /**
     * Get the dunning-fee documents created for this invoice, if any. A
     * fee invoice is a standalone `Invoice` (own invoice number, one
     * `InvoiceItem` line) that references this invoice via
     * `original_invoice_id` and `document_type = 'dunning_fee'`.
     */
    public function dunningFeeInvoices(): HasMany
    {
        return $this->hasMany(self::class, 'original_invoice_id')
            ->where('document_type', 'dunning_fee');
    }

    /**
     * Check if the invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return ! in_array($this->status, ['paid', 'cancelled']) && $this->due_date->isPast();
    }

    /**
     * Get the total amount paid for this invoice.
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Get the remaining balance.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return max(0, (float) $this->total_amount - $this->total_paid);
    }

    /**
     * Get the highest dunning level reached so far, or null if none exists.
     */
    public function getDunningLevelAttribute(): ?int
    {
        $highestLevel = $this->dunnings->max('level');

        return $highestLevel !== null ? (int) $highestLevel : null;
    }

    /**
     * Get the date of the most recent dunning, or null if none exists.
     */
    public function getRemindedAtAttribute(): ?Carbon
    {
        return $this->dunnings->sortByDesc('dunning_date')->first()?->dunning_date;
    }

    /**
     * Get the dunning level that the next triggered dunning would reach,
     * or null if the maximum level is already reached (no further
     * app-internal dunning possible, see `config/invoicing.php`).
     */
    public function getNextDunningLevelAttribute(): ?int
    {
        return DunningFeeSchedule::nextLevel($this->dunning_level);
    }

    /**
     * Get the fee that would be charged for the next dunning level, or
     * null if the maximum level is already reached.
     */
    public function getNextDunningFeeAmountAttribute(): ?float
    {
        $nextLevel = $this->next_dunning_level;

        return $nextLevel !== null ? DunningFeeSchedule::feeForLevel($nextLevel) : null;
    }

    /**
     * Scope a query to only include unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->whereNotIn('status', ['paid', 'cancelled']);
    }

    /**
     * Scope a query to only include overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', ['paid', 'cancelled'])
            ->where('due_date', '<', now());
    }

    /**
     * Scope a query to only include paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
