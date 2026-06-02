<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Create user (instructors)
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * View any user
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isInstructor();
    }

    /**
     * View specific user
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id ||
               $user->isAdmin() ||
               $user->isInstructor();
    }

    /**
     * Update user
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can update anyone
        return $user->isAdmin();
    }

    /**
     * Delete/deactivate user
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }
}
