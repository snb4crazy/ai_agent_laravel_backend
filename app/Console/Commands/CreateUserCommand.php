<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Auth\UserRegistrationRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a user interactively from the CLI';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->ask('Name');
        $email = Str::lower((string) $this->ask('Email'));
        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], UserRegistrationRules::make());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return SymfonyCommand::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->email_verified_at = now();
        $user->save();

        $this->info(sprintf('User %s created successfully (email verified).', $user->email));

        return SymfonyCommand::SUCCESS;
    }
}

