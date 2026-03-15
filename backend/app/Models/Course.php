<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Course Model
 *
 * Represents a training course offered by the dog school.
 *
 * @property int $id
 * @property int $trainer_id
 * @property string $name
 * @property string|null $description
 * @property string $course_type
 * @property int $max_participants
 * @property int|null $duration_minutes
 * @property float|null $price_per_session
 * @property int|null $total_sessions
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $trainer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TrainingSession> $sessions
 */
class Course extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'trainer_id',
        'name',
        'description',
        'course_type',
        'max_participants',
        'duration_minutes',
        'price_per_session',
        'total_sessions',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_participants' => 'integer',
            'duration_minutes' => 'integer',
            'price_per_session' => 'float',
            'total_sessions' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the trainer who is teaching this course.
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * Get all training sessions for this course.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    /**
     * Check if the course is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the course is full.
     */
    public function isFull(): bool
    {
        $upcomingSessions = $this->sessions()
            ->where('status', 'scheduled')
            ->get();

        foreach ($upcomingSessions as $session) {
            if (!$session->isFull()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope a query to only include active courses.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include courses of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('course_type', $type);
    }
}
