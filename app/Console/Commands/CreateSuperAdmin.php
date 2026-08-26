<?php

namespace App\Console\Commands;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-superadmin {email : Superadmin email address} {--name= : Display name}';

    protected $description = 'Create or promote a user to platform superadmin without storing credentials in source control';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) ($this->option('name') ?: 'Super Admin'));

        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            [
                'email' => $email,
                'name' => $name,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $created = ! $user->exists;

        $user->name = $name;
        $user->password = Hash::make($password);
        $user->role = UserRoleEnum::SuperAdmin;
        $user->email_verified_at ??= now();
        $user->save();

        $this->info($created
            ? "Superadmin {$email} created successfully."
            : "User {$email} promoted to superadmin and password updated successfully."
        );

        return self::SUCCESS;
    }
}
