<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AnamnesisResponse Model
 *
 * Represents a completed anamnesis form for a specific dog.
 *
 * @property int $id
 * @property int $dog_id
 * @property int $template_id
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $completed_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Dog $dog
 * @property-read AnamnesisTemplate $template
 * @property-read User|null $completedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnamnesisAnswer> $answers
 */
class AnamnesisResponse extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dog_id',
        'template_id',
        'completed_at',
        'completed_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the dog this response is for.
     */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    /**
     * Get the template used for this response.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AnamnesisTemplate::class, 'template_id');
    }

    /**
     * Get the user who completed this response.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get all answers in this response.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AnamnesisAnswer::class, 'response_id');
    }

    /**
     * Check if the response is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Scope a query to only include completed responses.
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Scope a query to only include incomplete responses.
     */
    public function scopeIncomplete($query)
    {
        return $query->whereNull('completed_at');
    }
}
