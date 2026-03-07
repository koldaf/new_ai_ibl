<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    // we skip RefreshDatabase to avoid needing a real mysql connection; the
    // middleware only inspects the user object provided by actingAs, so we
    // can use `make()` instead of `create()`.

    /** @test */
    public function admin_route_is_blocked_for_students()
    {
        $student = User::factory()->make(['role' => 'student']);

        $response = $this->actingAs($student)->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    /** @test */
    public function student_route_is_blocked_for_admins()
    {
        $admin = User::factory()->make(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('student.lessons.index'));

        // student area may still be accessible if you want to allow both,
        // but the middleware currently checks exact match so it will 403.
        $response->assertStatus(403);
    }

    /** @test */
    public function middleware_allows_correct_role()
    {
        $admin = User::factory()->make(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }
}
