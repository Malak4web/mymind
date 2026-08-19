<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the Phase 0 hardening that is not about per-user authorization:
 * upload whitelisting, signed file URLs, login throttling, and the removal
 * of the public migration endpoint.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $manageTasks = Permission::create(['name' => 'إدارة المهام', 'slug' => 'manage-tasks']);
        $manageProjects = Permission::create(['name' => 'إدارة المشاريع', 'slug' => 'manage-projects']);
        $role = Role::create(['name' => 'عضو', 'description' => 'عضو']);
        $role->permissions()->attach([$manageTasks->id, $manageProjects->id]);

        $this->user = User::create([
            'name' => 'Uploader',
            'email' => 'uploader@mymind.com',
            'password' => bcrypt('secret-uploader-pass'),
            'role_id' => $role->id,
        ]);

        $this->project = Project::create(['name' => 'مشروع الرفع', 'statuses' => ['بانتظار البدء']]);
        $this->project->users()->attach($this->user->id);

        $this->task = Task::create([
            'project_id' => $this->project->id,
            'title' => 'مهمة المرفقات',
            'status' => 'بانتظار البدء',
        ]);

        Sanctum::actingAs($this->user);
    }

    /**
     * @return array<int, string>
     */
    public static function executableFileNames(): array
    {
        return [
            ['shell.php'],
            ['shell.phtml'],
            ['shell.phar'],
            ['shell.php5'],
            ['payload.cgi'],
            ['payload.sh'],
            ['payload.html'],
            ['payload.svg'],
        ];
    }

    #[DataProvider('executableFileNames')]
    public function test_executable_uploads_are_rejected_on_task_attachments(string $name)
    {
        $response = $this->postJson("/api/tasks/{$this->task->id}/attachments", [
            'file' => UploadedFile::fake()->createWithContent($name, '<?php echo "pwned"; ?>'),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('attachments', ['name' => $name]);
        Storage::disk('public')->assertDirectoryEmpty('attachments');
    }

    #[DataProvider('executableFileNames')]
    public function test_executable_uploads_are_rejected_on_project_files(string $name)
    {
        $response = $this->postJson("/api/projects/{$this->project->id}/project-files", [
            'file' => UploadedFile::fake()->createWithContent($name, '<?php echo "pwned"; ?>'),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('project_files', ['name' => $name]);
    }

    public function test_allowed_uploads_still_work()
    {
        $this->postJson("/api/tasks/{$this->task->id}/attachments", [
            'file' => UploadedFile::fake()->image('design.png'),
        ])->assertStatus(201);

        $this->postJson("/api/projects/{$this->project->id}/project-files", [
            'file' => UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'),
        ])->assertStatus(201);

        $this->assertDatabaseHas('attachments', ['name' => 'design.png']);
        $this->assertDatabaseHas('project_files', ['name' => 'brief.pdf']);
    }

    public function test_oversized_attachments_are_rejected()
    {
        $response = $this->postJson("/api/tasks/{$this->task->id}/attachments", [
            'file' => UploadedFile::fake()->create('huge.pdf', 25000, 'application/pdf'),
        ]);

        $response->assertStatus(422);
    }

    public function test_attachment_file_requires_a_valid_signature()
    {
        $this->postJson("/api/tasks/{$this->task->id}/attachments", [
            'file' => UploadedFile::fake()->image('preview.png'),
        ])->assertStatus(201);

        $attachment = Attachment::firstOrFail();

        // Guessing the id is no longer enough.
        $this->get("/api/attachments/{$attachment->id}/file")->assertStatus(403);
        $this->get("/api/attachments/{$attachment->id}/file?signature=deadbeef&expires=99999999999")
            ->assertStatus(403);

        // The signed URL handed out by the API does work.
        $this->get($attachment->url)->assertStatus(200);
    }

    public function test_served_attachment_carries_anti_sniffing_headers()
    {
        $this->postJson("/api/tasks/{$this->task->id}/attachments", [
            'file' => UploadedFile::fake()->image('preview.png'),
        ])->assertStatus(201);

        $attachment = Attachment::firstOrFail();

        $this->get($attachment->url)
            ->assertStatus(200)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_public_migration_endpoint_is_gone()
    {
        $this->getJson('/api/run-migrate')->assertStatus(404);
    }

    public function test_login_is_rate_limited()
    {
        RateLimiter::clear('');
        $this->app['auth']->forgetGuards();

        $attempt = fn () => $this->postJson('/api/login', [
            'email' => 'uploader@mymind.com',
            'password' => 'definitely-not-the-password',
        ]);

        // The limiter allows 5 attempts per minute.
        for ($i = 0; $i < 5; $i++) {
            $attempt()->assertStatus(422);
        }

        $attempt()->assertStatus(429);
    }
}
