<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index($projectId)
    {
        $project = $this->authorizedProject($projectId);

        return response()->json($project->tasks()->with(['attachments', 'customFieldValues.definition', 'comments'])->get());
    }

    public function store(Request $request, $projectId)
    {
        $project = $this->authorizedProject($projectId);
        $this->assertPermission('manage-tasks');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
            'priority' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date'
        ]);

        if (!empty($validated['start_date'])) {
            try {
                new \DateTime($validated['start_date']);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'تاريخ غير صالح'], 422);
            }
        }
        if (!empty($validated['deadline'])) {
            try {
                new \DateTime($validated['deadline']);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'تاريخ غير صالح'], 422);
            }
        }

        if (!empty($validated['start_date']) && !empty($validated['deadline'])) {
            try {
                if (new \DateTime($validated['deadline']) < new \DateTime($validated['start_date'])) {
                    return response()->json(['error' => 'تاريخ التسليم لا يمكن أن يكون قبل تاريخ البدء'], 422);
                }
            } catch (\Throwable $e) {
                return response()->json(['error' => 'تاريخ غير صالح'], 422);
            }
        }

        $task = Task::create([
            'project_id' => $projectId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? ($project->statuses[0] ?? 'بانتظار البدء'),
            'priority' => $validated['priority'] ?? 'متوسط',
            'start_date' => $validated['start_date'] ?? null,
            'deadline' => $validated['deadline'] ?? null
        ]);

        broadcast(new DataChanged($request->user()->id, 'tasks', $task->project_id))->toOthers();

        return response()->json($task, 201);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $this->authorizedProject($task->project_id, withTrashed: true);
        $this->assertPermission('manage-tasks');

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
            'priority' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'deadline' => 'nullable|date',
            'project_id' => 'nullable|integer|exists:projects,id'
        ]);

        // Moving a task between projects requires access to the destination too.
        if (!empty($validated['project_id']) && (int) $validated['project_id'] !== (int) $task->project_id) {
            $this->assertProjectAccess(Project::findOrFail($validated['project_id']));
        }

        $start = $request->input('start_date', $task->start_date);
        $end = $request->input('deadline', $task->deadline);

        if (!empty($start)) {
            try {
                new \DateTime($start);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'تاريخ غير صالح'], 422);
            }
        }
        if (!empty($end)) {
            try {
                new \DateTime($end);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'تاريخ غير صالح'], 422);
            }
        }

        if (!empty($start) && !empty($end)) {
            try {
                if (new \DateTime($end) < new \DateTime($start)) {
                    return response()->json(['error' => 'تاريخ التسليم لا يمكن أن يكون قبل تاريخ البدء'], 422);
                }
            } catch (\Throwable $e) {
                return response()->json(['error' => 'تاريخ غير صالح'], 422);
            }
        }

        $task->update($validated);

        broadcast(new DataChanged($request->user()->id, 'tasks', $task->project_id))->toOthers();

        return response()->json($task);
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        $this->authorizedProject($task->project_id, withTrashed: true);
        $this->assertPermission('manage-tasks');

        $projectId = $task->project_id;
        $task->delete();

        broadcast(new DataChanged(request()->user()->id, 'tasks', $projectId))->toOthers();

        return response()->json(['message' => 'تم حذف المهمة بنجاح']);
    }
}
