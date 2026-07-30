<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthMiddlewareAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_routes_reject_unauthenticated_requests()
    {
        $this->getJson('/api/profile')->assertStatus(401);
        $this->postJson('/api/logout')->assertStatus(401);
    }

    public function test_protected_routes_allow_authenticated_requests()
    {
        $role = Role::create(['name' => 'مدير', 'description' => 'صلاحيات كاملة']);
        $user = User::create([
            'name' => 'أحمد العشري',
            'email' => 'ahmed@mymind.com',
            'password' => bcrypt('password123'),
            'role_id' => $role->id
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
                 ->assertJsonPath('email', 'ahmed@mymind.com');
    }
}
