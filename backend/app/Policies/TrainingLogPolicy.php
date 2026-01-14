<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TrainingLog;
use App\Models\User;

class TrainingLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Filter applied in controller
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TrainingLog $trainingLog): bool
    {
        // Admins and trainers can view all logs
        if ($user->role === 'admin' || $user->role === 'trainer') {
            return true;
        }

        // Customers can view logs for their own dogs
        if ($user->role === 'customer') {
            // User has a customer profile relationship
            return $user->customer && $trainingLog->dog->customer_id === $user->customer->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only trainers and admins can create training logs
        return $user->role === 'trainer' || $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TrainingLog $trainingLog): bool
    {
        // Admins can update any log
        if ($user->role === 'admin') {
            return true;
        }

        // Trainers can only update their own logs
        if ($user->role === 'trainer') {
            return $trainingLog->trainer_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TrainingLog $trainingLog): bool
    {
        // Only admins can delete training logs
        return $user->role === 'admin';
    }
}
