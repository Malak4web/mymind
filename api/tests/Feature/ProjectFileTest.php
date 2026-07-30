<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Folder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectFileTest extends TestCase
{
    use RefreshDatabase;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();
        Storage::fake('public');
        $this->project = Project::create([
            'name' => 'مشروع اختبار الملفات',
            'description' => 'وصف',
            'statuses' => ['بانتظار البدء', 'قيد العمل', 'مكتمل']
        ]);
    }

    public function test_can_list_project_files()
    {
        ProjectFile::create([
            'project_id' => $this->project->id,
            'name' => 'وثيقة.pdf',
            'path' => '/storage/project_files/doc.pdf',
            'size' => '1.5 MB',
            'type' => 'pdf'
        ]);

        $response = $this->getJson("/api/projects/{$this->project->id}/project-files");

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_can_upload_project_file()
    {
        $file = UploadedFile::fake()->create('تقرير.pdf', 1024, 'application/pdf');

        $response = $this->postJson("/api/projects/{$this->project->id}/project-files", [
            'file' => $file
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'تقرير.pdf');

        $this->assertDatabaseHas('project_files', [
            'project_id' => $this->project->id,
            'name' => 'تقرير.pdf'
        ]);
    }

    public function test_cannot_upload_without_file_payload()
    {
        $response = $this->postJson("/api/projects/{$this->project->id}/project-files", []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['file']);
    }

    public function test_can_delete_project_file()
    {
        $uploaded = UploadedFile::fake()->create('صورة.png', 500, 'image/png');
        $file = $this->postJson("/api/projects/{$this->project->id}/project-files", ['file' => $uploaded])->json();

        $response = $this->deleteJson("/api/project-files/{$file['id']}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('project_files', ['id' => $file['id']]);
    }

    public function test_can_download_project_file()
    {
        $uploaded = UploadedFile::fake()->create('ملف_تحميل.txt', 100, 'text/plain');
        $file = $this->postJson("/api/projects/{$this->project->id}/project-files", ['file' => $uploaded])->json();

        $response = $this->get("/api/project-files/{$file['id']}/download");

        $response->assertStatus(200);
    }
}
