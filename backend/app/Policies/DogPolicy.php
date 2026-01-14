<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Dog;
use App\Models\User;

/**
 * Dog Policy
 *
 * Defines authorization logic for dog operations.
 */
class DogPolicy
{
    /**
     * Determine whether the user can view any dogs.
     */
    public function viewAny(User $user): bool
    {
        // Admins, trainers, and customers can view dogs (filtered in controller)
        return $user->isAdmin() || $user->isTrainer() || $user->isCustomer();
    }

    /**
     * Determine whether the user can view the dog.
     */
    public function view(User $user, Dog $dog): bool
    {
        // Admins and trainers can view any dog
        if ($user->isAdminOrTrainer()) {
            return true;
        }

        // Customers can only view their own dogs
        return $user->isCustomer() && $dog->customer->user_id === $user->id;
    }

    /**
     * Determine whether the user can create dogs.
     */
    public function create(User $user): bool
    {
        // Only admins and trainers can create dogs
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can update the dog.
     */
    public function update(User $user, Dog $dog): bool
    {
        // Only admins and trainers can update dogs
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can delete the dog.
     */
    public function delete(User $user, Dog $dog): bool
    {
        // Only admins can delete dogs
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the dog.
     */
    public function restore(User $user, Dog $dog): bool
    {
        // Only admins can restore dogs
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the dog.
     */
    public function forceDelete(User $user, Dog $dog): bool
    {
        // Only admins can force delete dogs
        return $user->isAdmin();
    }
}
