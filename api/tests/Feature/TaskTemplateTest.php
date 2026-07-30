<?php

namespace Tests\Feature;

use App\Models\TaskTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();
    }

    public function test_can_list_task_templates()
    {
        TaskTemplate::create([
            'name' => 'قالب مهمة اختبار',
            'priority' => 'عالي'
        ]);

        $response = $this->getJson('/api/task-templates');

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_can_create_task_template()
    {
        $data = [
            'name' => 'قالب إصلاح ثغرة',
            'is_default' => true,
            'title' => 'عنوان المهمة الفعال',
            'description' => 'تفاصيل الثغرة المطلوبة',
            'priority' => 'عالي جداً',
            'start_date_offset' => 0,
            'due_date_offset' => 3
        ];

        $response = $this->postJson('/api/task-templates', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'قالب إصلاح ثغرة')
                 ->assertJsonPath('priority', 'عالي جداً');

        $this->assertDatabaseHas('task_templates', ['name' => 'قالب إصلاح ثغرة']);
    }

    public function test_cannot_create_task_template_without_name()
    {
        $response = $this->postJson('/api/task-templates', [
            'priority' => 'متوسط'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_task_template()
    {
        $template = TaskTemplate::create(['name' => 'قالب قديم']);

        $response = $this->putJson("/api/task-templates/{$template->id}", [
            'name' => 'قالب مراجعة كود',
            'priority' => 'منخفض'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('name', 'قالب مراجعة كود');

        $this->assertDatabaseHas('task_templates', [
            'id' => $template->id,
            'name' => 'قالب مراجعة كود'
        ]);
    }

    public function test_can_set_task_template_as_default()
    {
        $tpl1 = TaskTemplate::create(['name' => 'تيمبلت 1', 'is_default' => true]);
        $tpl2 = TaskTemplate::create(['name' => 'تيمبلت 2', 'is_default' => false]);

        $response = $this->postJson("/api/task-templates/{$tpl2->id}/set-default");

        $response->assertStatus(200)
                 ->assertJsonPath('is_default', true);

        $this->assertDatabaseHas('task_templates', ['id' => $tpl1->id, 'is_default' => false]);
        $this->assertDatabaseHas('task_templates', ['id' => $tpl2->id, 'is_default' => true]);
    }

    public function test_can_delete_task_template()
    {
        $template = TaskTemplate::create(['name' => 'قالب مهمة للحذف']);

        $response = $this->deleteJson("/api/task-templates/{$template->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('task_templates', ['id' => $template->id]);
    }
}
