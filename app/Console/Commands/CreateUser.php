<?php

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-user')]
#[Description('Create a verified user account (public registration is disabled)')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CreateNewUser $creator): int
    {
        $input = [
            'name' => text('Name', required: true),
            'email' => text('Email address', required: true),
            'password' => password('Password', required: true),
            'password_confirmation' => password('Confirm password', required: true),
        ];

        try {
            $user = $creator->create($input);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user->markEmailAsVerified();

        $this->components->info("User [{$user->email}] created. You can now log in.");

        return self::SUCCESS;
    }
}
