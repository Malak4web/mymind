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
        $files = ProjectFile::where('project_id', $projectId)->get();
        return response()->json($files);
    }

    public function store(Request $request, $projectId)
    {
        $request->validate([
            'file' => 'required|file|max:20480', // max 20MB
            'folder_id' => 'nullable|exists:folders,id'
        ]);

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
        $relativePath = str_replace('/storage/', '', $file->path);
        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->response($relativePath, $file->name);
        }
        return response()->json(['error' => 'File not found'], 404);
    }
}
