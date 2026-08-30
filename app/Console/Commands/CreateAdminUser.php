<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the shop's first staff account on a fresh deployment.
 *
 * Registration through the storefront always produces a customer, and the
 * seeders' admin@example.com belongs to development only — without this
 * command a production install has no way into /admin. The password is read
 * from a hidden prompt so it never lands in the shell history or process list.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin
                            {--email= : Email address for the account}
                            {--first-name= : Given name}
                            {--last-name= : Family name}';

    protected $description = 'Create (or promote) a staff account that can sign in to the admin area';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email address');

        if ($existing = User::where('email', $email)->first()) {
            return $this->promote($existing);
        }

        $firstName = $this->option('first-name') ?: $this->ask('First name');
        $lastName = $this->option('last-name') ?: $this->ask('Last name');
        $password = $this->secret('Password (min 8 characters)');
        $confirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->components->info("Admin account created for {$email}.");

        return self::SUCCESS;
    }

    /**
     * An existing account keeps its password; only the role changes. Useful
     * when the owner already registered as a customer before staff access
     * was set up.
     */
    private function promote(User $user): int
    {
        if ($user->isAdmin()) {
            $this->components->warn("{$user->email} is already an admin.");

            return self::SUCCESS;
        }

        if (! $this->confirm("{$user->email} already exists as a customer. Promote it to admin?", true)) {
            return self::FAILURE;
        }

        $user->update(['role' => 'admin']);
        $this->components->info("{$user->email} promoted to admin.");

        return self::SUCCESS;
    }
}
