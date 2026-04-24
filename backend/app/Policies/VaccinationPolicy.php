<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Vaccination;
use App\Models\User;

/**
 * Vaccination Policy
 *
 * Defines authorization logic for vaccination operations.
 */
class VaccinationPolicy
{
    /**
     * Determine whether the user can view any vaccinations.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view vaccinations
        return true;
    }

    /**
     * Determine whether the user can view the vaccination.
     */
    public function view(User $user, Vaccination $vaccination): bool
    {
        // Admins and trainers can view any vaccination
        if ($user->isAdminOrTrainer()) {
            return true;
        }

        // Customers can only view their own dog's vaccinations
        return $user->isCustomer() && $vaccination->dog->customer->user_id === $user->id;
    }

    /**
     * Determine whether the user can create vaccinations.
     */
    public function create(User $user): bool
    {
        // Only admins and trainers can create vaccinations
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can update the vaccination.
     */
    public function update(User $user, Vaccination $vaccination): bool
    {
        // Only admins and trainers can update vaccinations
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can delete the vaccination.
     */
    public function delete(User $user, Vaccination $vaccination): bool
    {
        // Only admins can delete vaccinations
        return $user->isAdmin();
    }
}
