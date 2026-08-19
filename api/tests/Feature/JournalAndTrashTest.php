<?php

namespace Tests\Feature;

use App\Models\DailyNote;
use App\Models\DailyTask;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the three features whose UI worked while nothing stood behind it:
 * journal notes, date-scoped daily tasks, and the project bin.
 */
class JournalAndTrashTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $manageProjects = Permission::create(['name' => 'إدارة المشاريع', 'slug' => 'manage-projects']);
        $role = Role::create(['name' => 'عضو', 'description' => 'عضو']);
        $role->permissions()->attach($manageProjects->id);

        $this->user = User::create([
            'name' => 'صاحب اليوميات', 'email' => 'owner@mymind.com',
            'password' => bcrypt('a-long-test-password'), 'role_id' => $role->id,
        ]);
        $this->other = User::create([
            'name' => 'مستخدم آخر', 'email' => 'other@mymind.com',
            'password' => bcrypt('a-long-test-password'), 'role_id' => $role->id,
        ]);
    }

    // ── Journal notes ───────────────────────────────────────────────

    public function test_a_journal_note_persists_and_is_scoped_to_its_date()
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/daily-notes', [
            'note_date' => '2026-08-19',
            'content' => 'ملاحظة اليوم',
        ])->assertStatus(201)->assertJsonPath('content', 'ملاحظة اليوم');

        $this->postJson('/api/daily-notes', [
            'note_date' => '2026-08-18',
            'content' => 'ملاحظة أمس',
        ])->assertStatus(201);

        // Survives a reload — the whole point.
        $this->getJson('/api/daily-notes')->assertStatus(200)->assertJsonCount(2);

        // And the day navigator actually narrows it.
        $this->getJson('/api/daily-notes?from=2026-08-19&to=2026-08-19')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.content', 'ملاحظة اليوم');
    }

    public function test_journal_notes_are_private_to_their_owner()
    {
        $note = DailyNote::create([
            'user_id' => $this->user->id,
            'note_date' => '2026-08-19',
            'content' => 'خاصة جداً',
        ]);

        Sanctum::actingAs($this->other);

        $this->getJson('/api/daily-notes')->assertStatus(200)->assertJsonCount(0);
        $this->deleteJson("/api/daily-notes/{$note->id}")->assertStatus(404);

        $this->assertDatabaseHas('daily_notes', ['id' => $note->id]);
    }

    // ── Date-scoped daily tasks ─────────────────────────────────────

    public function test_daily_tasks_are_filtered_by_date()
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/daily-tasks', ['title' => 'مهمة اليوم', 'due_date' => '2026-08-19'])
            ->assertStatus(201);
        $this->postJson('/api/daily-tasks', ['title' => 'مهمة بكرة', 'due_date' => '2026-08-20'])
            ->assertStatus(201);

        $this->getJson('/api/daily-tasks?date=2026-08-19')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'مهمة اليوم');

        // Unfiltered still returns everything, so nothing regresses.
        $this->getJson('/api/daily-tasks')->assertStatus(200)->assertJsonCount(2);
    }

    public function test_a_daily_task_without_an_explicit_date_lands_on_today()
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/daily-tasks', ['title' => 'بدون تاريخ'])->assertStatus(201);

        $this->assertDatabaseHas('daily_tasks', [
            'title' => 'بدون تاريخ',
            'due_date' => now()->toDateString(),
        ]);
    }

    // ── Project bin ─────────────────────────────────────────────────

    public function test_a_deleted_project_is_reachable_in_the_bin_and_restorable()
    {
        Sanctum::actingAs($this->user);

        $project = Project::create(['name' => 'مشروع للحذف', 'statuses' => ['بانتظار البدء']]);
        $project->users()->attach($this->user->id);

        $this->deleteJson("/api/projects/{$project->id}")->assertStatus(200);

        // Gone from the live listing...
        $this->getJson('/api/projects')->assertStatus(200)->assertJsonCount(0);

        // ...but the bin can actually see it. Before this it was unreachable.
        $this->getJson('/api/projects?trashed=1')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $project->id)
            ->assertJsonPath('0.is_deleted', true);

        $this->postJson("/api/projects/{$project->id}/restore")->assertStatus(200);

        $this->getJson('/api/projects')->assertStatus(200)->assertJsonCount(1);
        $this->getJson('/api/projects?trashed=1')->assertStatus(200)->assertJsonCount(0);
    }

    public function test_the_bin_does_not_leak_other_peoples_projects()
    {
        $theirs = Project::create(['name' => 'مشروع غيري', 'statuses' => ['بانتظار البدء']]);
        $theirs->users()->attach($this->other->id);
        $theirs->update(['is_deleted' => true]);
        $theirs->delete();

        Sanctum::actingAs($this->user);

        $this->getJson('/api/projects?trashed=1')->assertStatus(200)->assertJsonCount(0);
        $this->postJson("/api/projects/{$theirs->id}/restore")->assertStatus(403);
    }
}
