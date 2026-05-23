<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CourseRun Model
 *
 * Represents a specific run/instance of a course with its own set of sessions.
 * Customers book an entire CourseRun (all its sessions) at once.
 *
 * @property int $id
 * @property int $course_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Course $course
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TrainingSession> $sessions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Booking> $bookings
 */
class CourseRun extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_id',
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
            'start_date' => 'date',
            'end_date'   => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the course this run belongs to.
     *
     * @return BelongsTo<Course, CourseRun>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get all training sessions that belong to this run, ordered chronologically.
     *
     * @return HasMany<TrainingSession>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class)->orderBy('session_date');
    }

    /**
     * Get all bookings that were made as part of this run.
     *
     * @return HasMany<Booking>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Check whether the run is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
