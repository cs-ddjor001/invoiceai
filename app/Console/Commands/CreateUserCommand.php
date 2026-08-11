<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Replaces self-registration. The source app had no provisioning at all — any string typed
 * into the login box became a session — so this is new ground rather than a port.
 */
#[Signature('user:create {--name=} {--email=} {--role=} {--password=}')]
#[Description('Provision a user account. Prompts for anything not passed as an option.')]
class CreateUserCommand extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Full name',
            required: true,
        );

        $email = $this->option('email') ?: text(
            label: 'Email address',
            required: true,
        );

        $role = $this->option('role') ?: select(
            label: 'Role',
            options: collect(Role::cases())->pluck('value', 'value')->all(),
            default: Role::Ap->value,
        );

        // Prompted rather than accepted as an option by default: a password passed on the
        // command line lands in the shell history file in plaintext.
        $plainPassword = $this->option('password') ?: password(
            label: 'Password',
            required: true,
        );

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => $plainPassword,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
        ]);

        // role is not mass-assignable on purpose — see the note in User. Setting it directly
        // means no HTTP request can ever talk its way into an admin account.
        $user->role = Role::from($role);
        $user->password = $plainPassword;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Created {$user->email} as {$user->role->value}.");

        return self::SUCCESS;
    }
}
