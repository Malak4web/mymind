<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private $project;
    private $task;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->project = $this->postJson('/api/projects', [
            'name' => 'مشروع المرفقات',
            'status_source' => 'global'
        ])->json();

        $this->task = $this->postJson("/api/projects/{$this->project['id']}/tasks", [
            'title' => 'مهمة رفع ملفات'
        ])->json();
    }

    public function test_can_upload_attachment()
    {
        $file = UploadedFile::fake()->create('document.pdf', 500); // 500 KB

        $response = $this->postJson("/api/tasks/{$this->task['id']}/attachments", [
            'file' => $file
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['id', 'name', 'size', 'status']);

        $this->assertDatabaseHas('attachments', [
            'task_id' => $this->task['id'],
            'name' => 'document.pdf'
        ]);

        Storage::disk('public')->assertExists("attachments/{$file->hashName()}");
    }

    public function test_can_simulate_failed_upload()
    {
        $file = UploadedFile::fake()->create('error_file.png', 200);

        $response = $this->postJson("/api/tasks/{$this->task['id']}/attachments", [
            'file' => $file,
            'simulate_failure' => true
        ]);

        $response->assertStatus(200) // Returns file info but status is failed
                 ->assertJsonPath('status', 'failed');

        $this->assertDatabaseHas('attachments', [
            'task_id' => $this->task['id'],
            'name' => 'error_file.png',
            'status' => 'failed'
        ]);
    }

    public function test_can_delete_attachment()
    {
        $file = UploadedFile::fake()->create('document.pdf', 500);
        $attachment = $this->postJson("/api/tasks/{$this->task['id']}/attachments", [
            'file' => $file
        ])->json();

        $response = $this->deleteJson("/api/attachments/{$attachment['id']}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('attachments', ['id' => $attachment['id']]);
    }
}
