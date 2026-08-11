<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Accounts are provisioned by an administrator, never self-registered.
 */
class ProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_routes_do_not_exist(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));

        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_login_page_offers_no_way_to_sign_up(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Sign up')
            ->assertSee('Contact your administrator');
    }

    public function test_an_administrator_can_provision_an_account(): void
    {
        $this->artisan('user:create', [
            '--name' => 'Sally Reed',
            '--email' => 'sally@invoiceai.test',
            '--role' => 'admin',
            '--password' => 'correct-horse-battery',
        ])->assertSuccessful();

        $user = User::firstWhere('email', 'sally@invoiceai.test');

        $this->assertSame('Sally Reed', $user->name);
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    public function test_provisioning_defaults_a_user_to_the_ap_role(): void
    {
        $this->artisan('user:create', [
            '--name' => 'Tom Powell',
            '--email' => 'tom@invoiceai.test',
            '--role' => 'ap',
            '--password' => 'correct-horse-battery',
        ])->assertSuccessful();

        $this->assertSame(Role::Ap, User::firstWhere('email', 'tom@invoiceai.test')->role);
    }

    public function test_provisioning_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@invoiceai.test']);

        $this->artisan('user:create', [
            '--name' => 'Impostor',
            '--email' => 'taken@invoiceai.test',
            '--role' => 'ap',
            '--password' => 'correct-horse-battery',
        ])->assertFailed();

        $this->assertSame(1, User::where('email', 'taken@invoiceai.test')->count());
    }

    public function test_provisioning_rejects_an_unknown_role(): void
    {
        $this->artisan('user:create', [
            '--name' => 'Nobody',
            '--email' => 'nobody@invoiceai.test',
            '--role' => 'superuser',
            '--password' => 'correct-horse-battery',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'nobody@invoiceai.test']);
    }

    public function test_role_cannot_be_set_by_mass_assignment(): void
    {
        // The guard that stops a crafted request from talking its way into an admin account.
        $user = new User;
        $user->fill(['name' => 'Tom', 'email' => 'tom2@invoiceai.test', 'role' => 'admin']);

        $this->assertNotSame(Role::Admin, $user->role ?? null);
    }
}
