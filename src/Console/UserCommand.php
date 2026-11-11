<?php

namespace Gmarineau\AdminPhp\Console;

use Gmarineau\AdminPhp\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'adminphp:user')]
class UserCommand extends Command
{
    protected $signature = 'adminphp:user
        {email? : Email of the admin user to create, or whose password to reset}
        {--name= : Name shown in the admin}
        {--password= : Password (asked when omitted)}';

    protected $description = 'Create an admin user, or reset the password of an existing one';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Email');
        $user = User::query()->where('email', $email)->first();

        $name = $this->option('name') ?? $user->name ?? $this->ask('Name');
        $password = $this->option('password') ?? $this->secret('Password (8 characters minimum)');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user ??= new User(['email' => $email]);
        $wasExisting = $user->exists;

        $user->fill(['name' => $name, 'password' => $password])->save();

        $this->components->info(sprintf(
            '%s %s. Log in at %s',
            $wasExisting ? 'Updated' : 'Created',
            $user->email,
            url(config('adminphp.path')),
        ));

        return self::SUCCESS;
    }
}
