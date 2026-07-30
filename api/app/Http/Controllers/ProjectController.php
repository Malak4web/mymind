<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $query = Project::with(['customFields', 'users', 'category'])->where('is_deleted', false);

        if ($user->role && $user->role->name !== 'مدير') {
            $query->whereHas('users', function ($uq) use ($user) {
                $uq->where('users.id', $user->id);
            });
        }

        $projects = $query->get()->map(function ($p) {
            $pData = $p->toArray();
            $pData['member_ids'] = $p->users->pluck('id')->all();
            $pData['category_id'] = $p->category_id;
            $pData['category_name'] = $p->category?->name;
            return $pData;
        });

        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_template_id' => 'nullable|integer',
            'status_source' => 'nullable|string|in:global,custom,empty',
            'custom_statuses' => 'nullable|array',
            'category_id' => 'nullable|exists:project_categories,id'
        ]);

        $statuses = ['بانتظار البدء', 'قيد العمل', 'تحت المراجعة', 'مكتمل'];
        $customFields = [];

        $template = null;
        if (!empty($validated['project_template_id'])) {
            $template = \App\Models\ProjectTemplate::find($validated['project_template_id']);
        } else {
            $template = \App\Models\ProjectTemplate::where('is_default', true)->first();
        }

        if ($template) {
            if (!empty($template->statuses)) {
                $statuses = $template->statuses;
            }
            if (!empty($template->custom_fields)) {
                $customFields = $template->custom_fields;
            }
        } else {
            if (!empty($validated['status_source'])) {
                if ($validated['status_source'] === 'custom' && !empty($validated['custom_statuses'])) {
                    $statuses = $validated['custom_statuses'];
                } elseif ($validated['status_source'] === 'empty') {
                    $statuses = ['بانتظار البدء'];
                }
            }
        }

        return DB::transaction(function () use ($request, $validated, $statuses, $customFields, $template) {
            $project = Project::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'statuses' => $statuses,
                'category_id' => $validated['category_id'] ?? null
            ]);

            foreach ($customFields as $cf) {
                if (!empty($cf['name']) && !empty($cf['type'])) {
                    \App\Models\CustomFieldDefinition::create([
                        'project_id' => $project->id,
                        'name' => $cf['name'],
                        'type' => $cf['type'],
                        'active' => true
                    ]);
                }
            }

            // Auto-spawn tasks using task templates linked to project template
            if ($template && !empty($template->task_template_ids)) {
                foreach ($template->task_template_ids as $taskTemplateId) {
                    $taskTemplate = \App\Models\TaskTemplate::find($taskTemplateId);
                    if ($taskTemplate) {
                        $title = $taskTemplate->name;
                        $description = null;
                        $status = $statuses[0];
                        $priority = 'متوسط';
                        $startDate = null;
                        $deadline = null;
                        $attachments = [];
                        $customFieldsValues = [];

                        // Parse the dynamic fields array from custom_fields_values JSON
                        if (!empty($taskTemplate->custom_fields_values) && is_array($taskTemplate->custom_fields_values)) {
                            foreach ($taskTemplate->custom_fields_values as $f) {
                                if (empty($f['type'])) continue;
                                $fName = $f['name'] ?? '';
                                $fVal = $f['value'] ?? '';

                                if ($f['type'] === 'title') {
                                    $title = $fVal;
                                } elseif ($f['type'] === 'description' || $f['type'] === 'textarea') {
                                    $description = $fVal;
                                } elseif ($f['type'] === 'status') {
                                    $status = $fVal;
                                } elseif ($f['type'] === 'priority') {
                                    $priority = $fVal;
                                } elseif ($f['type'] === 'date_start_offset') {
                                    $startDate = now()->addDays(intval($fVal))->toDateString();
                                } elseif ($f['type'] === 'date_due_offset') {
                                    $deadline = now()->addDays(intval($fVal))->toDateString();
                                } elseif ($f['type'] === 'attachment') {
                                    $attachments[] = $fVal;
                                } elseif (in_array($f['type'], ['text', 'number', 'link'])) {
                                    $customFieldsValues[$fName] = $fVal;
                                }
                            }
                        }

                        $task = \App\Models\Task::create([
                            'project_id' => $project->id,
                            'title' => $title,
                            'description' => $description,
                            'status' => $status,
                            'start_date' => $startDate,
                            'deadline' => $deadline,
                            'priority' => $priority
                        ]);

                        foreach ($attachments as $attName) {
                            if (!empty($attName)) {
                                \App\Models\Attachment::create([
                                    'task_id' => $task->id,
                                    'name' => $attName,
                                    'size' => '0 KB',
                                    'path' => 'attachments/placeholder.png'
                                ]);
                            }
                        }

                        foreach ($customFieldsValues as $fieldName => $value) {
                            $cfDef = \App\Models\CustomFieldDefinition::where('project_id', $project->id)
                                ->where('name', $fieldName)
                                ->first();
                            if ($cfDef) {
                                \App\Models\CustomFieldValue::create([
                                    'task_id' => $task->id,
                                    'custom_field_definition_id' => $cfDef->id,
                                    'value' => (string)$value
                                ]);
                            }
                        }
                    }
                }
            }

            if ($request->has('member_ids') && is_array($request->member_ids)) {
                $project->users()->sync($request->member_ids);
            }

            $pData = $project->load('users')->toArray();
            $pData['member_ids'] = $project->users->pluck('id')->all();

            return response()->json($pData, 201);
        });
    }

    public function show($id)
    {
        $project = Project::with(['customFields', 'users'])->where('is_deleted', false)->findOrFail($id);
        $pData = $project->toArray();
        $pData['member_ids'] = $project->users->pluck('id')->all();
        return response()->json($pData);
    }

    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:project_categories,id'
        ]);

        $project->update($validated);

        if ($request->has('member_ids') && is_array($request->member_ids)) {
            $project->users()->sync($request->member_ids);
        }

        $pData = $project->load('users')->toArray();
        $pData['member_ids'] = $project->users->pluck('id')->all();

        return response()->json($pData);
    }

    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->update(['is_deleted' => true]);
        $project->delete(); // SoftDeletes call

        return response()->json(['message' => 'تم نقل المشروع لسلة المهملات']);
    }

    public function restore($id)
    {
        $project = Project::onlyTrashed()->findOrFail($id);
        $project->restore();
        $project->update(['is_deleted' => false]);

        return response()->json($project);
    }

    public function addStatus(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|string'
        ]);

        $statuses = $project->statuses;
        if (!in_array($validated['status'], $statuses)) {
            $statuses[] = $validated['status'];
            $project->update(['statuses' => $statuses]);
        }

        return response()->json($project);
    }

    public function deleteStatus(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|string',
            'fallback_status' => 'nullable|string'
        ]);

        $statuses = $project->statuses;
        if (count($statuses) <= 1) {
            return response()->json(['error' => 'يجب أن يحتوي المشروع على حالة واحدة على الأقل.'], 400);
        }

        $tasksCount = Task::where('project_id', $id)->where('status', $validated['status'])->count();

        if ($tasksCount > 0) {
            if (empty($validated['fallback_status'])) {
                return response()->json(['error' => 'لا يمكن حذف الحالة لوجود مهام بها. يرجى تحديد حالة بديلة.'], 400);
            }
            // Migrate tasks
            Task::where('project_id', $id)
                ->where('status', $validated['status'])
                ->update(['status' => $validated['fallback_status']]);
        }

        $newStatuses = array_values(array_filter($statuses, fn($s) => $s !== $validated['status']));
        $project->update(['statuses' => $newStatuses]);

        return response()->json($project);
    }
}
