<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AnamnesisResponse;
use App\Models\User;

class AnamnesisResponsePolicy
{
    /**
     * Determine whether the user can view any anamnesis responses.
     */
    public function viewAny(User $user): bool
    {
        return $user->role !== 'admin';
    }

    /**
     * Determine whether the user can view the anamnesis response.
     */
    public function view(User $user, AnamnesisResponse $anamnesisResponse): bool
    {
        // Trainers can view any response
        if ($user->role === 'trainer') {
            return true;
        }

        // Customers can only view responses for their own dogs
        if ($user->role === 'customer') {
            return $anamnesisResponse->dog->customer_id === $user->customer->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create anamnesis responses.
     */
    public function create(User $user): bool
    {
        return $user->role !== 'admin';
    }

    /**
     * Determine whether the user can update the anamnesis response.
     */
    public function update(User $user, AnamnesisResponse $anamnesisResponse): bool
    {
        // Trainers can update any response
        if ($user->role === 'trainer') {
            return true;
        }

        // Customers can only update responses for their own dogs
        if ($user->role === 'customer') {
            return $anamnesisResponse->dog->customer_id === $user->customer->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the anamnesis response.
     * Anamnesis responses cannot be deleted to preserve the audit trail.
     */
    public function delete(User $user, AnamnesisResponse $anamnesisResponse): bool
    {
        // Nobody can delete anamnesis responses
        return false;
    }
}
