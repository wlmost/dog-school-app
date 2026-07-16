<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DogRegistrationRequest Model
 *
 * Represents a customer's request to register a dog.
 * Admins review pending requests and either approve (creating a Dog) or reject them.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $name
 * @property string|null $breed
 * @property string|null $gender
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property bool $neutered
 * @property string|null $chip_number
 * @property \Illuminate\Support\Carbon|null $owner_since
 * @property string|null $age_at_acquisition
 * @property string|null $origin  breeder|shelter|private|unknown
 * @property string|null $notes
 * @property string $status  pending|approved|rejected
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read User|null $reviewer
 */
class DogRegistrationRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'breed',
        'gender',
        'date_of_birth',
        'neutered',
        'chip_number',
        'owner_since',
        'age_at_acquisition',
        'origin',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'neutered'      => 'boolean',
            'owner_since'   => 'date',
            'reviewed_at'   => 'datetime',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Get the customer that submitted the request.
     *
     * @return BelongsTo<Customer, DogRegistrationRequest>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the admin user who reviewed the request.
     *
     * @return BelongsTo<User, DogRegistrationRequest>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether this request is still pending review.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Determine whether this request has been approved.
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Determine whether this request has been rejected.
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
