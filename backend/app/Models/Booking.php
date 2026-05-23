<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Booking Model
 *
 * Represents a booking for a training session.
 *
 * @property int $id
 * @property int $training_session_id
 * @property int $customer_id
 * @property int $dog_id
 * @property int|null $course_run_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $booking_date
 * @property bool|null $attended
 * @property string|null $notes
 * @property string|null $cancellation_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read TrainingSession $session
 * @property-read Customer $customer
 * @property-read Dog $dog
 * @property-read CourseRun|null $courseRun
 */
class Booking extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'training_session_id',
        'customer_id',
        'dog_id',
        'course_run_id',
        'status',
        'booking_date',
        'attended',
        'cancellation_reason',
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
            'booking_date' => 'datetime',
            'attended' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the training session for this booking.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /**
     * Alias for session() relationship.
     */
    public function trainingSession(): BelongsTo
    {
        return $this->session();
    }

    /**
     * Get the customer who made this booking.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the dog for this booking.
     */
    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    /**
     * Get the course run this booking was made as part of (if any).
     *
     * @return BelongsTo<CourseRun, Booking>
     */
    public function courseRun(): BelongsTo
    {
        return $this->belongsTo(CourseRun::class);
    }

    /**
     * Check if the booking is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if the booking is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if a cancellation has been requested by the customer and is awaiting trainer approval.
     */
    public function isCancellationRequested(): bool
    {
        return $this->status === 'cancellation_requested';
    }

    /**
     * Calculate the latest point in time until which a cancellation is allowed.
     *
     * The deadline is derived from the related training session's date/time and
     * the course-level `cancellation_deadline_hours` setting (default 24 h).
     * Returns null when the related session or course cannot be resolved.
     */
    public function cancellationDeadline(): ?Carbon
    {
        $session = $this->relationLoaded('trainingSession')
            ? $this->trainingSession
            : $this->trainingSession()->with('course')->first();

        if (! $session) {
            return null;
        }

        $deadlineHours = $session->course?->cancellation_deadline_hours ?? 24;

        // Combine session_date (Carbon date) with start_time string.
        // When start_time is null, midnight (00:00:00) is used as a conservative
        // default – the deadline will then be `deadlineHours` before midnight of
        // the session date, which is stricter (earlier) than the actual session start.
        $sessionStart = Carbon::parse(
            $session->session_date->format('Y-m-d') . ' ' . ($session->start_time ?? '00:00:00')
        );

        return $sessionStart->subHours($deadlineHours);
    }

    /**
     * Determine whether the cancellation window is still open.
     */
    public function isCancellationAllowed(): bool
    {
        $deadline = $this->cancellationDeadline();

        return $deadline === null || now()->lessThanOrEqualTo($deadline);
    }

    /**
     * Scope a query to only include confirmed bookings.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to only include cancelled bookings.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to only include bookings with a pending cancellation request.
     */
    public function scopeCancellationRequested($query)
    {
        return $query->where('status', 'cancellation_requested');
    }

    /**
     * Scope a query to only include bookings where the dog attended.
     */
    public function scopeAttended($query)
    {
        return $query->where('attended', true);
    }
}
