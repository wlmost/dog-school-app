<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TrainingLog Model
 *
 * Represents a training log entry for a dog's progress documentation.
 *
 * @property int $id
 * @property int $dog_id
 * @property int|null $training_session_id
 * @property int $trainer_id
 * @property \Illuminate\Support\Carbon $log_date
 * @property string $title
 * @property string|null $notes
 * @property string|null $recommendations
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Dog $dog
 * @property-read TrainingSession|null $session
 * @property-read User $trainer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TrainingAttachment> $attachments
 */
class TrainingLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dog_id',
        'training_session_id',
        'trainer_id',
        'progress_notes',
        'behavior_notes',
        'homework',
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
     * Get the dog this log is for.
     */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    /**
     * Get the training session this log is associated with (if any).
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /**
     * Get the trainer who created this log.
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * Get all attachments for this log.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TrainingAttachment::class, 'training_log_id');
    }

    /**
     * Scope a query to only include logs from a specific date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('log_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include recent logs.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('log_date', '>=', now()->subDays($days))
            ->orderBy('log_date', 'desc');
    }
}
