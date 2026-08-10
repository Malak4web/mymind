<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function store(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);

        $request->validate([
            'file' => 'required|file',
            'simulate_failure' => 'nullable|boolean'
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $fileSize = round($file->getSize() / 1024, 1) . ' KB'; // convert size to KB string

        if ($request->boolean('simulate_failure')) {
            $attachment = Attachment::create([
                'task_id' => $taskId,
                'name' => $fileName,
                'path' => null,
                'size' => $fileSize,
                'progress' => 50,
                'status' => 'failed'
            ]);

            broadcast(new DataChanged($request->user()->id, 'tasks', $task->project_id))->toOthers();

            return response()->json($attachment, 200);
        }

        $path = $file->store('attachments', 'public');

        $attachment = Attachment::create([
            'task_id' => $taskId,
            'name' => $fileName,
            'path' => $path,
            'size' => $fileSize,
            'progress' => 100,
            'status' => 'done'
        ]);

        broadcast(new DataChanged($request->user()->id, 'tasks', $task->project_id))->toOthers();

        return response()->json($attachment, 201);
    }

    public function getFile($id)
    {
        $attachment = Attachment::findOrFail($id);
        if (!$attachment->path || !Storage::disk('public')->exists($attachment->path)) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }
        $fullPath = Storage::disk('public')->path($attachment->path);
        return response()->file($fullPath);
    }

    public function destroy($id)
    {
        $attachment = Attachment::with('task')->findOrFail($id);
        $projectId = $attachment->task?->project_id;

        if ($attachment->path) {
            Storage::disk('public')->delete($attachment->path);
        }
        $attachment->delete();

        broadcast(new DataChanged(request()->user()->id, 'tasks', $projectId))->toOthers();

        return response()->json(['message' => 'تم حذف المرفق بنجاح']);
    }
}
