<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TrainingSession Model
 *
 * Represents a single training session within a course or standalone session.
 *
 * @property int $id
 * @property int|null $course_id
 * @property int $trainer_id
 * @property \Illuminate\Support\Carbon $session_date
 * @property string $start_time
 * @property string $end_time
 * @property string|null $location
 * @property int $max_participants
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Course|null $course
 * @property-read User $trainer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Booking> $bookings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TrainingLog> $trainingLogs
 */
class TrainingSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_id',
        'trainer_id',
        'session_date',
        'start_time',
        'end_time',
        'location',
        'max_participants',
        'status',
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
            'session_date' => 'date',
            'max_participants' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the course this session belongs to (if any).
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the trainer leading this session.
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * Get all bookings for this session.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get all training logs for this session.
     */
    public function trainingLogs(): HasMany
    {
        return $this->hasMany(TrainingLog::class);
    }

    /**
     * Check if the session is full.
     */
    public function isFull(): bool
    {
        return $this->bookings()
            ->where('status', 'confirmed')
            ->count() >= $this->max_participants;
    }

    /**
     * Get the number of available spots.
     */
    public function getAvailableSpotsAttribute(): int
    {
        $confirmed = $this->bookings()
            ->where('status', 'confirmed')
            ->count();

        return max(0, $this->max_participants - $confirmed);
    }

    /**
     * Check if the session is in the past.
     */
    public function isPast(): bool
    {
        return $this->session_date->isPast();
    }

    /**
     * Scope a query to only include upcoming sessions.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('session_date', '>=', now())
            ->where('status', 'scheduled')
            ->orderBy('session_date')
            ->orderBy('start_time');
    }

    /**
     * Scope a query to only include past sessions.
     */
    public function scopePast($query)
    {
        return $query->where('session_date', '<', now())
            ->orderBy('session_date', 'desc');
    }

    /**
     * Scope a query to only include sessions for a specific trainer.
     */
    public function scopeForTrainer($query, int $trainerId)
    {
        return $query->where('trainer_id', $trainerId);
    }
}
