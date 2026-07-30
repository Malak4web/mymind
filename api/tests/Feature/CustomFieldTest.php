<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldTest extends TestCase
{
    use RefreshDatabase;

    private $project;
    private $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();

        $this->project = $this->postJson('/api/projects', [
            'name' => 'مشروع الحقول المخصصة',
            'status_source' => 'global'
        ])->json();

        $this->task = $this->postJson("/api/projects/{$this->project['id']}/tasks", [
            'title' => 'مهمة الحقل المخصص'
        ])->json();
    }

    public function test_can_define_custom_field()
    {
        $data = [
            'name' => 'رابط فيجما',
            'type' => 'link'
        ];

        $response = $this->postJson("/api/projects/{$this->project['id']}/custom-fields", $data);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'رابط فيجما')
                 ->assertJsonPath('type', 'link');

        $this->assertDatabaseHas('custom_field_definitions', [
            'project_id' => $this->project['id'],
            'name' => 'رابط فيجما'
        ]);
    }

    public function test_can_deactivate_custom_field()
    {
        $field = $this->postJson("/api/projects/{$this->project['id']}/custom-fields", [
            'name' => 'الساعات المقدرة',
            'type' => 'number'
        ])->json();

        $response = $this->deleteJson("/api/projects/{$this->project['id']}/custom-fields/{$field['id']}");
        $response->assertStatus(200);

        $this->assertDatabaseHas('custom_field_definitions', [
            'id' => $field['id'],
            'active' => false
        ]);
    }

    public function test_can_set_custom_field_value_on_task()
    {
        $field = $this->postJson("/api/projects/{$this->project['id']}/custom-fields", [
            'name' => 'رابط التصميم',
            'type' => 'link'
        ])->json();

        $data = [
            'custom_field_definition_id' => $field['id'],
            'value' => 'https://figma.com/design-file'
        ];

        $response = $this->postJson("/api/tasks/{$this->task['id']}/custom-fields", $data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('custom_field_values', [
            'task_id' => $this->task['id'],
            'custom_field_definition_id' => $field['id'],
            'value' => 'https://figma.com/design-file'
        ]);
    }
}
