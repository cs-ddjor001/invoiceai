<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleLandingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ap_user_can_access_ap_landing_page(): void
    {
        $user = User::factory()->create(['role' => 'ap']);

        $response = $this->actingAs($user)->get(route('ap'));

        $response->assertStatus(200);
        $response->assertViewIs('ap');
    }

    public function test_ap_cannot_access_admin_landing_page(): void
    {
        $user = User::factory()->create(['role' => 'ap']);

        $response = $this->actingAs($user)->get(route('admin'));

        $response->assertStatus(403);
    }
}
