<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'contact@mueblegroup.com'], // unique identifier
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Praveen_99'), // choose a strong password
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}

