<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CreditPackage;
use App\Models\User;

/**
 * Credit Package Policy
 *
 * Defines authorization logic for credit package operations.
 */
class CreditPackagePolicy
{
    /**
     * Determine whether the user can view any credit packages.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view credit packages
        return true;
    }

    /**
     * Determine whether the user can view the credit package.
     */
    public function view(User $user, CreditPackage $creditPackage): bool
    {
        // All authenticated users can view credit packages
        return true;
    }

    /**
     * Determine whether the user can create credit packages.
     */
    public function create(User $user): bool
    {
        // Only admins and trainers can create credit packages
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can update the credit package.
     */
    public function update(User $user, CreditPackage $creditPackage): bool
    {
        // Only admins and trainers can update credit packages
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can delete the credit package.
     */
    public function delete(User $user, CreditPackage $creditPackage): bool
    {
        // Only admins can delete credit packages
        return $user->isAdmin();
    }
}
