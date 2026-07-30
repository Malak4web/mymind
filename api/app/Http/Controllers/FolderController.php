<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use Illuminate\Http\Request;

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

        $folder = Folder::create([
            'project_id' => $projectId,
            'parent_id' => $request->parent_id,
            'name' => $request->name
        ]);

        return response()->json($folder, 201);
    }

    public function destroy($id)
    {
        $folder = Folder::findOrFail($id);
        $folder->delete();
        return response()->json(['message' => 'تم حذف المجلد بنجاح']);
    }
}
