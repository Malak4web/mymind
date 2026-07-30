<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();

        $this->project = $this->postJson('/api/projects', [
            'name' => 'مشروع التوثيق',
            'status_source' => 'global'
        ])->json();
    }

    public function test_can_create_and_delete_folder()
    {
        // 1. Create folder
        $response = $this->postJson("/api/projects/{$this->project['id']}/folders", [
            'name' => 'مجلد التصاميم'
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'مجلد التصاميم');

        $folder = $response->json();
        $this->assertDatabaseHas('folders', ['id' => $folder['id'], 'name' => 'مجلد التصاميم']);

        // 2. List folders
        $listResponse = $this->getJson("/api/projects/{$this->project['id']}/folders");
        $listResponse->assertStatus(200);

        // 3. Delete folder
        $deleteResponse = $this->deleteJson("/api/folders/{$folder['id']}");
        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('folders', ['id' => $folder['id']]);
    }

    public function test_can_create_and_update_note()
    {
        // 1. Create note
        $response = $this->postJson("/api/projects/{$this->project['id']}/notes", [
            'title' => 'ملاحظة تخطيطية',
            'content' => 'محتوى الملاحظة الغني هنا.'
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('title', 'ملاحظة تخطيطية');

        $note = $response->json();
        $this->assertDatabaseHas('notes', ['id' => $note['id'], 'title' => 'ملاحظة تخطيطية']);

        // 2. Update note
        $updateResponse = $this->putJson("/api/notes/{$note['id']}", [
            'title' => 'عنوان معدل',
            'content' => 'محتوى معدل.'
        ]);
        $updateResponse->assertStatus(200)
                       ->assertJsonPath('title', 'عنوان معدل');

        // 3. Delete note
        $deleteResponse = $this->deleteJson("/api/notes/{$note['id']}");
        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('notes', ['id' => $note['id']]);
    }

    public function test_can_upload_and_delete_project_file()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('logo.png', 150);

        // 1. Upload file
        $response = $this->postJson("/api/projects/{$this->project['id']}/project-files", [
            'file' => $file
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'logo.png');

        $projectFile = $response->json();
        
        // Assert storage has file
        $storedPath = str_replace('/storage/', '', $projectFile['path']);
        Storage::disk('public')->assertExists($storedPath);

        // 2. Delete file
        $deleteResponse = $this->deleteJson("/api/project-files/{$projectFile['id']}");
        $deleteResponse->assertStatus(200);
        
        Storage::disk('public')->assertMissing($storedPath);
        $this->assertDatabaseMissing('project_files', ['id' => $projectFile['id']]);
    }
}
