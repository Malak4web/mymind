<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EmailDigestTest extends TestCase
{
    use RefreshDatabase;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();

        $this->project = $this->postJson('/api/projects', [
            'name' => 'مشروع تجميع البريد',
            'status_source' => 'global'
        ])->json();
    }

    public function test_task_creation_queues_email_digest()
    {
        $data = [
            'title' => 'مهمة تجميع البريد',
            'status' => 'بانتظار البدء'
        ];

        $this->postJson("/api/projects/{$this->project['id']}/tasks", $data);

        // Check if database queues digest
        $this->assertDatabaseHas('email_digest_queues', [
            'update_text' => 'تم إنشاء المهمة "مهمة تجميع البريد".'
        ]);
    }

    public function test_artisan_command_sends_batched_email_and_clears_queue()
    {
        // 1. Create a task (which queues a digest entry)
        $this->postJson("/api/projects/{$this->project['id']}/tasks", [
            'title' => 'المهمة الأولى',
            'status' => 'بانتظار البدء'
        ]);

        // 2. Create another task (queues second entry)
        $this->postJson("/api/projects/{$this->project['id']}/tasks", [
            'title' => 'المهمة الثانية',
            'status' => 'بانتظار البدء'
        ]);

        // Verify queue has 2 records
        $this->assertDatabaseCount('email_digest_queues', 2);

        // 3. Trigger command
        $exitCode = Artisan::call('digest:send');
        $this->assertEquals(0, $exitCode);

        // Verify queue is cleared
        $this->assertDatabaseCount('email_digest_queues', 0);

        // Verify a batched email record is created in DB for frontend console logs feed
        $this->assertDatabaseHas('batched_emails', [
            'count' => 2
        ]);
    }
}
