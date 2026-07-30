<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'مدير',
            'description' => 'صلاحيات كاملة'
        ]);

        $this->adminUser = User::create([
            'name' => 'المدير خالد',
            'email' => 'admin@mymind.com',
            'password' => bcrypt('password123'),
            'role_id' => $role->id
        ]);

        Permission::create(['name' => 'إدارة المهام', 'slug' => 'manage-tasks']);
    }

    public function test_can_list_roles_and_permissions()
    {
        $token = $this->adminUser->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/roles');
        $response->assertStatus(200);

        $response2 = $this->withHeader('Authorization', 'Bearer ' . $token)
                          ->getJson('/api/permissions');
        $response2->assertStatus(200);
    }
}
