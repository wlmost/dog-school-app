<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * User Policy
 *
 * Defines authorization rules for user-related actions.
 */
class UserPolicy
{
    /**
     * Determine if the given user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTrainer();
    }

    /**
     * Determine if the given user can view the specified user.
     */
    public function view(User $user, User $model): bool
    {
        // Admins and trainers can view all users
        if ($user->isAdmin() || $user->isTrainer()) {
            return true;
        }

        // Users can view their own profile
        return $user->id === $model->id;
    }

    /**
     * Determine if the given user can create users.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the given user can update the specified user.
     */
    public function update(User $user, User $model): bool
    {
        // Admins can update all users
        if ($user->isAdmin()) {
            return true;
        }

        // Users can update their own profile
        return $user->id === $model->id;
    }

    /**
     * Determine if the given user can delete the specified user.
     */
    public function delete(User $user, User $model): bool
    {
        // Only admins can delete users
        // Cannot delete yourself
        return $user->isAdmin() && $user->id !== $model->id;
    }

    /**
     * Determine if the given user can restore the specified user.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the given user can permanently delete the specified user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }
}
