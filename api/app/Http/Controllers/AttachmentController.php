<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function store(Request $request, $taskId)
    {
        Task::findOrFail($taskId);

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

        return response()->json($attachment, 201);
    }

    public function destroy($id)
    {
        $attachment = Attachment::findOrFail($id);
        if ($attachment->path) {
            Storage::disk('public')->delete($attachment->path);
        }
        $attachment->delete();

        return response()->json(['message' => 'تم حذف المرفق بنجاح']);
    }
}
