<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();
    }

    public function test_can_list_projects()
    {
        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);
    }

    public function test_can_create_project_with_global_statuses()
    {
        $data = [
            'name' => 'مشروع اختبار',
            'description' => 'وصف مشروع اختبار TDD',
            'status_source' => 'global'
        ];

        $response = $this->postJson('/api/projects', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'مشروع اختبار')
                 ->assertJsonStructure(['id', 'name', 'statuses']);

        $this->assertDatabaseHas('projects', ['name' => 'مشروع اختبار']);
    }

    public function test_can_create_project_with_custom_statuses()
    {
        $data = [
            'name' => 'مشروع مخصص',
            'status_source' => 'custom',
            'custom_statuses' => ['مسودة', 'تم الإقرار', 'منشور']
        ];

        $response = $this->postJson('/api/projects', $data);

        $response->assertStatus(201)
                 ->assertJsonCount(3, 'statuses');
    }

    public function test_can_update_project()
    {
        // Setup initial project
        $project = $this->createProjectHelper();

        $data = [
            'name' => 'عنوان معدل',
            'description' => 'وصف معدل'
        ];

        $response = $this->putJson("/api/projects/{$project['id']}", $data);

        $response->assertStatus(200)
                 ->assertJsonPath('name', 'عنوان معدل');
    }

    public function test_can_soft_delete_project()
    {
        $project = $this->createProjectHelper();

        $response = $this->deleteJson("/api/projects/{$project['id']}");
        $response->assertStatus(200);

        // Check it is soft-deleted
        $this->getJson("/api/projects/{$project['id']}")->assertStatus(404);
        
        $this->assertDatabaseHas('projects', [
            'id' => $project['id'],
            'is_deleted' => true
        ]);
    }

    public function test_can_restore_project()
    {
        $project = $this->createProjectHelper();

        $this->deleteJson("/api/projects/{$project['id']}");

        $response = $this->postJson("/api/projects/{$project['id']}/restore");
        $response->assertStatus(200);

        $this->getJson("/api/projects/{$project['id']}")->assertStatus(200);
        
        $this->assertDatabaseHas('projects', [
            'id' => $project['id'],
            'is_deleted' => false
        ]);
    }

    public function test_can_add_custom_status_to_project()
    {
        $project = $this->createProjectHelper();

        $response = $this->postJson("/api/projects/{$project['id']}/statuses", [
            'status' => 'فكرة جديدة'
        ]);

        $response->assertStatus(200);
        $this->assertContains('فكرة جديدة', $response->json('statuses'));
    }

    public function test_can_delete_status_without_tasks()
    {
        $project = $this->createProjectHelper();

        $response = $this->deleteJson("/api/projects/{$project['id']}/statuses", [
            'status' => 'تحت المراجعة'
        ]);

        $response->assertStatus(200);
        $this->assertNotContains('تحت المراجعة', $response->json('statuses'));
    }

    public function test_unauthenticated_requests_to_projects_return_401()
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/projects')->assertStatus(401);
        $this->postJson('/api/projects', ['name' => 'Unauth'])->assertStatus(401);
        $this->getJson('/api/projects/1')->assertStatus(401);
        $this->putJson('/api/projects/1', ['name' => 'Unauth'])->assertStatus(401);
        $this->deleteJson('/api/projects/1')->assertStatus(401);
    }

    public function test_user_without_role_only_sees_assigned_projects()
    {
        $userNoRole = \App\Models\User::create([
            'name' => 'No Role User',
            'email' => 'norole@mymind.com',
            'password' => bcrypt('password123'),
            'role_id' => null,
        ]);

        $project1 = \App\Models\Project::create(['name' => 'Project 1', 'statuses' => ['Open']]);
        $project2 = \App\Models\Project::create(['name' => 'Project 2', 'statuses' => ['Open']]);

        $project1->users()->attach($userNoRole->id);

        \Laravel\Sanctum\Sanctum::actingAs($userNoRole);

        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $this->assertEquals($project1->id, $response->json('0.id'));
    }

    public function test_admin_only_sees_own_created_or_assigned_projects()
    {
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'مدير'], ['description' => 'مدير النظام']);
        
        $admin1 = \App\Models\User::create([
            'name' => 'Admin One',
            'email' => 'admin1@mymind.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
        ]);

        $admin2 = \App\Models\User::create([
            'name' => 'Admin Two',
            'email' => 'admin2@mymind.com',
            'password' => bcrypt('password123'),
            'role_id' => $adminRole->id,
        ]);

        // Admin 1 creates Project 1
        \Laravel\Sanctum\Sanctum::actingAs($admin1);
        $res1 = $this->postJson('/api/projects', [
            'name' => 'مشروع الأدمن الأول',
            'status_source' => 'global'
        ]);
        $res1->assertStatus(201);
        $project1Id = $res1->json('id');

        // Admin 2 creates Project 2
        \Laravel\Sanctum\Sanctum::actingAs($admin2);
        $res2 = $this->postJson('/api/projects', [
            'name' => 'مشروع الأدمن الثاني',
            'status_source' => 'global'
        ]);
        $res2->assertStatus(201);
        $project2Id = $res2->json('id');

        // When Admin 1 lists projects, they should ONLY see Project 1
        \Laravel\Sanctum\Sanctum::actingAs($admin1);
        $list1 = $this->getJson('/api/projects');
        $list1->assertStatus(200);
        $list1->assertJsonCount(1);
        $this->assertEquals($project1Id, $list1->json('0.id'));

        // Admin 1 cannot access Project 2 directly
        $this->getJson("/api/projects/{$project2Id}")->assertStatus(403);

        // When Admin 2 lists projects, they should ONLY see Project 2
        \Laravel\Sanctum\Sanctum::actingAs($admin2);
        $list2 = $this->getJson('/api/projects');
        $list2->assertStatus(200);
        $list2->assertJsonCount(1);
        $this->assertEquals($project2Id, $list2->json('0.id'));

        // Admin 2 cannot access Project 1 directly
        $this->getJson("/api/projects/{$project1Id}")->assertStatus(403);
    }

    private function createProjectHelper()
    {
        $data = [
            'name' => 'مشروع أساسي',
            'description' => 'تفاصيل مشروع',
            'status_source' => 'global'
        ];
        return $this->postJson('/api/projects', $data)->json();
    }
}
