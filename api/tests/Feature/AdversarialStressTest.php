<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdversarialStressTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $project;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'مدير',
            'description' => 'صلاحيات كاملة للنظام'
        ]);

        $this->user = User::create([
            'name' => 'Adversarial Tester',
            'email' => 'tester@mymind.com',
            'password' => bcrypt('password123'),
            'role_id' => $role->id
        ]);

        $this->authenticateUser($this->user);

        $this->project = Project::create([
            'name' => 'Main Test Project',
            'description' => 'Target project for stress testing',
            'statuses' => ['بانتظار البدء', 'قيد العمل', 'مكتمل']
        ]);
    }

    /**
     * 1. Stress-Test Unauthenticated API Access
     */
    public function test_unauthenticated_requests_to_strictly_protected_routes()
    {
        // Clear authentication session
        $this->app['auth']->forgetGuards();

        $protectedRoutes = [
            ['GET', '/api/profile'],
            ['POST', '/api/logout'],
            ['GET', '/api/users'],
            ['POST', '/api/users'],
            ['GET', '/api/roles'],
            ['GET', '/api/permissions'],
            ['GET', '/api/projects'],
            ['POST', '/api/projects'],
            ['GET', '/api/projects/1/tasks'],
            ['POST', '/api/projects/1/tasks'],
            ['GET', '/api/projects/1/folders'],
            ['POST', '/api/projects/1/folders'],
            ['GET', '/api/projects/1/notes'],
            ['GET', '/api/project-categories'],
            ['GET', '/api/notifications'],
            ['GET', '/api/project-templates'],
            ['GET', '/api/task-templates'],
        ];

        foreach ($protectedRoutes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $this->assertEquals(
                401,
                $response->status(),
                "Expected 401 for unauthenticated request to {$method} {$uri}, got {$response->status()}"
            );
        }
    }

    /**
     * 2. Stress-Test SQL Injection & Boundary Inputs in Controller Parameters
     */
    public function test_sql_injection_and_boundary_inputs_in_controller_parameters()
    {
        $sqliPayloads = [
            "' OR '1'='1",
            "1; DROP TABLE users;--",
            "1 UNION SELECT 1,2,3--",
            "' AND 1=2 UNION ALL SELECT NULL,NULL,NULL--"
        ];

        foreach ($sqliPayloads as $payload) {
            // Attempt SQL injection in project lookup
            $response = $this->getJson("/api/projects/{$payload}");
            $this->assertNotEquals(500, $response->status(), "SQLi payload caused 500 error: {$payload}");
            $this->assertEquals(404, $response->status());

            // Attempt SQL injection in task creation title
            $taskResp = $this->postJson("/api/projects/{$this->project->id}/tasks", [
                'title' => $payload,
                'description' => 'SQLi stress test'
            ]);
            $this->assertEquals(201, $taskResp->status());
            $this->assertDatabaseHas('tasks', ['title' => $payload]);
        }

        // Boundary IDs: negative, zero, giant integer
        $boundaryIds = [-1, 0, 999999999999];
        foreach ($boundaryIds as $badId) {
            $resp = $this->getJson("/api/projects/{$badId}");
            $this->assertEquals(404, $resp->status());
        }

        // Extremely long input strings (10,000 chars)
        $longTitle = str_repeat('A', 300); // Max validator is 255
        $respValidation = $this->postJson("/api/projects/{$this->project->id}/tasks", [
            'title' => $longTitle
        ]);
        $respValidation->assertStatus(422)
                       ->assertJsonValidationErrors(['title']);
    }

    /**
     * 3. Stress-Test Invalid Date Formats Submitted to TaskController
     */
    public function test_invalid_date_formats_and_inverted_dates_in_task_controller()
    {
        $invalidDates = [
            'invalid-date-string',
            '2026-13-45',
            '9999-99-99',
            '0000-00-00',
            '2026-02-30'
        ];

        foreach ($invalidDates as $badDate) {
            // Test in store
            $responseStore = $this->postJson("/api/projects/{$this->project->id}/tasks", [
                'title' => 'Task Invalid Date',
                'start_date' => $badDate
            ]);
            $this->assertEquals(422, $responseStore->status(), "Failed to reject invalid start_date: {$badDate}");

            $responseStoreEnd = $this->postJson("/api/projects/{$this->project->id}/tasks", [
                'title' => 'Task Invalid Date',
                'deadline' => $badDate
            ]);
            $this->assertEquals(422, $responseStoreEnd->status(), "Failed to reject invalid deadline: {$badDate}");
        }

        // Inverted dates: deadline before start_date
        $invertedResp = $this->postJson("/api/projects/{$this->project->id}/tasks", [
            'title' => 'Inverted Dates Task',
            'start_date' => '2026-12-31',
            'deadline' => '2026-01-01'
        ]);
        $invertedResp->assertStatus(422)
                     ->assertJsonPath('error', 'تاريخ التسليم لا يمكن أن يكون قبل تاريخ البدء');

        // Test in update endpoint
        $task = Task::create([
            'project_id' => $this->project->id,
            'title' => 'Valid Task',
            'status' => 'بانتظار البدء',
            'start_date' => '2026-07-01',
            'deadline' => '2026-07-10'
        ]);

        $updateInverted = $this->putJson("/api/tasks/{$task->id}", [
            'deadline' => '2026-06-01' // Before existing start_date 2026-07-01
        ]);
        $updateInverted->assertStatus(422);
    }

    /**
     * 4. Multi-Tenant Folder Isolation Checks in FolderController
     */
    public function test_multi_tenant_folder_isolation()
    {
        $projectA = Project::create(['name' => 'Project A', 'statuses' => ['Open']]);
        $projectB = Project::create(['name' => 'Project B', 'statuses' => ['Open']]);

        $folderA = Folder::create([
            'project_id' => $projectA->id,
            'name' => 'Tenant A Root Folder'
        ]);

        // Tenant B attempts to create a child folder under Tenant A's folder
        $response = $this->postJson("/api/projects/{$projectB->id}/folders", [
            'name' => 'Tenant B Cross-Tenant Child',
            'parent_id' => $folderA->id
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error', 'المجلد الأب لا ينتمي إلى هذا المشروع');

        // Verify subfolder was NOT created in DB
        $this->assertDatabaseMissing('folders', [
            'name' => 'Tenant B Cross-Tenant Child'
        ]);

        // Verify folder listing isolation
        Folder::create([
            'project_id' => $projectB->id,
            'name' => 'Tenant B Valid Folder'
        ]);

        $listA = $this->getJson("/api/projects/{$projectA->id}/folders");
        $listA->assertStatus(200)->assertJsonCount(1);
        $this->assertEquals($folderA->id, $listA->json('0.id'));

        $listB = $this->getJson("/api/projects/{$projectB->id}/folders");
        $listB->assertStatus(200)->assertJsonCount(1);
        $this->assertEquals('Tenant B Valid Folder', $listB->json('0.name'));
    }

    /**
     * 5. Exception Handling and Transaction Rollback in ProjectController@store Template Expansion
     */
    public function test_project_creation_transaction_rollback_on_template_expansion_failure()
    {
        // Create a task template that triggers an exception during template expansion
        $taskTemplate = TaskTemplate::create([
            'name' => 'Faulty Task Template',
            'custom_fields_values' => [
                ['type' => 'title', 'value' => 'Spawned Task Title']
            ]
        ]);

        $projectTemplate = ProjectTemplate::create([
            'name' => 'Faulty Project Template',
            'statuses' => ['Start', 'End'],
            'task_template_ids' => [$taskTemplate->id]
        ]);

        // Count projects before attempt
        $initialProjectCount = Project::count();

        Task::creating(function () {
            throw new \Exception('Simulated DB failure during task creation');
        });

        $response = $this->postJson('/api/projects', [
            'name' => 'Project That Should Roll Back',
            'project_template_id' => $projectTemplate->id
        ]);

        $response->assertStatus(500);

        // Verify full transaction rollback: no project was persisted in DB
        $this->assertEquals($initialProjectCount, Project::count(), "Transaction did not roll back project creation on failure!");
        $this->assertDatabaseMissing('projects', ['name' => 'Project That Should Roll Back']);
    }
}
