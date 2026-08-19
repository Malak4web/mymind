<?php

namespace App\Http\Controllers;

use App\Models\DailyTask;
use App\Events\DataChanged;
use Illuminate\Http\Request;

class DailyTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->currentUser($request);

        $query = DailyTask::where('user_id', $user->id);

        // The journal is date-navigated; without this filter every day showed
        // the same list forever.
        if ($request->filled('date')) {
            $query->whereDate('due_date', $request->query('date'));
        } elseif ($request->filled('from') && $request->filled('to')) {
            $query->whereDate('due_date', '>=', $request->query('from'))
                  ->whereDate('due_date', '<=', $request->query('to'));
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $user = $this->currentUser($request);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string',
            'priority' => 'nullable|string',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|string',
            'completed' => 'nullable|boolean'
        ]);

        $task = DailyTask::create([
            'user_id' => $user->id,
            'due_date' => $validated['due_date'] ?? now()->toDateString(),
            'title' => $validated['title'],
            'category' => $validated['category'] ?? 'عام',
            'priority' => $validated['priority'] ?? 'متوسطة',
            'due_time' => $validated['due_time'] ?? null,
            'completed' => $validated['completed'] ?? false
        ]);

        if ($request->user()) {
            broadcast(new DataChanged($request->user()->id, 'daily_tasks'))->toOthers();
        }

        return response()->json($task, 201);
    }

    public function update(Request $request, $id)
    {
        $task = $this->currentUser($request)->dailyTasks()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string',
            'priority' => 'nullable|string',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|string',
            'completed' => 'nullable|boolean'
        ]);

        $task->update(array_filter($validated, fn($val) => $val !== null));

        if ($request->user()) {
            broadcast(new DataChanged($request->user()->id, 'daily_tasks'))->toOthers();
        }

        return response()->json($task);
    }

    public function destroy(Request $request, $id)
    {
        $task = $this->currentUser($request)->dailyTasks()->findOrFail($id);
        $task->delete();

        if ($request->user()) {
            broadcast(new DataChanged($request->user()->id, 'daily_tasks'))->toOthers();
        }

        return response()->json(['message' => 'تم حذف المهمة اليومية بنجاح']);
    }

    public function sync(Request $request)
    {
        $user = $this->currentUser($request);
        $tasks = $request->input('tasks', []);

        if (is_array($tasks)) {
            foreach ($tasks as $t) {
                if (empty($t['title'])) continue;
                
                $existing = DailyTask::where('title', $t['title'])
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'category' => $t['category'] ?? $existing->category,
                        'priority' => $t['priority'] ?? $existing->priority,
                        'due_time' => $t['dueTime'] ?? ($t['due_time'] ?? $existing->due_time),
                        'completed' => isset($t['completed']) ? (bool)$t['completed'] : $existing->completed
                    ]);
                } else {
                    DailyTask::create([
                        'user_id' => $user->id,
                        'due_date' => $t['dueDate'] ?? ($t['due_date'] ?? now()->toDateString()),
                        'title' => $t['title'],
                        'category' => $t['category'] ?? 'عام',
                        'priority' => $t['priority'] ?? 'متوسطة',
                        'due_time' => $t['dueTime'] ?? ($t['due_time'] ?? null),
                        'completed' => !empty($t['completed'])
                    ]);
                }
            }
        }

        if ($request->user()) {
            broadcast(new DataChanged($request->user()->id, 'daily_tasks'))->toOthers();
        }

        return $this->index($request);
    }
}
