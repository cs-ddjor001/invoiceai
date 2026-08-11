<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_redirects_to_admin_landing_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin'));
    }

    public function test_ap_user_redirects_to_ap_landing_page(): void
    {
        $ap = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $ap->email,
            'password' => 'password',
        ])->assertRedirect(route('ap'));
    }

    public function test_trainer_user_redirects_to_trainer_landing_page(): void
    {
        $trainer = User::factory()->trainer()->create();

        $this->post(route('login.store'), [
            'email' => $trainer->email,
            'password' => 'password',
        ])->assertRedirect(route('trainer'));
    }
}
