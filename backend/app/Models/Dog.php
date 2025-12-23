<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dog Model
 *
 * Represents a dog enrolled in the dog school.
 * Each dog belongs to a customer and can have multiple training sessions, vaccinations, etc.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $name
 * @property string|null $breed
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $gender
 * @property bool $neutered
 * @property float|null $weight
 * @property string|null $chip_number
 * @property string|null $veterinarian_name
 * @property string|null $veterinarian_contact
 * @property string|null $medical_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Customer $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Vaccination> $vaccinations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Booking> $bookings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnamnesisResponse> $anamnesisResponses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TrainingLog> $trainingLogs
 */
class Dog extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'breed',
        'date_of_birth',
        'gender',
        'neutered',
        'weight',
        'chip_number',
        'veterinarian_name',
        'veterinarian_contact',
        'medical_notes',
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
            'neutered' => 'boolean',
            'weight' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that owns the dog.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all vaccinations for this dog.
     */
    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    /**
     * Get all bookings for this dog.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get all anamnesis responses for this dog.
     */
    public function anamnesisResponses(): HasMany
    {
        return $this->hasMany(AnamnesisResponse::class);
    }

    /**
     * Get all training logs for this dog.
     */
    public function trainingLogs(): HasMany
    {
        return $this->hasMany(TrainingLog::class);
    }

    /**
     * Get the dog's age in years.
     */
    protected function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return (int) floor($this->date_of_birth->diffInYears(now()));
    }

    /**
     * Scope a query to only include active (not deleted) dogs.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope a query to only include puppies (younger than 1 year).
     */
    public function scopePuppies($query)
    {
        return $query->whereNotNull('date_of_birth')
            ->where('date_of_birth', '>', now()->subYear());
    }
}
