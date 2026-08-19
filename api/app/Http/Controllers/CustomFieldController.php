<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
    public function storeDefinition(Request $request, $projectId)
    {
        $this->authorizedProject($projectId);
        $this->assertPermission('manage-projects');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:text,number,link'
        ]);

        $field = CustomFieldDefinition::create([
            'project_id' => $projectId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'active' => true
        ]);

        broadcast(new DataChanged($request->user()->id, 'tasks', (int)$projectId))->toOthers();

        return response()->json($field, 201);
    }

    public function deactivateDefinition($projectId, $fieldId)
    {
        $this->authorizedProject($projectId);
        $this->assertPermission('manage-projects');

        $field = CustomFieldDefinition::where('project_id', $projectId)->findOrFail($fieldId);
        $field->update(['active' => false]);

        broadcast(new DataChanged(request()->user()->id, 'tasks', (int)$projectId))->toOthers();

        return response()->json(['message' => 'تم إلغاء تفعيل الحقل المخصص بنجاح']);
    }

    public function setValue(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorizedProject($task->project_id, withTrashed: true);
        $this->assertPermission('manage-tasks');

        $validated = $request->validate([
            'custom_field_definition_id' => 'required|exists:custom_field_definitions,id',
            'value' => 'nullable|string'
        ]);

        $value = CustomFieldValue::updateOrCreate(
            [
                'task_id' => $taskId,
                'custom_field_definition_id' => $validated['custom_field_definition_id']
            ],
            [
                'value' => $validated['value']
            ]
        );

        broadcast(new DataChanged($request->user()->id, 'tasks', $task->project_id))->toOthers();

        return response()->json($value);
    }
}
