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
        return true;
    }

    /**
     * Determine whether the user can view the anamnesis template.
     */
    public function view(User $user, AnamnesisTemplate $anamnesisTemplate): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create anamnesis templates.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['trainer', 'admin']);
    }

    /**
     * Determine whether the user can update the anamnesis template.
     */
    public function update(User $user, AnamnesisTemplate $anamnesisTemplate): bool
    {
        // Admins can update any template
        if ($user->role === 'admin') {
            return true;
        }

        // Trainers can only update their own templates
        if ($user->role === 'trainer') {
            return $anamnesisTemplate->trainer_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the anamnesis template.
     */
    public function delete(User $user, AnamnesisTemplate $anamnesisTemplate): bool
    {
        // Only admins can delete templates
        return $user->role === 'admin';
    }
}
