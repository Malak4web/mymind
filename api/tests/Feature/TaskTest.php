<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create project helper for task relation
        $this->project = $this->postJson('/api/projects', [
            'name' => 'مشروع المهام',
            'status_source' => 'global'
        ])->json();
    }

    public function test_can_list_tasks_in_project()
    {
        $response = $this->getJson("/api/projects/{$this->project['id']}/tasks");
        $response->assertStatus(200);
    }

    public function test_can_create_task()
    {
        $data = [
            'title' => 'مهمة إعداد السيرفر',
            'description' => 'تثبيت البرمجيات الملحقة',
            'status' => 'بانتظار البدء',
            'start_date' => '2026-07-16',
            'deadline' => '2026-07-20'
        ];

        $response = $this->postJson("/api/projects/{$this->project['id']}/tasks", $data);

        $response->assertStatus(201)
                 ->assertJsonPath('title', 'مهمة إعداد السيرفر');

        $this->assertDatabaseHas('tasks', ['title' => 'مهمة إعداد السيرفر']);
    }

    public function test_cannot_create_task_with_deadline_before_start_date()
    {
        $data = [
            'title' => 'مهمة خاطئة',
            'start_date' => '2026-07-20',
            'deadline' => '2026-07-15'
        ];

        $response = $this->postJson("/api/projects/{$this->project['id']}/tasks", $data);
        $response->assertStatus(422);
    }

    public function test_can_update_task()
    {
        $task = $this->createTaskHelper();

        $data = [
            'title' => 'مسمى مهمة معدل',
            'status' => 'قيد العمل'
        ];

        $response = $this->putJson("/api/tasks/{$task['id']}", $data);

        $response->assertStatus(200)
                 ->assertJsonPath('title', 'مسمى مهمة معدل')
                 ->assertJsonPath('status', 'قيد العمل');
    }

    public function test_can_delete_task()
    {
        $task = $this->createTaskHelper();

        $response = $this->deleteJson("/api/tasks/{$task['id']}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('tasks', ['id' => $task['id']]);
    }

    private function createTaskHelper()
    {
        return $this->postJson("/api/projects/{$this->project['id']}/tasks", [
            'title' => 'مهمة مبدئية',
            'status' => 'بانتظار البدء'
        ])->json();
    }
}
