<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer Model
 *
 * Represents a customer in the dog school system.
 * Each customer is linked to a user account and can have multiple dogs.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $country
 * @property string|null $emergency_contact
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Dog> $dogs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Booking> $bookings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomerCredit> $credits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices
 */
class Customer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'trainer_id',
        'address_line1',
        'address_line2',
        'postal_code',
        'city',
        'country',
        'emergency_contact',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the customer profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the assigned trainer for this customer.
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * Get all dogs belonging to this customer.
     */
    public function dogs(): HasMany
    {
        return $this->hasMany(Dog::class);
    }

    /**
     * Get all bookings made by this customer.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get all credit packages owned by this customer.
     */
    public function credits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class);
    }

    /**
     * Get all invoices for this customer.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the customer's full address.
     */
    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->postal_code ? "{$this->postal_code} {$this->city}" : $this->city,
            $this->country,
        ]);

        return !empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Scope a query to only include customers with active credits.
     */
    public function scopeWithActiveCredits($query)
    {
        return $query->whereHas('credits', function ($q) {
            $q->where('status', 'active')
              ->where('remaining_credits', '>', 0);
        });
    }
}
