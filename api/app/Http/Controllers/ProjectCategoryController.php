<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\ProjectCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProjectCategoryController extends Controller
{
    private function ensureUserIdColumnExists(): void
    {
        try {
            if (!Schema::hasColumn('project_categories', 'user_id')) {
                Schema::table('project_categories', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
                });
                ProjectCategory::whereNull('user_id')->update(['user_id' => 1]);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to ensure user_id column in project_categories: " . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([]);
        }

        $this->ensureUserIdColumnExists();

        $categories = ProjectCategory::where('user_id', $user->id)
            ->withCount(['projects' => function ($q) use ($user) {
                if (!$user->role || $user->role->name !== 'مدير') {
                    $q->whereHas('users', function ($uq) use ($user) {
                        $uq->where('users.id', $user->id);
                    });
                }
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $this->ensureUserIdColumnExists();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['user_id'] = $user->id;

        $category = ProjectCategory::create($validated);

        try {
            broadcast(new DataChanged($user->id, 'project_categories'))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcasting failed in ProjectCategoryController@store: ' . $e->getMessage());
        }

        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $this->ensureUserIdColumnExists();

        $category = ProjectCategory::findOrFail($id);
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Only the owner of the category can edit it
        if ((int)$category->user_id !== (int)$user->id) {
            return response()->json(['message' => 'غير مصرح بتعديل هذا التصنيف'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $category->update($validated);

        try {
            broadcast(new DataChanged($user->id, 'project_categories'))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcasting failed in ProjectCategoryController@update: ' . $e->getMessage());
        }

        return response()->json($category);
    }

    public function destroy(Request $request, $id)
    {
        $this->ensureUserIdColumnExists();

        $category = ProjectCategory::findOrFail($id);
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Only the owner of the category can delete it
        if ((int)$category->user_id !== (int)$user->id) {
            return response()->json(['message' => 'غير مصرح بحذف هذا التصنيف'], 403);
        }

        $category->delete();

        try {
            broadcast(new DataChanged($user->id, 'project_categories'))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcasting failed in ProjectCategoryController@destroy: ' . $e->getMessage());
        }

        return response()->json(null, 204);
    }
}
