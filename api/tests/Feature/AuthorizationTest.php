<?php

namespace Tests\Feature;

use App\Models\DailyTask;
use App\Models\Folder;
use App\Models\Habit;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Proves the Phase 0 authorization fixes: a logged-in user must not be able to
 * reach another user's data by guessing ids.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $outsider;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $manageProjects = Permission::create(['name' => 'إدارة المشاريع', 'slug' => 'manage-projects']);
        $manageTasks = Permission::create(['name' => 'إدارة المهام', 'slug' => 'manage-tasks']);

        $memberRole = Role::create(['name' => 'عضو', 'description' => 'عضو']);
        $memberRole->permissions()->attach([$manageProjects->id, $manageTasks->id]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@mymind.com',
            'password' => bcrypt('secret-owner-pass'),
            'role_id' => $memberRole->id,
        ]);

        $this->outsider = User::create([
            'name' => 'Outsider',
            'email' => 'outsider@mymind.com',
            'password' => bcrypt('secret-outsider-pass'),
            'role_id' => $memberRole->id,
        ]);

        $this->project = Project::create([
            'name' => 'مشروع خاص',
            'statuses' => ['بانتظار البدء', 'مكتمل'],
        ]);
        $this->project->users()->attach($this->owner->id);
    }

    private function actAsOutsider(): void
    {
        Sanctum::actingAs($this->outsider);
    }

    private function makeProjectFor(User $user): Project
    {
        $project = Project::create([
            'name' => 'مشروع رقم '.$user->id,
            'statuses' => ['بانتظار البدء'],
        ]);
        $project->users()->attach($user->id);

        return $project;
    }

    public function test_outsider_cannot_read_or_mutate_another_users_project()
    {
        $this->actAsOutsider();

        $this->getJson("/api/projects/{$this->project->id}")->assertStatus(403);
        $this->putJson("/api/projects/{$this->project->id}", ['name' => 'مسروق'])->assertStatus(403);
        $this->deleteJson("/api/projects/{$this->project->id}")->assertStatus(403);
        $this->postJson("/api/projects/{$this->project->id}/statuses", ['status' => 'جديد'])->assertStatus(403);
        $this->deleteJson("/api/projects/{$this->project->id}/statuses", ['status' => 'مكتمل'])->assertStatus(403);

        $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'name' => 'مشروع خاص']);
    }

    public function test_outsider_cannot_list_or_create_tasks_in_another_users_project()
    {
        $this->actAsOutsider();

        $this->getJson("/api/projects/{$this->project->id}/tasks")->assertStatus(403);
        $this->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'مهمة دخيلة'])->assertStatus(403);

        $this->assertDatabaseMissing('tasks', ['title' => 'مهمة دخيلة']);
    }

    public function test_outsider_cannot_update_or_delete_another_users_task()
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'title' => 'مهمة سرية',
            'status' => 'بانتظار البدء',
        ]);

        $this->actAsOutsider();

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'تم الاختراق'])->assertStatus(403);
        $this->deleteJson("/api/tasks/{$task->id}")->assertStatus(403);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'مهمة سرية']);
    }

    public function test_task_cannot_be_moved_into_a_project_the_user_cannot_reach()
    {
        $ownProject = $this->makeProjectFor($this->outsider);
        $ownTask = Task::create([
            'project_id' => $ownProject->id,
            'title' => 'مهمتي',
            'status' => 'بانتظار البدء',
        ]);

        $this->actAsOutsider();

        $this->putJson("/api/tasks/{$ownTask->id}", [
            'title' => 'مهمتي',
            'project_id' => $this->project->id,
        ])->assertStatus(403);

        $this->assertDatabaseHas('tasks', ['id' => $ownTask->id, 'project_id' => $ownProject->id]);
    }

    public function test_outsider_cannot_reach_folders_files_or_notes_of_another_project()
    {
        $folder = Folder::create(['project_id' => $this->project->id, 'name' => 'مجلد خاص']);
        $note = Note::create(['project_id' => $this->project->id, 'title' => 'ملاحظة خاصة']);

        $this->actAsOutsider();

        $this->getJson("/api/projects/{$this->project->id}/folders")->assertStatus(403);
        $this->postJson("/api/projects/{$this->project->id}/folders", ['name' => 'مجلد دخيل'])->assertStatus(403);
        $this->deleteJson("/api/folders/{$folder->id}")->assertStatus(403);

        $this->getJson("/api/projects/{$this->project->id}/notes")->assertStatus(403);
        $this->putJson("/api/notes/{$note->id}", ['title' => 'مسروقة'])->assertStatus(403);
        $this->deleteJson("/api/notes/{$note->id}")->assertStatus(403);

        $this->getJson("/api/projects/{$this->project->id}/project-files")->assertStatus(403);

        $this->assertDatabaseHas('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'title' => 'ملاحظة خاصة']);
    }

    public function test_outsider_cannot_touch_custom_fields_of_another_project()
    {
        $this->actAsOutsider();

        $this->postJson("/api/projects/{$this->project->id}/custom-fields", [
            'name' => 'حقل دخيل',
            'type' => 'text',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('custom_field_definitions', ['name' => 'حقل دخيل']);
    }

    public function test_viewer_role_can_read_but_cannot_write()
    {
        $viewerRole = Role::create(['name' => 'مشاهد', 'description' => 'قراءة فقط']);
        $viewer = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@mymind.com',
            'password' => bcrypt('secret-viewer-pass'),
            'role_id' => $viewerRole->id,
        ]);
        $this->project->users()->attach($viewer->id);

        Sanctum::actingAs($viewer);

        // A viewer is a member, so reading is allowed...
        $this->getJson("/api/projects/{$this->project->id}/tasks")->assertStatus(200);

        // ...but every write is refused.
        $this->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'من مشاهد'])->assertStatus(403);
        $this->putJson("/api/projects/{$this->project->id}", ['name' => 'من مشاهد'])->assertStatus(403);
        $this->postJson('/api/projects', ['name' => 'مشروع مشاهد'])->assertStatus(403);

        $this->assertDatabaseMissing('tasks', ['title' => 'من مشاهد']);
    }

    public function test_habits_and_daily_tasks_are_isolated_per_user()
    {
        $habit = Habit::create(['user_id' => $this->owner->id, 'title' => 'عادة خاصة']);
        $daily = DailyTask::create(['user_id' => $this->owner->id, 'title' => 'يومية خاصة']);

        // Legacy unowned rows must not leak to anyone.
        Habit::create(['user_id' => null, 'title' => 'عادة يتيمة']);

        $this->actAsOutsider();

        $this->getJson('/api/habits')->assertStatus(200)->assertJsonCount(0);
        $this->getJson('/api/daily-tasks')->assertStatus(200)->assertJsonCount(0);

        $this->putJson("/api/habits/{$habit->id}", ['title' => 'مسروقة'])->assertStatus(404);
        $this->deleteJson("/api/habits/{$habit->id}")->assertStatus(404);
        $this->putJson("/api/daily-tasks/{$daily->id}", ['title' => 'مسروقة'])->assertStatus(404);
        $this->deleteJson("/api/daily-tasks/{$daily->id}")->assertStatus(404);

        $this->assertDatabaseHas('habits', ['id' => $habit->id, 'title' => 'عادة خاصة']);
        $this->assertDatabaseHas('daily_tasks', ['id' => $daily->id, 'title' => 'يومية خاصة']);
    }

    public function test_notifications_are_scoped_to_their_owner()
    {
        $ownerNotification = Notification::create([
            'user_id' => $this->owner->id,
            'title' => 'خاص',
            'text' => 'محتوى خاص',
            'is_read' => false,
        ]);

        $this->actAsOutsider();

        $this->getJson('/api/notifications')->assertStatus(200)->assertJsonCount(0);
        $this->postJson("/api/notifications/{$ownerNotification->id}/read")->assertStatus(404);
        $this->postJson('/api/notifications/read-all')->assertStatus(200);

        // The owner's notification is untouched.
        $this->assertDatabaseHas('notifications', ['id' => $ownerNotification->id, 'is_read' => false]);
    }

    public function test_digest_routes_require_an_administrative_permission()
    {
        $this->actAsOutsider();

        $this->getJson('/api/digest/queue')->assertStatus(403);
        $this->getJson('/api/digest/emails')->assertStatus(403);
        $this->postJson('/api/digest/send')->assertStatus(403);
    }
}
