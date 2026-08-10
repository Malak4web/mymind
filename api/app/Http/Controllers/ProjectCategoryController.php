<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\ProjectCategory;
use Illuminate\Http\Request;

class ProjectCategoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $categories = ProjectCategory::where(function ($query) use ($user) {
                if ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhereNull('user_id');
                } else {
                    $query->whereNull('user_id');
                }
            })
            ->withCount(['projects' => function ($q) use ($user) {
                if ($user && (!$user->role || $user->role->name !== 'مدير')) {
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        if ($request->user()) {
            $validated['user_id'] = $request->user()->id;
        }

        $category = ProjectCategory::create($validated);

        broadcast(new DataChanged($request->user()->id, 'project_categories'));

        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $category = ProjectCategory::findOrFail($id);
        $user = $request->user();

        if ($user && $category->user_id && $category->user_id !== $user->id && (!$user->role || $user->role->name !== 'مدير')) {
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

        broadcast(new DataChanged($request->user()->id, 'project_categories'));

        return response()->json($category);
    }

    public function destroy(Request $request, $id)
    {
        $category = ProjectCategory::findOrFail($id);
        $user = $request->user();

        if ($user && $category->user_id && $category->user_id !== $user->id && (!$user->role || $user->role->name !== 'مدير')) {
            return response()->json(['message' => 'غير مصرح بحذف هذا التصنيف'], 403);
        }

        $category->delete();

        broadcast(new DataChanged($request->user()->id, 'project_categories'));

        return response()->json(null, 204);
    }
}
