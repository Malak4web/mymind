<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * Extensions accepted on upload. Anything executable by the web server
     * (php, phtml, phar, cgi, ...) must never appear here. See SEC-01.
     */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'pdf', 'txt', 'csv', 'md',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'rar', '7z',
        'mp4', 'mp3', 'wav',
    ];

    /**
     * Extensions safe to render inline in the browser. Everything else is
     * forced to download so uploaded markup can never execute on our origin.
     */
    private const INLINE_EXTENSIONS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'pdf' => 'application/pdf',
    ];

    public function store(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorizedProject($task->project_id, withTrashed: true);
        $this->assertPermission('manage-tasks');

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480', // 20 MB
                'extensions:'.implode(',', self::ALLOWED_EXTENSIONS),
            ],
            'simulate_failure' => 'nullable|boolean',
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

        // Store under a generated name so the original filename can never
        // influence what the web server does with the file on disk.
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

    /**
     * Serve an attachment. Reached only through a signed, expiring URL
     * (see routes/api.php), so plain id enumeration no longer works.
     */
    public function getFile($id)
    {
        $attachment = Attachment::findOrFail($id);

        if (!$attachment->path || !Storage::disk('public')->exists($attachment->path)) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }

        $fullPath = Storage::disk('public')->path($attachment->path);
        $extension = strtolower(pathinfo($attachment->path, PATHINFO_EXTENSION));
        $inlineType = self::INLINE_EXTENSIONS[$extension] ?? null;

        $headers = [
            'Content-Type' => $inlineType ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];

        return response()->file($fullPath, $headers)
            ->setContentDisposition(
                $inlineType ? 'inline' : 'attachment',
                $attachment->name
            );
    }

    public function destroy($id)
    {
        $attachment = Attachment::with('task.project')->findOrFail($id);
        $projectId = $attachment->task?->project_id;

        if ($projectId) {
            $this->authorizedProject($projectId, withTrashed: true);
        }
        $this->assertPermission('manage-tasks');

        if ($attachment->path) {
            Storage::disk('public')->delete($attachment->path);
        }
        $attachment->delete();

        broadcast(new DataChanged(request()->user()->id, 'tasks', $projectId))->toOthers();

        return response()->json(['message' => 'تم حذف المرفق بنجاح']);
    }
}
