<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One account per role for local development, mirroring the three the source app hardcoded
 * into its SPECIAL_ROUTES dict (sally.admin, tom.ap, jim.model) — except these are real
 * accounts with real password hashes rather than strings that bypassed authentication.
 *
 * Password for all three: password
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Tom Powell', 'email' => 'ap@invoiceai.test', 'role' => Role::Ap],
            ['name' => 'Sally Reed', 'email' => 'admin@invoiceai.test', 'role' => Role::Admin],
            ['name' => 'Jim Chen', 'email' => 'trainer@invoiceai.test', 'role' => Role::Trainer],
        ];

        foreach ($accounts as $account) {
            // firstOrNew rather than updateOrCreate: re-seeding must not silently reset a
            // password you changed while testing.
            $user = User::firstOrNew(['email' => $account['email']]);

            if ($user->exists) {
                continue;
            }

            $user->fill(['name' => $account['name'], 'password' => 'password']);
            $user->role = $account['role'];
            $user->email_verified_at = now();
            $user->save();
        }

        $this->command->info('Users: '.User::count());
    }
}
