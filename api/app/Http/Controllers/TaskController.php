<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index($projectId)
    {
        $project = Project::findOrFail($projectId);
        return response()->json($project->tasks()->with(['attachments', 'customFieldValues.definition'])->get());
    }

    public function store(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date'
        ]);

        if (!empty($validated['start_date']) && !empty($validated['deadline'])) {
            if (new \DateTime($validated['deadline']) < new \DateTime($validated['start_date'])) {
                return response()->json(['error' => 'تاريخ التسليم لا يمكن أن يكون قبل تاريخ البدء'], 422);
            }
        }

        $task = Task::create([
            'project_id' => $projectId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? ($project->statuses[0] ?? 'بانتظار البدء'),
            'start_date' => $validated['start_date'] ?? null,
            'deadline' => $validated['deadline'] ?? null
        ]);

        return response()->json($task, 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'project_id' => 'nullable|integer|exists:projects,id'
        ]);

        $start = $request->input('start_date', $task->start_date);
        $end = $request->input('deadline', $task->deadline);

        if (!empty($start) && !empty($end)) {
            if (new \DateTime($end) < new \DateTime($start)) {
                return response()->json(['error' => 'تاريخ التسليم لا يمكن أن يكون قبل تاريخ البدء'], 422);
            }
        }

        $task->update($validated);

        return response()->json($task);
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        $task->delete();

        return response()->json(['message' => 'تم حذف المهمة بنجاح']);
    }
}
