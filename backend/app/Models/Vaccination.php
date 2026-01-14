<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vaccination Model
 *
 * Represents a vaccination record for a dog.
 *
 * @property int $id
 * @property int $dog_id
 * @property string $vaccination_type
 * @property \Illuminate\Support\Carbon $vaccination_date
 * @property \Illuminate\Support\Carbon|null $next_due_date
 * @property string|null $veterinarian
 * @property string|null $document_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Dog $dog
 */
class Vaccination extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dog_id',
        'vaccination_type',
        'vaccination_date',
        'next_due_date',
        'veterinarian',
        'document_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vaccination_date' => 'date',
            'next_due_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the dog that this vaccination belongs to.
     */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    /**
     * Check if the vaccination is due or overdue.
     */
    public function isDue(): bool
    {
        if (!$this->next_due_date) {
            return false;
        }

        return $this->next_due_date->isPast() || $this->next_due_date->isToday();
    }

    /**
     * Check if the vaccination is overdue.
     */
    public function isOverdue(): bool
    {
        if (!$this->next_due_date) {
            return false;
        }

        return $this->next_due_date->isPast();
    }

    /**
     * Scope a query to only include overdue vaccinations.
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotNull('next_due_date')
            ->where('next_due_date', '<', now());
    }

    /**
     * Scope a query to only include vaccinations due soon (within 30 days).
     */
    public function scopeDueSoon($query)
    {
        return $query->whereNotNull('next_due_date')
            ->whereBetween('next_due_date', [now(), now()->addDays(30)]);
    }
}
