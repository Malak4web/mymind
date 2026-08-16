<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProjectCategoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([]);
        }

        $hasUserIdCol = Schema::hasColumn('project_categories', 'user_id');
        $query = ProjectCategory::query();
        
        if ($hasUserIdCol) {
            // Strictly fetch ONLY categories belonging to this authenticated user
            $query->where('user_id', $user->id);
        }
        
        $categories = $query->withCount(['projects' => function ($q) use ($user) {
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        if (Schema::hasColumn('project_categories', 'user_id')) {
            $validated['user_id'] = $user->id;
        }

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
        $category = ProjectCategory::findOrFail($id);
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Only the owner of the category can edit it
        if (isset($category->user_id) && $category->user_id !== null && $category->user_id !== $user->id) {
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
        $category = ProjectCategory::findOrFail($id);
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Only the owner of the category can delete it
        if (isset($category->user_id) && $category->user_id !== null && $category->user_id !== $user->id) {
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
