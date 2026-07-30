<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;
    private $normalUser;
    private $adminRole;
    private $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup permissions
        $manageUsersPermission = Permission::create([
            'name' => 'إدارة المستخدمين',
            'slug' => 'manage-users'
        ]);

        // Setup Roles
        $this->adminRole = Role::create([
            'name' => 'مدير',
            'description' => 'صلاحيات كاملة'
        ]);
        $this->adminRole->permissions()->attach($manageUsersPermission->id);

        $this->memberRole = Role::create([
            'name' => 'عضو',
            'description' => 'صلاحيات عمل محدودة'
        ]);

        // Create Users
        $this->adminUser = User::create([
            'name' => 'المدير خالد',
            'email' => 'admin@mymind.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->adminRole->id
        ]);

        $this->normalUser = User::create([
            'name' => 'العضو سارة',
            'email' => 'sara@mymind.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->memberRole->id
        ]);
    }

    public function test_admin_can_list_users()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
                 ->assertJsonCount(2);
    }

    public function test_member_cannot_list_users()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->normalUser);

        $response = $this->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user_with_role()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $data = [
            'name' => 'عضو جديد',
            'email' => 'newmember@mymind.com',
            'password' => 'password123',
            'role_id' => $this->memberRole->id
        ];

        $response = $this->postJson('/api/users', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('email', 'newmember@mymind.com');

        $this->assertDatabaseHas('users', ['email' => 'newmember@mymind.com']);
    }

    public function test_admin_can_update_user_role()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/users/{$this->normalUser->id}", [
                             'name' => 'سارة المعدلة',
                             'role_id' => $this->adminRole->id
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('role_id', $this->adminRole->id);

        $this->assertEquals($this->adminRole->id, $this->normalUser->fresh()->role_id);
    }

    public function test_admin_can_delete_user()
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);

        $userToDelete = User::create([
            'name' => 'عضو مؤقت',
            'email' => 'temp@mymind.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->memberRole->id
        ]);

        $response = $this->deleteJson("/api/users/{$userToDelete->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }
}
