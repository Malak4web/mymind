<?php

namespace App\Http\Controllers;

use App\Models\TaskTemplate;
use Illuminate\Http\Request;

class TaskTemplateController extends Controller
{
    public function index()
    {
        return response()->json(TaskTemplate::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:255',
            'custom_fields_values' => 'nullable|array',
            'priority' => 'nullable|string|max:255',
            'start_date_offset' => 'nullable|integer',
            'due_date_offset' => 'nullable|integer',
            'attachments' => 'nullable|array'
        ]);

        if (!empty($validated['is_default'])) {
            TaskTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = TaskTemplate::create([
            'name' => $validated['name'],
            'is_default' => $validated['is_default'] ?? false,
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? null,
            'custom_fields_values' => $validated['custom_fields_values'] ?? [],
            'priority' => $validated['priority'] ?? 'متوسط',
            'start_date_offset' => $validated['start_date_offset'] ?? null,
            'due_date_offset' => $validated['due_date_offset'] ?? null,
            'attachments' => $validated['attachments'] ?? []
        ]);

        return response()->json($template, 201);
    }

    public function update(Request $request, $id)
    {
        $template = TaskTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:255',
            'custom_fields_values' => 'nullable|array',
            'priority' => 'nullable|string|max:255',
            'start_date_offset' => 'nullable|integer',
            'due_date_offset' => 'nullable|integer',
            'attachments' => 'nullable|array'
        ]);

        if (!empty($validated['is_default'])) {
            TaskTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template->update([
            'name' => $validated['name'],
            'is_default' => $validated['is_default'] ?? false,
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? null,
            'custom_fields_values' => $validated['custom_fields_values'] ?? [],
            'priority' => $validated['priority'] ?? 'متوسط',
            'start_date_offset' => $validated['start_date_offset'] ?? null,
            'due_date_offset' => $validated['due_date_offset'] ?? null,
            'attachments' => $validated['attachments'] ?? []
        ]);

        return response()->json($template);
    }

    public function destroy($id)
    {
        $template = TaskTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'تم حذف قالب المهمة بنجاح']);
    }

    public function setDefault($id)
    {
        TaskTemplate::where('is_default', true)->update(['is_default' => false]);
        $template = TaskTemplate::findOrFail($id);
        $template->update(['is_default' => true]);

        return response()->json($template);
    }
}
