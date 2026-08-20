<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\Idea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IdeaController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->currentUser($request);

        $query = Idea::where('user_id', $user->id);

        if ($request->filled('category') && $request->query('category') !== 'all') {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('color') && $request->query('color') !== 'all') {
            $query->where('color', $request->query('color'));
        }

        if ($request->boolean('pinned_only')) {
            $query->where('is_pinned', true);
        }

        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('content', 'like', $term);
            });
        }

        $ideas = $query->orderBy('is_pinned', 'desc')
                       ->orderBy('sort_order', 'asc')
                       ->orderBy('created_at', 'desc')
                       ->get();

        return response()->json($ideas);
    }

    public function store(Request $request)
    {
        $user = $this->currentUser($request);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'nullable',
            'color' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'is_pinned' => 'nullable|boolean',
            'idea_date' => 'nullable|date',
            'sort_order' => 'nullable|integer',
        ]);

        $maxSortOrder = Idea::where('user_id', $user->id)->max('sort_order') ?? 0;

        $idea = Idea::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'images' => $validated['images'] ?? [],
            'color' => $validated['color'] ?? 'amber',
            'category' => $validated['category'] ?? 'عام',
            'is_pinned' => $validated['is_pinned'] ?? false,
            'idea_date' => $validated['idea_date'] ?? now()->toDateString(),
            'sort_order' => $validated['sort_order'] ?? ($maxSortOrder + 1),
        ]);

        if ($request->user()) {
            broadcast(new DataChanged($user->id, 'ideas'))->toOthers();
        }

        return response()->json($idea, 201);
    }

    public function show(Request $request, $id)
    {
        $idea = $this->currentUser($request)->ideas()->findOrFail($id);
        return response()->json($idea);
    }

    public function update(Request $request, $id)
    {
        $user = $this->currentUser($request);
        $idea = $user->ideas()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'nullable',
            'color' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'is_pinned' => 'nullable|boolean',
            'idea_date' => 'nullable|date',
            'sort_order' => 'nullable|integer',
        ]);

        $idea->update(array_filter($validated, fn($v) => $v !== null));

        if ($request->user()) {
            broadcast(new DataChanged($user->id, 'ideas'))->toOthers();
        }

        return response()->json($idea);
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->currentUser($request);
        $idea = $user->ideas()->findOrFail($id);

        // Delete associated uploaded files if stored locally
        if (is_array($idea->images)) {
            foreach ($idea->images as $img) {
                $path = is_array($img) ? ($img['path'] ?? null) : null;
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        $idea->delete();

        if ($request->user()) {
            broadcast(new DataChanged($user->id, 'ideas'))->toOthers();
        }

        return response()->json(['message' => 'تم حذف الفكرة بنجاح']);
    }

    public function uploadImage(Request $request)
    {
        $user = $this->currentUser($request);

        $request->validate([
            'file' => 'required|file|image|max:20480', // 20 MB max
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $fileName = 'idea_' . time() . '_' . Str::random(8) . '.' . $extension;
        $path = $file->storeAs('ideas', $fileName, 'public');

        $url = asset('storage/' . $path);

        return response()->json([
            'id' => Str::uuid()->toString(),
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'url' => $url,
            'size' => round($file->getSize() / 1024, 1) . ' KB',
            'type' => $file->getMimeType(),
        ], 201);
    }

    public function reorder(Request $request)
    {
        $user = $this->currentUser($request);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $ids = $request->input('ids', []);
        foreach ($ids as $index => $id) {
            Idea::where('user_id', $user->id)
                ->where('id', $id)
                ->update(['sort_order' => $index]);
        }

        if ($request->user()) {
            broadcast(new DataChanged($user->id, 'ideas'))->toOthers();
        }

        return response()->json(['message' => 'تم حفظ الترتيب الجديد']);
    }

    public function sync(Request $request)
    {
        $user = $this->currentUser($request);
        $ideas = $request->input('ideas', []);

        if (is_array($ideas)) {
            foreach ($ideas as $item) {
                if (empty($item['title'])) continue;

                $existing = Idea::where('title', $item['title'])
                                ->where('user_id', $user->id)
                                ->first();

                if ($existing) {
                    $existing->update([
                        'content' => $item['content'] ?? $existing->content,
                        'images' => $item['images'] ?? $existing->images,
                        'color' => $item['color'] ?? $existing->color,
                        'category' => $item['category'] ?? $existing->category,
                        'is_pinned' => isset($item['is_pinned']) ? (bool)$item['is_pinned'] : $existing->is_pinned,
                        'idea_date' => $item['idea_date'] ?? $existing->idea_date,
                        'sort_order' => $item['sort_order'] ?? $existing->sort_order,
                    ]);
                } else {
                    Idea::create([
                        'user_id' => $user->id,
                        'title' => $item['title'],
                        'content' => $item['content'] ?? null,
                        'images' => $item['images'] ?? [],
                        'color' => $item['color'] ?? 'amber',
                        'category' => $item['category'] ?? 'عام',
                        'is_pinned' => !empty($item['is_pinned']),
                        'idea_date' => $item['idea_date'] ?? now()->toDateString(),
                        'sort_order' => $item['sort_order'] ?? 0,
                    ]);
                }
            }
        }

        if ($request->user()) {
            broadcast(new DataChanged($user->id, 'ideas'))->toOthers();
        }

        return $this->index($request);
    }
}
