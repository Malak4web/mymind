<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorizedProject($task->project_id, withTrashed: true);

        return response()->json(
            Comment::where('task_id', $task->id)->orderBy('created_at')->get()
        );
    }

    public function store(Request $request, $taskId)
    {
        $task = Task::findOrFail($taskId);
        $this->authorizedProject($task->project_id, withTrashed: true);
        $this->assertPermission('manage-tasks');

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $user = $this->currentUser($request);

        $comment = Comment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'author_name' => $user->name,
            'body' => $validated['body'],
        ]);

        broadcast(new DataChanged($user->id, 'tasks', $task->project_id))->toOthers();

        return response()->json($comment, 201);
    }

    public function destroy(Request $request, $id)
    {
        $comment = Comment::with('task')->findOrFail($id);
        $this->authorizedProject($comment->task->project_id, withTrashed: true);

        $user = $this->currentUser($request);

        // A comment can be removed by its author or by an admin.
        if ((int) $comment->user_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403, 'يمكنك حذف تعليقاتك فقط.');
        }

        $projectId = $comment->task->project_id;
        $comment->delete();

        broadcast(new DataChanged($user->id, 'tasks', $projectId))->toOthers();

        return response()->json(['message' => 'تم حذف التعليق بنجاح']);
    }
}
