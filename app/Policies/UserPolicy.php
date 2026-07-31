<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRoleEnum; // Make sure this is imported
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    { 
        // Only Admins can view the list of all users
        return $user->role === UserRoleEnum::Admin;
    }

    /**
     * Determine whether the user can view the model.
     * (This would apply to a `users.show` route, e.g., viewing a specific user's profile)
     */
    public function view(User $user, User $model): bool
    {
        // Admins can view any user's profile
        // Users can view their own profile
        return $user->role === UserRoleEnum::Admin || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     * (Applies to `users.create` and `users.store` actions)
     */
    public function create(User $user): bool
    {
        // Only Admins can create new users
        return $user->role === UserRoleEnum::Admin;
    }

    /**
     * Determine whether the user can update the model.
     * (Applies to `users.edit` and `users.update` actions)
     */
    public function update(User $user, User $model): bool
    {
        // Admins can update any user
        // Users can update their own profile (e.g., change name, email)
        return $user->role === UserRoleEnum::Admin || $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     * (Applies to `users.destroy` action)
     */
    public function delete(User $user, User $model): bool
    {
        // Only Admins can delete users
        // Prevent an admin from deleting themselves (optional but good practice)
        return $user->role === UserRoleEnum::Admin && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can restore the model.
     * (Used with soft deletes)
     */
    public function restore(User $user, User $model): bool
    {
        return $user->role === UserRoleEnum::Admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * (Used with soft deletes and forceDelete)
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->role === UserRoleEnum::Admin;
    }
}