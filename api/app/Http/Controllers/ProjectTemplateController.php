<?php

namespace App\Http\Controllers;

use App\Models\ProjectTemplate;
use Illuminate\Http\Request;

class ProjectTemplateController extends Controller
{
    public function index()
    {
        return response()->json(ProjectTemplate::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
            'statuses' => 'nullable|array',
            'task_template_ids' => 'nullable|array'
        ]);

        if (!empty($validated['is_default'])) {
            ProjectTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = ProjectTemplate::create([
            'name' => $validated['name'],
            'is_default' => $validated['is_default'] ?? false,
            'statuses' => $validated['statuses'] ?? ['بانتظار البدء', 'قيد العمل', 'تحت المراجعة', 'مكتمل'],
            'task_template_ids' => $validated['task_template_ids'] ?? []
        ]);

        return response()->json($template, 201);
    }

    public function update(Request $request, $id)
    {
        $template = ProjectTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
            'statuses' => 'nullable|array',
            'task_template_ids' => 'nullable|array'
        ]);

        if (!empty($validated['is_default'])) {
            ProjectTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template->update([
            'name' => $validated['name'],
            'is_default' => $validated['is_default'] ?? false,
            'statuses' => $validated['statuses'] ?? $template->statuses,
            'task_template_ids' => $validated['task_template_ids'] ?? []
        ]);

        return response()->json($template);
    }

    public function destroy($id)
    {
        $template = ProjectTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'تم حذف قالب المشروع بنجاح']);
    }

    public function setDefault($id)
    {
        ProjectTemplate::where('is_default', true)->update(['is_default' => false]);
        $template = ProjectTemplate::findOrFail($id);
        $template->update(['is_default' => true]);

        return response()->json($template);
    }
}
