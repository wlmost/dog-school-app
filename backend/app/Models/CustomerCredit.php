<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CustomerCredit Model
 *
 * Represents a customer's purchased credit package with remaining credits.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $credit_package_id
 * @property int $remaining_credits
 * @property \Illuminate\Support\Carbon $purchase_date
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read CreditPackage $package
 */
class CustomerCredit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'credit_package_id',
        'total_credits',
        'remaining_credits',
        'purchase_date',
        'expiration_date',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_credits' => 'integer',
            'remaining_credits' => 'integer',
            'purchase_date' => 'date',
            'expiration_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the customer who owns this credit.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the credit package this is based on.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class, 'credit_package_id');
    }

    /**
     * Check if the credit package is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' 
            && $this->remaining_credits > 0
            && (!$this->expiration_date || $this->expiration_date->isFuture());
    }

    /**
     * Check if the credit package is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    /**
     * Deduct credits from the package.
     */
    public function useCredit(int $amount = 1): bool
    {
        if ($this->remaining_credits < $amount) {
            return false;
        }

        $this->remaining_credits -= $amount;
        
        if ($this->remaining_credits === 0) {
            $this->status = 'used';
        }

        $this->save();

        return true;
    }

    /**
     * Scope a query to only include active credits.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('remaining_credits', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiration_date')
                  ->orWhere('expiration_date', '>', now());
            });
    }

    /**
     * Scope a query to only include expired credits.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiration_date')
            ->where('expiration_date', '<', now());
    }
}
