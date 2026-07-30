<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Project;
use App\Models\Folder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();
        $this->project = Project::create([
            'name' => 'مشروع اختبار الملاحظات',
            'description' => 'وصف',
            'statuses' => ['بانتظار البدء', 'قيد العمل', 'مكتمل']
        ]);
    }

    public function test_can_list_project_notes()
    {
        Note::create([
            'project_id' => $this->project->id,
            'title' => 'ملاحظة 1',
            'content' => 'محتوى الملاحظة'
        ]);

        $response = $this->getJson("/api/projects/{$this->project->id}/notes");

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_can_create_note_in_project()
    {
        $data = [
            'title' => 'تعليمات النشر',
            'content' => 'خطوات النشر على السيرفر المحلي'
        ];

        $response = $this->postJson("/api/projects/{$this->project->id}/notes", $data);

        $response->assertStatus(201)
                 ->assertJsonPath('title', 'تعليمات النشر');

        $this->assertDatabaseHas('notes', [
            'project_id' => $this->project->id,
            'title' => 'تعليمات النشر'
        ]);
    }

    public function test_cannot_create_note_without_title()
    {
        $response = $this->postJson("/api/projects/{$this->project->id}/notes", [
            'content' => 'محتوى بدون عنوان'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['title']);
    }

    public function test_can_update_note()
    {
        $note = Note::create([
            'project_id' => $this->project->id,
            'title' => 'عنوان قديم',
            'content' => 'محتوى قديم'
        ]);

        $response = $this->putJson("/api/notes/{$note->id}", [
            'title' => 'عنوان جديد',
            'content' => 'محتوى جديد ومعدل'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('title', 'عنوان جديد');

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'عنوان جديد'
        ]);
    }

    public function test_can_delete_note()
    {
        $note = Note::create([
            'project_id' => $this->project->id,
            'title' => 'ملاحظة للحذف',
            'content' => 'سيتم حذفها'
        ]);

        $response = $this->deleteJson("/api/notes/{$note->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }
}
