<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class GrantAdminCommand extends Command
{
    protected $signature = 'user:grant-admin {email : Email of a registered, verified user}';

    protected $description = 'Grant the Admin role to a registered user with a verified email';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user is registered with the email {$email}.");

            return self::FAILURE;
        }

        if (! $user->hasVerifiedEmail()) {
            $this->error("{$email} has not verified the email address. Nothing was changed.");

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->info("{$email} is already an admin.");

            return self::SUCCESS;
        }

        $role = Role::firstOrCreate(
            ['name' => 'Admin'],
            ['description' => 'Administrator role with full access'],
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->info("{$email} is now an admin.");

        return self::SUCCESS;
    }
}
