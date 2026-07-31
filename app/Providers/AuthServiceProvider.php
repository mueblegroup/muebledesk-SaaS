<?php

namespace App\Providers;

use App\Models\Client; // Import your Client model
use App\Models\User; // Import your User model
use App\Policies\ClientPolicy; // Import your ClientPolicy
// use App\Policies\UserPolicy; // If you have a UserPolicy as well

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Client::class => ClientPolicy::class, // Register your ClientPolicy here
        // User::class => UserPolicy::class, // Example for UserPolicy
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}