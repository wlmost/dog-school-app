<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AnamnesisTemplate;
use App\Models\User;

class AnamnesisTemplatePolicy
{
    /**
     * Determine whether the user can view any anamnesis templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->role !== 'admin';
    }

    /**
     * Determine whether the user can view the anamnesis template.
     */
    public function view(User $user, AnamnesisTemplate $anamnesisTemplate): bool
    {
        return $user->role !== 'admin';
    }

    /**
     * Determine whether the user can create anamnesis templates.
     */
    public function create(User $user): bool
    {
        return $user->role === 'trainer';
    }

    /**
     * Determine whether the user can update the anamnesis template.
     */
    public function update(User $user, AnamnesisTemplate $anamnesisTemplate): bool
    {
        // Trainers can only update their own templates
        return $user->role === 'trainer' && $anamnesisTemplate->trainer_id === $user->id;
    }

    /**
     * Determine whether the user can delete the anamnesis template.
     */
    public function delete(User $user, AnamnesisTemplate $anamnesisTemplate): bool
    {
        // Trainers can delete their own templates
        return $user->role === 'trainer' && $anamnesisTemplate->trainer_id === $user->id;
    }
}
