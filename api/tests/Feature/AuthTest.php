<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private $role;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create([
            'name' => 'مدير',
            'description' => 'صلاحيات كاملة للنظام'
        ]);

        $this->user = User::create([
            'name' => 'أحمد العشري',
            'email' => 'ahmed@mymind.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->role->id
        ]);
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'ahmed@mymind.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'ahmed@mymind.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_get_profile()
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/profile');

        $response->assertStatus(200)
                 ->assertJsonPath('email', 'ahmed@mymind.com');
    }

    public function test_user_can_logout()
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/logout');

        $response->assertStatus(200);
    }
}
