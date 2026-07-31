<?php

namespace App\Policies;

use App\Models\User; // Import the User model
use App\Models\Client; // Import the Client model
use App\Enums\UserRoleEnum; // Import your UserRoleEnum
use Illuminate\Auth\Access\Response;

class ClientPolicy
{
    /**
     * Determine whether the user can view any clients (e.g., list all clients).
     * This is typically used for index methods or global client visibility.
     */
    public function viewAny(User $user): bool
    {
        // Only Admins and Employees can view lists of clients
        return $user->role === UserRoleEnum::Admin || $user->role === UserRoleEnum::Employee;
    }

    /**
     * Determine whether the user can view the specified client.
     */
    public function view(User $user, Client $client): bool
    {
        // Admins can view any client
        if ($user->role === UserRoleEnum::Admin) {
            return true;
        }

        // Employees can view clients they are assigned to
        if ($user->role === UserRoleEnum::Employee) {
            return $user->id === $client->employee_id;
        }

        // Customers (clients themselves) cannot view client records in this context.
        // They should access their own data via their user profile or specific portal routes.
        return false;
    }

    /**
     * Determine whether the user can create clients.
     */
    public function create(User $user): bool
    {
        // Only Admins and Employees can create clients
        return $user->role === UserRoleEnum::Admin || $user->role === UserRoleEnum::Employee;
    }


    /**
     * Determine whether the user can update the specified client.
     */
    public function update(User $user, Client $client): bool
    {
        // Admins can update any client
        if ($user->role === UserRoleEnum::Admin) {
            return true;
        }

        // Employees can update clients they are assigned to
        if ($user->role === UserRoleEnum::Employee) {
            return $user->id === $client->employee_id;
        }

        // Customers cannot update client records
        return false;
    }

    /**
     * Determine whether the user can delete the specified client.
     */
    public function delete(User $user, Client $client): bool
    {
        // Admins can delete any client
        if ($user->role === UserRoleEnum::Admin) {
            return true;
        }

        // Employees can delete clients they are assigned to
        if ($user->role === UserRoleEnum::Employee) {
            return $user->id === $client->employee_id;
        }

        // Customers cannot delete client records
        return false;
    }

    // If you have restore/forceDelete methods for soft deletes, add them here too.
}