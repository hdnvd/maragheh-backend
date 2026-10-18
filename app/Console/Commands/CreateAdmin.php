<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=مدیر} {--password=}';

    protected $description = 'Create or reset an admin panel account';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password (min 8 chars)');
        if (! $password || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }
        $user = User::updateOrCreate(
            ['email' => strtolower($this->argument('email'))],
            ['name' => $this->option('name'), 'password' => $password],
        );
        $user->tokens()->delete();
        $this->info("Admin {$user->email} is ready.");

        return self::SUCCESS;
    }
}
