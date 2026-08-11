<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    // Gives each test a clean database, then rolls it back afterwards. Without this, one
    // test's users would leak into the next and the order tests ran in would change results.
    use RefreshDatabase;

    /**
     * Registers a throwaway route guarded by the middleware.
     *
     * The middleware has no routes to protect yet, so the test supplies its own. Routes
     * declared inside a test exist only for that test — this keeps the test independent of
     * whatever URLs Phase 2 eventually settles on, so renaming a real route later won't
     * break it.
     */
    private function routeGuardedBy(string $middleware): string
    {
        Route::middleware(['web', 'auth', $middleware])
            ->get('/__test/guarded', fn () => 'reached the route');

        return '/__test/guarded';
    }

    public function test_a_user_without_the_required_role_is_forbidden(): void
    {
        // ARRANGE — set up exactly the world this test needs, and nothing else.
        $url = $this->routeGuardedBy('role:admin');
        $apUser = User::factory()->create();          // factory default role is Ap

        // ACT — perform one action. actingAs() authenticates as that user for this request,
        // so we never have to submit the login form to test something behind auth.
        $response = $this->actingAs($apUser)->get($url);

        // ASSERT — state the expected outcome. Naming the status explicitly documents the
        // decision to use 403 rather than a redirect.
        $response->assertForbidden();
    }

    public function test_a_user_with_the_required_role_reaches_the_route(): void
    {
        $url = $this->routeGuardedBy('role:admin');
        $adminUser = User::factory()->admin()->create();
        $response = $this->actingAs($adminUser)->get($url);
        $response->assertOk();
        $response->assertSee('reached the route');
    }

    public function test_a_route_accepts_any_of_several_roles(): void
    {
        $url = $this->routeGuardedBy('role:admin,trainer');
        $adminUser = User::factory()->admin()->create();
        $trainerUser = User::factory()->trainer()->create();
        $adminResponse = $this->actingAs($adminUser)->get($url);
        $trainerResponse = $this->actingAs($trainerUser)->get($url);
        $adminResponse->assertOk();
        $trainerResponse->assertOk();
    }
}
