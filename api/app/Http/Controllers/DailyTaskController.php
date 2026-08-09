<?php

namespace App\Http\Controllers;

use App\Models\DailyTask;
use Illuminate\Http\Request;

class DailyTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = DailyTask::query();

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
            'priority' => 'nullable|string',
            'due_time' => 'nullable|string',
            'completed' => 'nullable|boolean'
        ]);

        $task = DailyTask::create([
            'user_id' => $user ? $user->id : null,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? 'عام',
            'priority' => $validated['priority'] ?? 'متوسطة',
            'due_time' => $validated['due_time'] ?? null,
            'completed' => $validated['completed'] ?? false
        ]);

        return response()->json($task, 201);
    }

    public function update(Request $request, $id)
    {
        $task = DailyTask::findOrFail($id);
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string',
            'priority' => 'nullable|string',
            'due_time' => 'nullable|string',
            'completed' => 'nullable|boolean'
        ]);

        $task->update(array_filter($validated, fn($val) => $val !== null));

        return response()->json($task);
    }

    public function destroy(Request $request, $id)
    {
        $task = DailyTask::findOrFail($id);
        $task->delete();

        return response()->json(['message' => 'تم حذف المهمة اليومية بنجاح']);
    }

    public function sync(Request $request)
    {
        $user = $request->user();
        $tasks = $request->input('tasks', []);

        if (is_array($tasks)) {
            foreach ($tasks as $t) {
                if (empty($t['title'])) continue;
                DailyTask::updateOrCreate(
                    ['id' => $t['id'] ?? null],
                    [
                        'user_id' => $user ? $user->id : null,
                        'title' => $t['title'],
                        'category' => $t['category'] ?? 'عام',
                        'priority' => $t['priority'] ?? 'متوسطة',
                        'due_time' => $t['dueTime'] ?? ($t['due_time'] ?? null),
                        'completed' => !empty($t['completed'])
                    ]
                );
            }
        }

        return $this->index($request);
    }
}
