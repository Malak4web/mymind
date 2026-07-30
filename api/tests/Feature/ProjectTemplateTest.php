<?php

namespace Tests\Feature;

use App\Models\ProjectTemplate;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();
    }

    public function test_can_list_project_templates()
    {
        ProjectTemplate::create([
            'name' => 'قالب مشروع 1',
            'statuses' => ['قيد العمل', 'مكتمل']
        ]);

        $response = $this->getJson('/api/project-templates');

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_can_create_project_template()
    {
        $data = [
            'name' => 'قالب تطوير البرمجيات',
            'is_default' => true,
            'statuses' => ['تحليل', 'تطوير', 'اختبار', 'تم الإطلاق']
        ];

        $response = $this->postJson('/api/project-templates', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'قالب تطوير البرمجيات')
                 ->assertJsonPath('is_default', true);

        $this->assertDatabaseHas('project_templates', [
            'name' => 'قالب تطوير البرمجيات',
            'is_default' => true
        ]);
    }

    public function test_cannot_create_project_template_without_name()
    {
        $response = $this->postJson('/api/project-templates', [
            'is_default' => false
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_project_template()
    {
        $template = ProjectTemplate::create([
            'name' => 'قالب قديم',
            'is_default' => false
        ]);

        $response = $this->putJson("/api/project-templates/{$template->id}", [
            'name' => 'قالب بايثون',
            'is_default' => false,
            'statuses' => ['بدء', 'إنهاء']
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('name', 'قالب بايثون');

        $this->assertDatabaseHas('project_templates', [
            'id' => $template->id,
            'name' => 'قالب بايثون'
        ]);
    }

    public function test_can_set_project_template_as_default()
    {
        $template1 = ProjectTemplate::create(['name' => 'قالب 1', 'is_default' => true]);
        $template2 = ProjectTemplate::create(['name' => 'قالب 2', 'is_default' => false]);

        $response = $this->postJson("/api/project-templates/{$template2->id}/set-default");

        $response->assertStatus(200)
                 ->assertJsonPath('is_default', true);

        $this->assertDatabaseHas('project_templates', ['id' => $template1->id, 'is_default' => false]);
        $this->assertDatabaseHas('project_templates', ['id' => $template2->id, 'is_default' => true]);
    }

    public function test_can_delete_project_template()
    {
        $template = ProjectTemplate::create(['name' => 'قالب للحذف']);

        $response = $this->deleteJson("/api/project-templates/{$template->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('project_templates', ['id' => $template->id]);
    }
}
