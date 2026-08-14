<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * InvoiceDunning Model
 *
 * Represents a single dunning (reminder) level raised against an invoice,
 * including the associated fee. Multiple dunning levels can exist per
 * invoice, analogous to the existing Payment model.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $level
 * @property Carbon $dunning_date
 * @property float $fee_amount
 * @property int|null $fee_invoice_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Invoice|null $feeInvoice
 */
class InvoiceDunning extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'level',
        'dunning_date',
        'fee_amount',
        'fee_invoice_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dunning_date' => 'date',
            'fee_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the invoice this dunning belongs to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the standalone fee invoice document created for this dunning,
     * if any.
     */
    public function feeInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'fee_invoice_id');
    }
}
