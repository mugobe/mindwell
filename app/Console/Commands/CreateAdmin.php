<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the first admin account on a fresh server (go-live, 2026-10-31). Asks for the
 * details on screen so no password ends up in shell history or in the code.
 *
 *   php artisan mindwell:create-admin
 *
 * If the email already belongs to a user, it offers to make that user an admin instead.
 */
class CreateAdmin extends Command
{
    protected $signature = 'mindwell:create-admin';

    protected $description = 'Create a Mindwell admin account (asks for name, email and password)';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Full name'));
        $email = strtolower(trim((string) $this->ask('Email')));

        if ($existing = User::where('email', $email)->first()) {
            if ($existing->role === 'admin') {
                $this->info("{$email} is already an admin.");

                return self::SUCCESS;
            }
            if (! $this->confirm("{$email} already has a {$existing->role} account. Make it an admin?", false)) {
                return self::FAILURE;
            }
            $existing->forceFill(['role' => 'admin', 'status' => 'active'])->save();
            $this->info("{$email} is now an admin.");

            return self::SUCCESS;
        }

        $password = (string) $this->secret('Password (at least 10 characters)');
        $confirm = (string) $this->secret('Type the password again');

        $v = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            ['name' => 'required|max:255', 'email' => 'required|email|max:255', 'password' => 'required|min:10|confirmed'],
        );
        if ($v->fails()) {
            foreach ($v->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();

        $this->info("Admin account created for {$email}. You can log in now.");

        return self::SUCCESS;
    }
}
