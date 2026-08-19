<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->currentUser($request);

        return response()->json(
            Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $user = $this->currentUser($request);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'text' => 'required|string'
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'text' => $validated['text'],
            'is_read' => false
        ]);

        broadcast(new DataChanged($user->id, 'notifications'))->toOthers();

        return response()->json($notification, 201);
    }

    public function markRead(Request $request, $id)
    {
        $user = $this->currentUser($request);

        $notification = Notification::where('user_id', $user->id)->findOrFail($id);
        $notification->update(['is_read' => true]);

        broadcast(new DataChanged($user->id, 'notifications'))->toOthers();

        return response()->json($notification);
    }

    public function markAllRead(Request $request)
    {
        $user = $this->currentUser($request);

        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        broadcast(new DataChanged($user->id, 'notifications'))->toOthers();

        return response()->json(['message' => 'تم تحديد جميع الإشعارات كمقروءة']);
    }

    public function createTestingHelper(Request $request)
    {
        $user = $this->currentUser($request);

        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'تنبيه اختبار',
            'text' => 'محتوى تنبيه اختبار TDD',
            'is_read' => false
        ]);

        broadcast(new DataChanged($user->id, 'notifications'))->toOthers();

        return response()->json($notification, 201);
    }
}
