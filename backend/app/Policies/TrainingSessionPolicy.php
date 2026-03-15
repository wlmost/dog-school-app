<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TrainingSession;
use App\Models\User;

/**
 * Training Session Policy
 *
 * Defines authorization logic for training session operations.
 */
class TrainingSessionPolicy
{
    /**
     * Determine whether the user can view any training sessions.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view training sessions
        return true;
    }

    /**
     * Determine whether the user can view the training session.
     */
    public function view(User $user, TrainingSession $trainingSession): bool
    {
        // All authenticated users can view training sessions
        return true;
    }

    /**
     * Determine whether the user can create training sessions.
     */
    public function create(User $user): bool
    {
        // Only admins and trainers can create training sessions
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can update the training session.
     */
    public function update(User $user, TrainingSession $trainingSession): bool
    {
        // Admins can update any training session
        if ($user->isAdmin()) {
            return true;
        }

        // Trainers can update their own training sessions
        return $user->isTrainer() && $trainingSession->trainer_id === $user->id;
    }

    /**
     * Determine whether the user can delete the training session.
     */
    public function delete(User $user, TrainingSession $trainingSession): bool
    {
        // Only admins can delete training sessions
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the training session.
     */
    public function restore(User $user, TrainingSession $trainingSession): bool
    {
        // Only admins can restore training sessions
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the training session.
     */
    public function forceDelete(User $user, TrainingSession $trainingSession): bool
    {
        // Only admins can force delete training sessions
        return $user->isAdmin();
    }
}
