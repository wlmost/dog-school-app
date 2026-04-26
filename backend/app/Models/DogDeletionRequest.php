<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DogDeletionRequest Model
 *
 * Represents a customer's request to delete their dog.
 * Admins review pending requests and either approve (deleting the Dog + sending email)
 * or reject them.
 *
 * @property int $id
 * @property int $dog_id
 * @property int $customer_id
 * @property string $dog_name
 * @property string $status  pending|approved|rejected
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Dog $dog
 * @property-read Customer $customer
 * @property-read User|null $reviewer
 */
class DogDeletionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'dog_id',
        'customer_id',
        'dog_name',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
