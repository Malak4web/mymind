<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectFileController extends Controller
{
    public function index($projectId)
    {
        $this->authorizedProject($projectId);

        $files = ProjectFile::where('project_id', $projectId)->get();

        return response()->json($files);
    }

    public function store(Request $request, $projectId)
    {
        $project = $this->authorizedProject($projectId);
        $this->assertPermission('manage-projects');

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480', // 20 MB
                'extensions:'.implode(',', AttachmentController::ALLOWED_EXTENSIONS),
            ],
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        // A folder from another project must not be usable as a target.
        if ($request->filled('folder_id')) {
            $folder = \App\Models\Folder::find($request->folder_id);
            if (! $folder || (int) $folder->project_id !== (int) $project->id) {
                return response()->json(['error' => 'المجلد لا ينتمي إلى هذا المشروع'], 422);
            }
        }

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();

        // Save to public storage
        $path = $uploadedFile->store('project_files', 'public');

        // Format size
        $bytes = $uploadedFile->getSize();
        if ($bytes >= 1048576) {
            $size = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $size = number_format($bytes / 1024, 2) . ' KB';
        } else {
            $size = $bytes . ' B';
        }

        $type = $uploadedFile->getClientOriginalExtension() ?: 'unknown';

        $file = ProjectFile::create([
            'project_id' => $projectId,
            'folder_id' => $request->folder_id,
            'name' => $originalName,
            'path' => Storage::url($path),
            'size' => $size,
            'type' => $type
        ]);

        broadcast(new DataChanged($request->user()->id, 'project_files', $file->project_id))->toOthers();

        return response()->json($file, 201);
    }

    public function destroy($id)
    {
        $file = ProjectFile::findOrFail($id);
        $this->authorizedProject($file->project_id);
        $this->assertPermission('manage-projects');

        $projectId = $file->project_id;

        // Extract relative storage path and delete physical file
        $relativePath = str_replace('/storage/', '', $file->path);
        Storage::disk('public')->delete($relativePath);

        $file->delete();

        broadcast(new DataChanged(request()->user()->id, 'project_files', $projectId))->toOthers();

        return response()->json(['message' => 'تم حذف الملف بنجاح']);
    }

    public function download($id)
    {
        $file = ProjectFile::findOrFail($id);
        $this->authorizedProject($file->project_id);

        $relativePath = str_replace('/storage/', '', $file->path);

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->download($relativePath, $file->name, [
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->json(['error' => 'File not found'], 404);
    }
}
