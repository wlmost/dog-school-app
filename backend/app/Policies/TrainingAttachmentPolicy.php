<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TrainingAttachment;
use App\Models\User;

class TrainingAttachmentPolicy
{
    /**
     * Determine whether the user can view any training attachments.
     */
    public function viewAny(User $user): bool
    {
        // Admin and trainers can view all attachments
        // Customers can view their own dog's attachments (handled in controller)
        return true;
    }

    /**
     * Determine whether the user can view the training attachment.
     */
    public function view(User $user, TrainingAttachment $trainingAttachment): bool
    {
        // Admin and trainers can view any attachment
        if ($user->isAdmin() || $user->isTrainer()) {
            return true;
        }

        // Customers can only view attachments for their own dogs
        if ($user->isCustomer()) {
            $trainingAttachment->load('trainingLog.dog.customer');
            return $trainingAttachment->trainingLog->dog->customer->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create training attachments.
     */
    public function create(User $user): bool
    {
        // Only admin and trainers can upload attachments
        return $user->isAdmin() || $user->isTrainer();
    }

    /**
     * Determine whether the user can update the training attachment.
     */
    public function update(User $user, TrainingAttachment $trainingAttachment): bool
    {
        // Attachments cannot be updated, only deleted and re-uploaded
        return false;
    }

    /**
     * Determine whether the user can delete the training attachment.
     */
    public function delete(User $user, TrainingAttachment $trainingAttachment): bool
    {
        // Admin can delete any attachment
        if ($user->isAdmin()) {
            return true;
        }

        // Trainers can delete attachments they uploaded
        if ($user->isTrainer()) {
            $trainingAttachment->load('trainingLog');
            return $trainingAttachment->trainingLog->trainer_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the training attachment.
     */
    public function restore(User $user, TrainingAttachment $trainingAttachment): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the training attachment.
     */
    public function forceDelete(User $user, TrainingAttachment $trainingAttachment): bool
    {
        return $user->isAdmin();
    }
}
