<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderTest extends TestCase
{
    use RefreshDatabase;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();
        $this->project = Project::create([
            'name' => 'مشروع اختبار المجلدات',
            'description' => 'وصف',
            'statuses' => ['بانتظار البدء', 'قيد العمل', 'مكتمل']
        ]);
    }

    public function test_can_list_project_folders()
    {
        Folder::create([
            'project_id' => $this->project->id,
            'name' => 'مجلد المستندات'
        ]);

        $response = $this->getJson("/api/projects/{$this->project->id}/folders");

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_can_create_folder_in_project()
    {
        $data = [
            'name' => 'مجلد التصاميم'
        ];

        $response = $this->postJson("/api/projects/{$this->project->id}/folders", $data);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'مجلد التصاميم');

        $this->assertDatabaseHas('folders', [
            'project_id' => $this->project->id,
            'name' => 'مجلد التصاميم'
        ]);
    }

    public function test_can_create_nested_subfolder()
    {
        $parent = Folder::create([
            'project_id' => $this->project->id,
            'name' => 'المجلد الرئيسي'
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/folders", [
            'name' => 'المجلد الفرعي',
            'parent_id' => $parent->id
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('parent_id', $parent->id);

        $this->assertDatabaseHas('folders', [
            'parent_id' => $parent->id,
            'name' => 'المجلد الفرعي'
        ]);
    }

    public function test_cannot_create_folder_without_name()
    {
        $response = $this->postJson("/api/projects/{$this->project->id}/folders", []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    public function test_can_delete_folder()
    {
        $folder = Folder::create([
            'project_id' => $this->project->id,
            'name' => 'مجلد للحذف'
        ]);

        $response = $this->deleteJson("/api/folders/{$folder->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
    }

    public function test_cannot_create_subfolder_with_parent_from_another_project()
    {
        $otherProject = Project::create([
            'name' => 'مشروع آخر',
            'statuses' => ['بانتظار البدء']
        ]);
        $otherParentFolder = Folder::create([
            'project_id' => $otherProject->id,
            'name' => 'مجلد مشروع آخر'
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/folders", [
            'name' => 'مجلد فرعي غير شرعي',
            'parent_id' => $otherParentFolder->id
        ]);

        $response->assertStatus(422);
    }

    public function test_cascading_deletion_removes_child_folders_and_files()
    {
        $parent = Folder::create([
            'project_id' => $this->project->id,
            'name' => 'مجلد رئيسي'
        ]);
        $child = Folder::create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'name' => 'مجلد فرعي'
        ]);
        $file = \App\Models\ProjectFile::create([
            'project_id' => $this->project->id,
            'folder_id' => $child->id,
            'name' => 'subfile.pdf',
            'path' => '/storage/project_files/subfile.pdf',
            'size' => '10 KB',
            'type' => 'pdf'
        ]);

        $response = $this->deleteJson("/api/folders/{$parent->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('folders', ['id' => $parent->id]);
        $this->assertDatabaseMissing('folders', ['id' => $child->id]);
        $this->assertDatabaseMissing('project_files', ['id' => $file->id]);
    }
}
