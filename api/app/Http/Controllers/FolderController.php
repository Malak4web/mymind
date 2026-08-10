<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FolderController extends Controller
{
    public function index($projectId)
    {
        $folders = Folder::where('project_id', $projectId)->get();
        return response()->json($folders);
    }

    public function store(Request $request, $projectId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id'
        ]);

        if (!empty($request->parent_id)) {
            $parentFolder = Folder::find($request->parent_id);
            if (!$parentFolder || (string)$parentFolder->project_id !== (string)$projectId) {
                return response()->json(['error' => 'المجلد الأب لا ينتمي إلى هذا المشروع'], 422);
            }
        }

        $folder = Folder::create([
            'project_id' => $projectId,
            'parent_id' => $request->parent_id,
            'name' => $request->name
        ]);

        broadcast(new DataChanged($request->user()->id, 'folders', $folder->project_id));

        return response()->json($folder, 201);
    }

    public function destroy($id)
    {
        $folder = Folder::findOrFail($id);
        $projectId = $folder->project_id;

        DB::transaction(function () use ($folder) {
            $this->deleteFolderRecursive($folder);
        });

        broadcast(new DataChanged(request()->user()->id, 'folders', $projectId));

        return response()->json(['message' => 'تم حذف المجلد بنجاح']);
    }

    private function deleteFolderRecursive(Folder $folder)
    {
        foreach ($folder->files as $file) {
            $relativePath = str_replace('/storage/', '', $file->path);
            Storage::disk('public')->delete($relativePath);
            $file->delete();
        }

        foreach ($folder->children as $child) {
            $this->deleteFolderRecursive($child);
        }

        foreach ($folder->notes as $note) {
            $note->delete();
        }

        $folder->delete();
    }
}
