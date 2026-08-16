<?php

namespace Tests\Feature;

use App\Models\ProjectCategory;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCategoryTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'مدير', 'description' => 'مدير النظام']);
        $this->user = User::create([
            'name' => 'اختبار التصنيفات',
            'email' => 'cat@mymind.com',
            'password' => bcrypt('password123'),
            'role_id' => $role->id
        ]);
        $this->authenticateUser($this->user);
    }

    public function test_can_list_project_categories()
    {
        ProjectCategory::create(['name' => 'تصنيف 1', 'color' => '#8b5cf6', 'user_id' => $this->user->id]);

        $response = $this->getJson('/api/project-categories');

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_can_create_project_category()
    {
        $data = [
            'name' => 'تصنيف جديد',
            'description' => 'وصف التصنيف',
            'color' => '#3b82f6',
            'icon' => '🚀'
        ];

        $response = $this->postJson('/api/project-categories', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'تصنيف جديد')
                 ->assertJsonPath('user_id', $this->user->id);

        $this->assertDatabaseHas('project_categories', [
            'name' => 'تصنيف جديد',
            'user_id' => $this->user->id
        ]);
    }

    public function test_cannot_create_category_without_required_name()
    {
        $response = $this->postJson('/api/project-categories', [
            'description' => 'بدون اسم'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_project_category()
    {
        $category = ProjectCategory::create(['name' => 'تصنيف قديم', 'user_id' => $this->user->id]);

        $response = $this->putJson("/api/project-categories/{$category->id}", [
            'name' => 'تصنيف معدل',
            'color' => '#10b981'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('name', 'تصنيف معدل');

        $this->assertDatabaseHas('project_categories', [
            'id' => $category->id,
            'name' => 'تصنيف معدل'
        ]);
    }

    public function test_can_delete_project_category()
    {
        $category = ProjectCategory::create(['name' => 'تصنيف للحذف', 'user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/project-categories/{$category->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('project_categories', ['id' => $category->id]);
    }

    public function test_user_only_sees_own_and_system_categories()
    {
        $otherRole = Role::create(['name' => 'عضو', 'description' => 'عضو عادي']);
        $otherUser = User::create([
            'name' => 'عضو آخر',
            'email' => 'other@mymind.com',
            'password' => bcrypt('password123'),
            'role_id' => $otherRole->id
        ]);

        // Category by current user
        ProjectCategory::create(['name' => 'تصنيفي الخاص', 'user_id' => $this->user->id]);
        // Category by other user
        ProjectCategory::create(['name' => 'تصنيف عضو آخر', 'user_id' => $otherUser->id]);

        // Authenticate as otherUser
        $this->authenticateUser($otherUser);

        $response = $this->getJson('/api/project-categories');

        $response->assertStatus(200)
                 ->assertJsonCount(1) // Sees ONLY own category, not other user's
                 ->assertJsonPath('0.name', 'تصنيف عضو آخر');
    }
}
