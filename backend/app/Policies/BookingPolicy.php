<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Booking Policy
 *
 * Defines authorization logic for booking operations.
 */
class BookingPolicy
{
    /**
     * Determine whether the user can view any bookings.
     */
    public function viewAny(User $user): bool
    {
        // Admins and trainers can view all bookings
        // Customers can view their own bookings (filtered in controller)
        return true;
    }

    /**
     * Determine whether the user can view the booking.
     */
    public function view(User $user, Booking $booking): bool
    {
        // Admins and trainers can view any booking
        if ($user->isAdminOrTrainer()) {
            return true;
        }

        // Customers can only view their own bookings
        return $user->isCustomer() && $booking->customer->user_id === $user->id;
    }

    /**
     * Determine whether the user can create bookings.
     *
     * Customers can create their own bookings.
     * Trainers can create bookings on behalf of customers.
     * Admins have read-only access and cannot create bookings.
     */
    public function create(User $user): bool
    {
        return $user->isCustomer() || $user->isTrainer();
    }

    /**
     * Determine whether the user can update the booking.
     *
     * Only trainers can update bookings (mark attendance, change status).
     * Admins have read-only access.
     */
    public function update(User $user, Booking $booking): bool
    {
        return $user->isTrainer();
    }

    /**
     * Determine whether the user can cancel the booking.
     *
     * Trainers can cancel any booking on behalf of customers.
     * Customers can cancel their own bookings if not yet attended.
     * Admins have read-only access and cannot cancel bookings.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        // Trainers can cancel any booking
        if ($user->isTrainer()) {
            return true;
        }

        // Customers can cancel their own bookings if not yet attended
        if ($user->isCustomer() && $booking->customer->user_id === $user->id) {
            return $booking->status !== 'cancelled' && !$booking->attended;
        }

        return false;
    }

    /**
     * Determine whether the user can approve a cancellation request.
     *
     * Only trainers may approve cancellation requests.
     */
    public function approveCancellation(User $user, Booking $booking): bool
    {
        return $user->isTrainer();
    }

    /**
     * Determine whether the user can delete the booking.
     */
    public function delete(User $user, Booking $booking): bool
    {
        // Only admins can delete bookings
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the booking.
     */
    public function restore(User $user, Booking $booking): bool
    {
        // Only admins can restore bookings
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the booking.
     */
    public function forceDelete(User $user, Booking $booking): bool
    {
        // Only admins can force delete bookings
        return $user->isAdmin();
    }
}
