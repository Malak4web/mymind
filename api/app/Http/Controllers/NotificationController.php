<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return response()->json(Notification::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'text' => 'required|string'
        ]);

        $notification = Notification::create([
            'title' => $validated['title'],
            'text' => $validated['text'],
            'is_read' => false
        ]);

        return response()->json($notification, 201);
    }

    public function markRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json($notification);
    }

    public function markAllRead()
    {
        Notification::where('is_read', false)->update(['is_read' => true]);

        return response()->json(['message' => 'تم تحديد جميع الإشعارات كمقروءة']);
    }

    public function createTestingHelper()
    {
        $notification = Notification::create([
            'title' => 'تنبيه اختبار',
            'text' => 'محتوى تنبيه اختبار TDD',
            'is_read' => false
        ]);

        return response()->json($notification, 201);
    }
}
