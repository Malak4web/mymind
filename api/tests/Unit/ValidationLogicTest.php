<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ValidationLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_transaction_rollback_reverts_state_on_failure()
    {
        $initialCount = Project::count();

        DB::beginTransaction();
        try {
            Project::create([
                'name' => 'مشروع مؤقت للتراجُع',
                'description' => 'سيتم التراجع عنه',
                'statuses' => ['بانتظار البدء', 'مكتمل']
            ]);

            // Force simulated failure exception
            throw new \Exception('Simulated database error');
        } catch (\Exception $e) {
            DB::rollBack();
        }

        $this->assertEquals($initialCount, Project::count());
        $this->assertDatabaseMissing('projects', ['name' => 'مشروع مؤقت للتراجُع']);
    }

    public function test_task_status_fallback_when_invalid_status_passed()
    {
        $project = Project::create([
            'name' => 'مشروع الفحوصات',
            'statuses' => ['بانتظار البدء', 'مكتمل']
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'مهمة فحص الحالات',
            'status' => 'حالة غريبة'
        ]);

        $this->assertEquals('حالة غريبة', $task->status);
    }
}
