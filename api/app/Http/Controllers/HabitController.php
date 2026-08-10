<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Events\DataChanged;
use Illuminate\Http\Request;

class HabitController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Habit::query();

        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            });
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'time_of_day' => 'nullable|string',
            'type' => 'nullable|string',
            'target_value' => 'nullable|integer',
            'unit' => 'nullable|string',
            'frequency' => 'nullable|array',
            'logs' => 'nullable|array',
            'notes_list' => 'nullable|array',
            'checklist' => 'nullable|array'
        ]);

        $habit = Habit::create([
            'user_id' => $user ? $user->id : null,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? 'عام',
            'icon' => $validated['icon'] ?? '📌',
            'color' => $validated['color'] ?? 'from-blue-500 to-indigo-500',
            'time_of_day' => $validated['time_of_day'] ?? 'anytime',
            'type' => $validated['type'] ?? 'boolean',
            'target_value' => $validated['target_value'] ?? 1,
            'unit' => $validated['unit'] ?? 'مرة',
            'frequency' => $validated['frequency'] ?? [0, 1, 2, 3, 4, 5, 6],
            'logs' => $validated['logs'] ?? (object)[],
            'notes_list' => $validated['notes_list'] ?? [],
            'checklist' => $validated['checklist'] ?? []
        ]);

        return response()->json($habit, 201);
    }

    public function update(Request $request, $id)
    {
        $habit = Habit::findOrFail($id);
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'time_of_day' => 'nullable|string',
            'type' => 'nullable|string',
            'target_value' => 'nullable|integer',
            'unit' => 'nullable|string',
            'frequency' => 'nullable|array',
            'logs' => 'nullable|array',
            'notes_list' => 'nullable|array',
            'checklist' => 'nullable|array'
        ]);

        $habit->update(array_filter($validated, fn($val) => $val !== null));

        if ($request->user()) {
            broadcast(new DataChanged($request->user()->id, 'habits'))->toOthers();
        }

        return response()->json($habit);
    }

    public function destroy(Request $request, $id)
    {
        $habit = Habit::findOrFail($id);
        $habit->delete();

        if ($request->user()) {
            broadcast(new DataChanged($request->user()->id, 'habits'))->toOthers();
        }

        return response()->json(['message' => 'تم حذف العادة بنجاح']);
    }

    public function sync(Request $request)
    {
        $user = $request->user();
        $habits = $request->input('habits', []);

        if (is_array($habits)) {
            foreach ($habits as $h) {
                if (empty($h['title'])) continue;

                $existing = Habit::where('title', $h['title'])
                    ->where(function($q) use ($user) {
                        if ($user) {
                            $q->where('user_id', $user->id)->orWhereNull('user_id');
                        }
                    })->first();

                if ($existing) {
                    $existing->update([
                        'category' => $h['category'] ?? $existing->category,
                        'icon' => $h['icon'] ?? $existing->icon,
                        'color' => $h['color'] ?? $existing->color,
                        'time_of_day' => $h['timeOfDay'] ?? ($h['time_of_day'] ?? $existing->time_of_day),
                        'type' => $h['type'] ?? $existing->type,
                        'target_value' => $h['targetValue'] ?? ($h['target_value'] ?? $existing->target_value),
                        'unit' => $h['unit'] ?? $existing->unit,
                        'frequency' => $h['frequency'] ?? $existing->frequency,
                        'logs' => $h['logs'] ?? $existing->logs,
                        'notes_list' => $h['notesList'] ?? ($h['notes_list'] ?? $existing->notes_list),
                        'checklist' => $h['checklist'] ?? $existing->checklist
                    ]);
                } else {
                    Habit::create([
                        'user_id' => $user ? $user->id : null,
                        'title' => $h['title'],
                        'category' => $h['category'] ?? 'عام',
                        'icon' => $h['icon'] ?? '📌',
                        'color' => $h['color'] ?? 'from-blue-500 to-indigo-500',
                        'time_of_day' => $h['timeOfDay'] ?? ($h['time_of_day'] ?? 'anytime'),
                        'type' => $h['type'] ?? 'boolean',
                        'target_value' => $h['targetValue'] ?? ($h['target_value'] ?? 1),
                        'unit' => $h['unit'] ?? 'مرة',
                        'frequency' => $h['frequency'] ?? [0, 1, 2, 3, 4, 5, 6],
                        'logs' => $h['logs'] ?? (object)[],
                        'notes_list' => $h['notesList'] ?? ($h['notes_list'] ?? []),
                        'checklist' => $h['checklist'] ?? []
                    ]);
                }
            }
        }

        return $this->index($request);
    }
}
