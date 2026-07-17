<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Announcement Policy
 *
 * Defines authorization logic for announcement operations. Only admins
 * may manage announcements.
 */
class AnnouncementPolicy
{
    /**
     * Determine whether the user can view any announcements.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the announcement.
     */
    public function view(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create announcements.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the announcement.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the announcement.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
    }
}
