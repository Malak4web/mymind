<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\Challenge;
use App\Models\ChallengeCheer;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChallengeController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->currentUser($request);

        $challenges = Challenge::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('partner_id', $user->id);
        })
        ->with([
            'user:id,name,email',
            'partner:id,name,email',
            'cheers' => function ($q) {
                $q->with('user:id,name,email')->orderBy('created_at', 'asc');
            }
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($challenges);
    }

    public function store(Request $request)
    {
        $user = $this->currentUser($request);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'required|integer|min:1',
            'reward_title' => 'nullable|string|max:255',
            'reward_icon' => 'nullable|string',
            'reward_description' => 'nullable|string',
            'conditions' => 'nullable|array',
            'partner_id' => 'nullable|exists:users,id',
            'days_progress' => 'nullable|array',
            'status' => 'nullable|string|in:active,completed,abandoned',
        ]);

        $partnerId = $validated['partner_id'] ?? null;
        if ($partnerId && (int)$partnerId === (int)$user->id) {
            $partnerId = null;
        }

        $challenge = Challenge::create([
            'user_id' => $user->id,
            'partner_id' => $partnerId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? 'عام',
            'icon' => $validated['icon'] ?? '🎯',
            'color' => $validated['color'] ?? 'from-violet-600 to-indigo-600',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $validated['total_days'],
            'reward_title' => $validated['reward_title'] ?? null,
            'reward_icon' => $validated['reward_icon'] ?? '🏆',
            'reward_description' => $validated['reward_description'] ?? null,
            'conditions' => $validated['conditions'] ?? [],
            'days_progress' => $validated['days_progress'] ?? (object)[],
            'status' => $validated['status'] ?? 'active',
        ]);

        if ($partnerId) {
            try {
                Notification::create([
                    'user_id' => $partnerId,
                    'title' => 'شراكة تحدي جديد 🎯',
                    'text' => "أضافك {$user->name} كشريك في تحدي \"{$challenge->title}\"!",
                    'is_read' => false,
                ]);
                broadcast(new DataChanged($partnerId, 'notifications'))->toOthers();
                broadcast(new DataChanged($partnerId, 'challenges'))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Partner notify/broadcast failed: ' . $e->getMessage());
            }
        }

        try {
            broadcast(new DataChanged($user->id, 'challenges'))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcasting failed in ChallengeController store: ' . $e->getMessage());
        }

        $challenge->load([
            'user:id,name,email',
            'partner:id,name,email',
            'cheers.user:id,name,email'
        ]);

        return response()->json($challenge, 201);
    }

    public function show(Request $request, $id)
    {
        $user = $this->currentUser($request);

        $challenge = Challenge::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('partner_id', $user->id);
            })
            ->with([
                'user:id,name,email',
                'partner:id,name,email',
                'cheers.user:id,name,email'
            ])
            ->firstOrFail();

        return response()->json($challenge);
    }

    public function update(Request $request, $id)
    {
        $user = $this->currentUser($request);

        $challenge = Challenge::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('partner_id', $user->id);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'icon' => 'nullable|string',
            'color' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'total_days' => 'nullable|integer|min:1',
            'reward_title' => 'nullable|string|max:255',
            'reward_icon' => 'nullable|string',
            'reward_description' => 'nullable|string',
            'conditions' => 'nullable|array',
            'partner_id' => 'nullable|exists:users,id',
            'days_progress' => 'nullable|array',
            'status' => 'nullable|string|in:active,completed,abandoned',
        ]);

        if (isset($validated['partner_id']) && (int)$validated['partner_id'] === (int)$user->id) {
            $validated['partner_id'] = null;
        }

        // If partner is updating and is not owner, only allow updating days_progress or status
        if ((int)$challenge->user_id !== (int)$user->id) {
            $allowedKeys = ['days_progress', 'status'];
            $validated = array_intersect_key($validated, array_flip($allowedKeys));
        }

        // Check if all days are completed in days_progress
        if (isset($validated['days_progress'])) {
            $totalDays = $validated['total_days'] ?? $challenge->total_days;
            $completedDays = 0;
            foreach ($validated['days_progress'] as $dayData) {
                if (!empty($dayData['completed'])) {
                    $completedDays++;
                }
            }

            if ($completedDays >= $totalDays && $totalDays > 0) {
                $validated['status'] = 'completed';
            }
        }

        $challenge->update(array_filter($validated, fn($val) => $val !== null));

        // Broadcast to both creator and partner
        $notifyUsers = array_filter(array_unique([$challenge->user_id, $challenge->partner_id]));
        foreach ($notifyUsers as $targetUserId) {
            try {
                broadcast(new DataChanged($targetUserId, 'challenges'))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Broadcast failed in ChallengeController update: ' . $e->getMessage());
            }
        }

        $challenge->load([
            'user:id,name,email',
            'partner:id,name,email',
            'cheers.user:id,name,email'
        ]);

        return response()->json($challenge);
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->currentUser($request);

        $challenge = Challenge::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $partnerId = $challenge->partner_id;
        $challenge->delete();

        try {
            broadcast(new DataChanged($user->id, 'challenges'))->toOthers();
            if ($partnerId) {
                broadcast(new DataChanged($partnerId, 'challenges'))->toOthers();
            }
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed in ChallengeController destroy: ' . $e->getMessage());
        }

        return response()->json(['message' => 'تم حذف التحدي بنجاح']);
    }

    public function addCheer(Request $request, $id)
    {
        $user = $this->currentUser($request);

        $challenge = Challenge::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('partner_id', $user->id);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
            'reaction' => 'nullable|string|max:50',
        ]);

        if (empty($validated['message']) && empty($validated['reaction'])) {
            return response()->json(['error' => 'يجب إدخال رسالة أو تفاعل على الأقل'], 422);
        }

        $cheer = ChallengeCheer::create([
            'challenge_id' => $challenge->id,
            'user_id' => $user->id,
            'message' => $validated['message'] ?? null,
            'reaction' => $validated['reaction'] ?? null,
        ]);

        $cheer->load('user:id,name,email');

        // Determine recipient to notify
        $targetUserId = ((int)$user->id === (int)$challenge->user_id) 
            ? $challenge->partner_id 
            : $challenge->user_id;

        if ($targetUserId) {
            try {
                $cheerText = trim(($validated['reaction'] ?? '') . ' ' . ($validated['message'] ?? ''));
                Notification::create([
                    'user_id' => $targetUserId,
                    'title' => 'رسالة تشجيعية جديدة 🔥',
                    'text' => "{$user->name} أرسل تشجيعاً في تحدي \"{$challenge->title}\": {$cheerText}",
                    'is_read' => false,
                ]);
                broadcast(new DataChanged($targetUserId, 'notifications'))->toOthers();
                broadcast(new DataChanged($targetUserId, 'challenges'))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Cheer notify failed: ' . $e->getMessage());
            }
        }

        try {
            broadcast(new DataChanged($user->id, 'challenges'))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcasting failed in addCheer: ' . $e->getMessage());
        }

        return response()->json($cheer, 201);
    }
}
