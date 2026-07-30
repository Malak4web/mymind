<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index($projectId)
    {
        $notes = Note::where('project_id', $projectId)->get();
        return response()->json($notes);
    }

    public function store(Request $request, $projectId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'folder_id' => 'nullable|exists:folders,id'
        ]);

        $note = Note::create([
            'project_id' => $projectId,
            'folder_id' => $request->folder_id,
            'title' => $request->title,
            'content' => $request->content
        ]);

        return response()->json($note, 201);
    }

    public function update(Request $request, $id)
    {
        $note = Note::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'folder_id' => 'nullable|exists:folders,id'
        ]);

        $note->update([
            'folder_id' => $request->folder_id,
            'title' => $request->title,
            'content' => $request->content
        ]);

        return response()->json($note);
    }

    public function destroy($id)
    {
        $note = Note::findOrFail($id);
        $note->delete();
        return response()->json(['message' => 'تم حذف الملاحظة بنجاح']);
    }
}
