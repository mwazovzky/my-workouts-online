<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create
        {name : Display name}
        {email : Email address, must be unused}
        {password : Password, at least 8 characters}
        {--verified : Mark the email as verified so no verification link is needed}';

    protected $description = 'Create an account without the registration email round-trip (App Review demo account, first admin)';

    public function handle(): int
    {
        $input = [
            'name' => (string) $this->argument('name'),
            'email' => (string) $this->argument('email'),
            'password' => (string) $this->argument('password'),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create($input);

        if ($this->option('verified')) {
            $user->markEmailAsVerified();
        }

        $this->info("{$user->email} created".($this->option('verified') ? ' (verified).' : '; a verification email has not been sent.'));

        return self::SUCCESS;
    }
}
