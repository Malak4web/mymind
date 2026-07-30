<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Project;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function indexProjectMessages($projectId)
    {
        Project::findOrFail($projectId);
        
        $messages = Message::where('project_id', $projectId)
                           ->whereNull('task_id')
                           ->orderBy('created_at', 'asc')
                           ->get();

        return response()->json($messages);
    }

    public function indexTaskMessages($taskId)
    {
        $messages = Message::where('task_id', $taskId)
                           ->orderBy('created_at', 'asc')
                           ->get();

        return response()->json($messages);
    }

    public function store(Request $request, $projectId)
    {
        Project::findOrFail($projectId);

        $validated = $request->validate([
            'sender' => 'required|string|max:255',
            'text' => 'required|string',
            'task_id' => 'nullable|exists:tasks,id',
            'reply_to_id' => 'nullable|exists:messages,id'
        ]);

        $message = Message::create([
            'project_id' => $projectId,
            'task_id' => $validated['task_id'] ?? null,
            'sender' => $validated['sender'],
            'text' => $validated['text'],
            'reply_to_id' => $validated['reply_to_id'] ?? null,
            'is_deleted' => false
        ]);

        return response()->json($message, 201);
    }

    public function destroy($id)
    {
        $message = Message::findOrFail($id);
        $message->update([
            'is_deleted' => true,
            'text' => 'تم حذف هذه الرسالة'
        ]);

        return response()->json($message);
    }
}
