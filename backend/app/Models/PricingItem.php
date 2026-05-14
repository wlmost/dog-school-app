<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PricingItem Model
 *
 * Represents a single entry in the dog school's public pricing overview.
 *
 * @property int $id
 * @property string $category
 * @property string $title
 * @property string $price
 * @property string|null $unit
 * @property string|null $description
 * @property bool $is_from_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PricingItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category',
        'title',
        'price',
        'unit',
        'description',
        'is_from_price',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price'         => 'decimal:2',
            'is_from_price' => 'boolean',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }
}
