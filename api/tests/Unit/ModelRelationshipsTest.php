<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Task;
use App\Models\Folder;
use App\Models\Note;
use App\Models\ProjectFile;
use App\Models\ProjectCategory;
use App\Models\ProjectTemplate;
use App\Models\TaskTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_role()
    {
        $role = Role::create(['name' => 'مدير', 'description' => 'مدير النظام']);
        $user = User::create([
            'name' => 'خالد',
            'email' => 'khaled@mymind.com',
            'password' => bcrypt('password123'),
            'role_id' => $role->id
        ]);

        $this->assertInstanceOf(Role::class, $user->role);
        $this->assertEquals('مدير', $user->role->name);
    }

    public function test_role_belongs_to_many_permissions()
    {
        $role = Role::create(['name' => 'عضو']);
        $permission = Permission::create(['name' => 'إدارة المهام', 'slug' => 'manage-tasks']);

        $role->permissions()->attach($permission->id);

        $this->assertTrue($role->permissions->contains($permission));
        $this->assertEquals(1, $role->permissions->count());
    }

    public function test_project_has_many_tasks()
    {
        $category = ProjectCategory::create(['name' => 'برمجة']);
        $project = Project::create([
            'name' => 'مشروع رئيسي',
            'category_id' => $category->id,
            'statuses' => ['بانتظار البدء', 'قيد العمل', 'مكتمل']
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'مهمة 1',
            'status' => 'بانتظار البدء'
        ]);

        $this->assertInstanceOf(ProjectCategory::class, $project->category);
        $this->assertTrue($project->tasks->contains($task));
        $this->assertTrue($category->projects->contains($project));
    }

    public function test_folder_parent_and_subfolder_relationship()
    {
        $project = Project::create(['name' => 'مشروع', 'statuses' => ['بانتظار البدء']]);
        $parent = Folder::create(['project_id' => $project->id, 'name' => 'رئيسي']);
        $child = Folder::create(['project_id' => $project->id, 'parent_id' => $parent->id, 'name' => 'فرعي']);

        $this->assertInstanceOf(Folder::class, $child->parent);
        $this->assertEquals('رئيسي', $child->parent->name);
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_project_template_default_casts()
    {
        $template = ProjectTemplate::create([
            'name' => 'قالب بايثون',
            'statuses' => ['خطوة 1', 'خطوة 2'],
            'is_default' => true
        ]);

        $this->assertIsArray($template->statuses);
        $this->assertTrue($template->is_default);
    }
}
