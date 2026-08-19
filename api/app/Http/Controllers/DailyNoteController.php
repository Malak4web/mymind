<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\DailyNote;
use Illuminate\Http\Request;

class DailyNoteController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->currentUser($request);

        $query = DailyNote::where('user_id', $user->id);

        // Optional window so the journal does not fetch a lifetime of notes.
        if ($request->filled('from')) {
            $query->whereDate('note_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('note_date', '<=', $request->query('to'));
        }

        return response()->json(
            $query->orderBy('note_date', 'desc')->orderBy('created_at', 'desc')->get()
        );
    }

    public function store(Request $request)
    {
        $user = $this->currentUser($request);

        $validated = $request->validate([
            'note_date' => 'required|date',
            'content' => 'required|string|max:5000',
        ]);

        $note = DailyNote::create([
            'user_id' => $user->id,
            'note_date' => $validated['note_date'],
            'content' => $validated['content'],
        ]);

        broadcast(new DataChanged($user->id, 'daily_notes'))->toOthers();

        return response()->json($note, 201);
    }

    public function destroy(Request $request, $id)
    {
        $note = $this->currentUser($request)->dailyNotes()->findOrFail($id);
        $note->delete();

        broadcast(new DataChanged($request->user()->id, 'daily_notes'))->toOthers();

        return response()->json(['message' => 'تم حذف الملاحظة بنجاح']);
    }
}
