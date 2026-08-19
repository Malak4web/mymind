<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The authenticated user, or a 401 abort.
     */
    protected function currentUser(?Request $request = null): User
    {
        $user = ($request ?? request())->user();

        if (! $user) {
            abort(401, 'يجب تسجيل الدخول للمتابعة.');
        }

        return $user;
    }

    /**
     * Resolve a project the current user is allowed to reach, or abort.
     */
    protected function authorizedProject($projectId, bool $withTrashed = false): Project
    {
        $project = ($withTrashed ? Project::withTrashed() : Project::query())->findOrFail($projectId);

        $this->assertProjectAccess($project);

        return $project;
    }

    /**
     * Abort unless the current user is a member of the project (admins bypass).
     */
    protected function assertProjectAccess(Project $project): void
    {
        $user = $this->currentUser();

        if ($user->isAdmin()) {
            return;
        }

        if (! $project->users()->whereKey($user->id)->exists()) {
            abort(403, 'غير مصرح لك بالوصول إلى هذا المشروع.');
        }
    }

    /**
     * Abort unless the current user holds the given permission (admins bypass).
     */
    protected function assertPermission(string $slug): void
    {
        if (! $this->currentUser()->hasPermission($slug)) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية.');
        }
    }
}
