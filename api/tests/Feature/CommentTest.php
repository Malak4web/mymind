<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $teammate;
    private User $outsider;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $manageTasks = Permission::create(['name' => 'إدارة المهام', 'slug' => 'manage-tasks']);
        $role = Role::create(['name' => 'عضو', 'description' => 'عضو']);
        $role->permissions()->attach($manageTasks->id);

        $mk = fn (string $email, string $name) => User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('a-long-test-password'),
            'role_id' => $role->id,
        ]);

        $this->author = $mk('author@mymind.com', 'صاحب التعليق');
        $this->teammate = $mk('teammate@mymind.com', 'زميل');
        $this->outsider = $mk('outsider@mymind.com', 'دخيل');

        $this->project = Project::create(['name' => 'مشروع', 'statuses' => ['بانتظار البدء']]);
        $this->project->users()->attach([$this->author->id, $this->teammate->id]);

        $this->task = Task::create([
            'project_id' => $this->project->id,
            'title' => 'مهمة',
            'status' => 'بانتظار البدء',
        ]);
    }

    public function test_a_comment_is_persisted_and_returned()
    {
        Sanctum::actingAs($this->author);

        $this->postJson("/api/tasks/{$this->task->id}/comments", ['body' => 'أول تعليق حقيقي'])
            ->assertStatus(201)
            ->assertJsonPath('body', 'أول تعليق حقيقي')
            ->assertJsonPath('author_name', 'صاحب التعليق');

        // The point of the whole feature: it survives a reload.
        $this->getJson("/api/tasks/{$this->task->id}/comments")
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.body', 'أول تعليق حقيقي');

        $this->assertDatabaseHas('comments', [
            'task_id' => $this->task->id,
            'user_id' => $this->author->id,
            'body' => 'أول تعليق حقيقي',
        ]);
    }

    public function test_comments_ride_along_with_the_task_listing()
    {
        Comment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->author->id,
            'author_name' => 'صاحب التعليق',
            'body' => 'تعليق محمّل مع المهمة',
        ]);

        Sanctum::actingAs($this->teammate);

        $this->getJson("/api/projects/{$this->project->id}/tasks")
            ->assertStatus(200)
            ->assertJsonPath('0.comments.0.body', 'تعليق محمّل مع المهمة');
    }

    public function test_outsider_cannot_read_or_write_comments()
    {
        Sanctum::actingAs($this->outsider);

        $this->getJson("/api/tasks/{$this->task->id}/comments")->assertStatus(403);
        $this->postJson("/api/tasks/{$this->task->id}/comments", ['body' => 'دخيل'])->assertStatus(403);

        $this->assertDatabaseMissing('comments', ['body' => 'دخيل']);
    }

    public function test_only_the_author_or_an_admin_can_delete_a_comment()
    {
        $comment = Comment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->author->id,
            'author_name' => 'صاحب التعليق',
            'body' => 'تعليقي',
        ]);

        // A teammate on the same project still may not delete someone else's comment.
        Sanctum::actingAs($this->teammate);
        $this->deleteJson("/api/comments/{$comment->id}")->assertStatus(403);
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);

        // The author can.
        Sanctum::actingAs($this->author);
        $this->deleteJson("/api/comments/{$comment->id}")->assertStatus(200);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_deleting_a_task_removes_its_comments()
    {
        Comment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->author->id,
            'author_name' => 'صاحب التعليق',
            'body' => 'سيُحذف مع المهمة',
        ]);

        Sanctum::actingAs($this->author);
        $this->deleteJson("/api/tasks/{$this->task->id}")->assertStatus(200);

        $this->assertDatabaseMissing('comments', ['body' => 'سيُحذف مع المهمة']);
    }
}
