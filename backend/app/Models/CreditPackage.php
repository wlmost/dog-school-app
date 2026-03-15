<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CreditPackage Model
 *
 * Represents a credit package that customers can purchase (e.g., 5-session or 10-session cards).
 *
 * @property int $id
 * @property string $name
 * @property int $total_credits
 * @property float $price
 * @property int|null $validity_days
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomerCredit> $customerCredits
 */
class CreditPackage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'total_credits',
        'price',
        'validity_days',
        'description',
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
            'price' => 'float',
            'validity_days' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get all customer credit purchases of this package.
     */
    public function customerCredits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class);
    }

    /**
     * Get the price per credit.
     */
    public function getPricePerCreditAttribute(): float
    {
        return $this->total_credits > 0 ? $this->price / $this->total_credits : 0;
    }
}
