<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

/**
 * Course Policy
 *
 * Defines authorization logic for course operations.
 */
class CoursePolicy
{
    /**
     * Determine whether the user can view any courses.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view courses
        return true;
    }

    /**
     * Determine whether the user can view the course.
     */
    public function view(User $user, Course $course): bool
    {
        // All authenticated users can view courses
        return true;
    }

    /**
     * Determine whether the user can create courses.
     */
    public function create(User $user): bool
    {
        // Only admins and trainers can create courses
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can update the course.
     */
    public function update(User $user, Course $course): bool
    {
        // Admins can update any course
        if ($user->isAdmin()) {
            return true;
        }

        // Trainers can update their own courses
        return $user->isTrainer() && $course->trainer_id === $user->id;
    }

    /**
     * Determine whether the user can delete the course.
     */
    public function delete(User $user, Course $course): bool
    {
        // Only admins can delete courses
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the course.
     */
    public function restore(User $user, Course $course): bool
    {
        // Only admins can restore courses
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the course.
     */
    public function forceDelete(User $user, Course $course): bool
    {
        // Only admins can force delete courses
        return $user->isAdmin();
    }
}
