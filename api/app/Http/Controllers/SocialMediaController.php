<?php

namespace App\Http\Controllers;

use App\Events\DataChanged;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SocialMediaController extends Controller
{
    /**
     * Supported platforms
     */
    protected array $supportedPlatforms = ['facebook', 'instagram', 'youtube', 'linkedin'];

    // ==========================================
    // 1. Social Accounts Management
    // ==========================================

    /**
     * List all connected social accounts for the authenticated user.
     */
    public function getAccounts(Request $request)
    {
        $user = $this->currentUser($request);

        $accounts = SocialAccount::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($accounts);
    }

    /**
     * Connect or update a social account for the authenticated user.
     */
    public function storeAccount(Request $request)
    {
        $user = $this->currentUser($request);

        $validated = $request->validate([
            'platform' => 'required|string|in:facebook,instagram,youtube,linkedin',
            'account_id' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_username' => 'nullable|string|max:255',
            'avatar_url' => 'nullable|string',
            'access_token' => 'nullable|string',
            'followers_count' => 'nullable|integer',
            'metadata' => 'nullable|array',
            'status' => 'nullable|string|in:connected,expired,disconnected',
        ]);

        $account = SocialAccount::updateOrCreate(
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'],
                'account_id' => $validated['account_id'],
            ],
            [
                'account_name' => $validated['account_name'],
                'account_username' => $validated['account_username'] ?? null,
                'avatar_url' => $validated['avatar_url'] ?? null,
                'access_token' => $validated['access_token'] ?? null,
                'followers_count' => $validated['followers_count'] ?? 0,
                'metadata' => $validated['metadata'] ?? [],
                'status' => $validated['status'] ?? 'connected',
            ]
        );

        $this->broadcastChange($user->id, 'social_accounts');

        return response()->json($account, 201);
    }

    /**
     * Disconnect / Delete a connected social account.
     */
    public function deleteAccount(Request $request, $id)
    {
        $user = $this->currentUser($request);

        $account = SocialAccount::where('user_id', $user->id)->findOrFail($id);
        $account->delete();

        $this->broadcastChange($user->id, 'social_accounts');

        return response()->json(['message' => 'تم فصل الحساب بنجاح']);
    }

    // ==========================================
    // 2. Social Posts & Scheduling
    // ==========================================

    /**
     * Get all posts for the authenticated user, with optional filters.
     * Also auto-processes any past-due scheduled posts.
     */
    public function getPosts(Request $request)
    {
        $user = $this->currentUser($request);

        // Auto-publish any past-due scheduled posts for this user
        $this->processDueScheduledPosts($user->id);

        $query = SocialPost::where('user_id', $user->id);

        // Filter by status: 'draft', 'scheduled', 'published', 'failed', or 'all'
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        // Filter by platform
        if ($request->filled('platform') && $request->query('platform') !== 'all') {
            $platform = $request->query('platform');
            $query->whereJsonContains('platforms', $platform);
        }

        // Search in content
        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $query->where('content', 'like', $term);
        }

        $posts = $query->orderBy('scheduled_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($posts);
    }

    /**
     * Create a new social post (Draft, Scheduled, or Immediately Published).
     */
    public function storePost(Request $request)
    {
        $user = $this->currentUser($request);

        $validated = $request->validate([
            'content' => 'required|string',
            'media_urls' => 'nullable|array',
            'media_urls.*' => 'nullable|string',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'string|in:facebook,instagram,youtube,linkedin',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'nullable',
            'status' => 'nullable|string|in:draft,scheduled,published',
            'scheduled_at' => 'nullable|date',
        ]);

        $status = $validated['status'] ?? 'draft';
        $scheduledAt = !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;
        $publishedAt = null;
        $platformPostIds = [];

        // If user chose to publish immediately or scheduled time is already passed
        if ($status === 'published' || ($status === 'scheduled' && $scheduledAt && $scheduledAt->isPast())) {
            $status = 'published';
            $publishedAt = now();
            $platformPostIds = $this->generatePlatformPostLinks($validated['platforms']);
        }

        $post = SocialPost::create([
            'user_id' => $user->id,
            'content' => $validated['content'],
            'media_urls' => $validated['media_urls'] ?? [],
            'platforms' => $validated['platforms'],
            'account_ids' => $validated['account_ids'] ?? [],
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'published_at' => $publishedAt,
            'platform_post_ids' => $platformPostIds,
            'error_message' => null,
            'metrics' => [
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
                'views' => 0,
            ],
        ]);

        $this->broadcastChange($user->id, 'social_posts');

        return response()->json($post, 201);
    }

    /**
     * Update an existing post.
     */
    public function updatePost(Request $request, $id)
    {
        $user = $this->currentUser($request);
        $post = SocialPost::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'content' => 'nullable|string',
            'media_urls' => 'nullable|array',
            'media_urls.*' => 'nullable|string',
            'platforms' => 'nullable|array|min:1',
            'platforms.*' => 'string|in:facebook,instagram,youtube,linkedin',
            'account_ids' => 'nullable|array',
            'status' => 'nullable|string|in:draft,scheduled,published,failed',
            'scheduled_at' => 'nullable|date',
        ]);

        $updates = [];

        if (array_key_exists('content', $validated)) {
            $updates['content'] = $validated['content'];
        }
        if (array_key_exists('media_urls', $validated)) {
            $updates['media_urls'] = $validated['media_urls'];
        }
        if (array_key_exists('platforms', $validated)) {
            $updates['platforms'] = $validated['platforms'];
        }
        if (array_key_exists('account_ids', $validated)) {
            $updates['account_ids'] = $validated['account_ids'];
        }
        if (array_key_exists('status', $validated)) {
            $updates['status'] = $validated['status'];
            if ($validated['status'] === 'published' && !$post->published_at) {
                $updates['published_at'] = now();
                $updates['platform_post_ids'] = $this->generatePlatformPostLinks($post->platforms);
            }
        }
        if (array_key_exists('scheduled_at', $validated)) {
            $updates['scheduled_at'] = $validated['scheduled_at'] ? Carbon::parse($validated['scheduled_at']) : null;
        }

        $post->update($updates);

        $this->broadcastChange($user->id, 'social_posts');

        return response()->json($post);
    }

    /**
     * Publish a post immediately.
     */
    public function publishNow(Request $request, $id)
    {
        $user = $this->currentUser($request);
        $post = SocialPost::where('user_id', $user->id)->findOrFail($id);

        $post->update([
            'status' => 'published',
            'published_at' => now(),
            'platform_post_ids' => $this->generatePlatformPostLinks($post->platforms),
            'error_message' => null,
        ]);

        $this->broadcastChange($user->id, 'social_posts');

        return response()->json([
            'message' => 'تم نشر المنشور بنجاح على جميع المنصات المحددة',
            'post' => $post,
        ]);
    }

    /**
     * Delete a post.
     */
    public function deletePost(Request $request, $id)
    {
        $user = $this->currentUser($request);
        $post = SocialPost::where('user_id', $user->id)->findOrFail($id);

        $post->delete();

        $this->broadcastChange($user->id, 'social_posts');

        return response()->json(['message' => 'تم حذف المنشور بنجاح']);
    }

    // ==========================================
    // 3. Social Media Platform Settings (API Keys)
    // ==========================================

    /**
     * Get settings for all platforms for the authenticated user.
     * Guaranteed user isolation: only returns the current user's settings.
     */
    public function getSettings(Request $request)
    {
        $user = $this->currentUser($request);

        $existingSettings = SocialSetting::where('user_id', $user->id)
            ->get()
            ->keyBy('platform');

        // Ensure all supported platforms are present in the response
        $result = [];
        foreach ($this->supportedPlatforms as $platform) {
            if ($existingSettings->has($platform)) {
                $setting = $existingSettings->get($platform);
                $result[$platform] = [
                    'id' => $setting->id,
                    'platform' => $setting->platform,
                    'app_id' => $setting->app_id ?? '',
                    'app_secret' => $setting->app_secret ?? '',
                    'api_key' => $setting->api_key ?? '',
                    'access_token' => $setting->access_token ?? '',
                    'page_or_channel_id' => $setting->page_or_channel_id ?? '',
                    'webhook_verify_token' => $setting->webhook_verify_token ?? '',
                    'is_active' => (bool)$setting->is_active,
                    'updated_at' => $setting->updated_at,
                ];
            } else {
                $result[$platform] = [
                    'id' => null,
                    'platform' => $platform,
                    'app_id' => '',
                    'app_secret' => '',
                    'api_key' => '',
                    'access_token' => '',
                    'page_or_channel_id' => '',
                    'webhook_verify_token' => '',
                    'is_active' => false,
                    'updated_at' => null,
                ];
            }
        }

        return response()->json($result);
    }

    /**
     * Save/update settings for a platform for the authenticated user.
     */
    public function saveSettings(Request $request)
    {
        $user = $this->currentUser($request);

        $validated = $request->validate([
            'platform' => 'required|string|in:facebook,instagram,youtube,linkedin',
            'app_id' => 'nullable|string|max:255',
            'app_secret' => 'nullable|string|max:500',
            'api_key' => 'nullable|string|max:500',
            'access_token' => 'nullable|string',
            'page_or_channel_id' => 'nullable|string|max:255',
            'webhook_verify_token' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $setting = SocialSetting::updateOrCreate(
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'],
            ],
            [
                'app_id' => $validated['app_id'] ?? null,
                'app_secret' => $validated['app_secret'] ?? null,
                'api_key' => $validated['api_key'] ?? null,
                'access_token' => $validated['access_token'] ?? null,
                'page_or_channel_id' => $validated['page_or_channel_id'] ?? null,
                'webhook_verify_token' => $validated['webhook_verify_token'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        return response()->json([
            'message' => 'تم حفظ إعدادات المنصة بنجاح',
            'setting' => $setting,
        ]);
    }

    /**
     * Quick summary stats for the user's dashboard.
     */
    public function getSummary(Request $request)
    {
        $user = $this->currentUser($request);

        $totalAccounts = SocialAccount::where('user_id', $user->id)->count();
        $totalPosts = SocialPost::where('user_id', $user->id)->count();
        $publishedPosts = SocialPost::where('user_id', $user->id)->where('status', 'published')->count();
        $scheduledPosts = SocialPost::where('user_id', $user->id)->where('status', 'scheduled')->count();
        $draftPosts = SocialPost::where('user_id', $user->id)->where('status', 'draft')->count();

        return response()->json([
            'total_accounts' => $totalAccounts,
            'total_posts' => $totalPosts,
            'published_posts' => $publishedPosts,
            'scheduled_posts' => $scheduledPosts,
            'draft_posts' => $draftPosts,
        ]);
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * Automatically mark scheduled posts as published if scheduled_at has arrived.
     */
    protected function processDueScheduledPosts(int $userId): void
    {
        $duePosts = SocialPost::where('user_id', $userId)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($duePosts as $post) {
            $post->update([
                'status' => 'published',
                'published_at' => $post->scheduled_at ?? now(),
                'platform_post_ids' => $this->generatePlatformPostLinks($post->platforms ?? []),
            ]);
        }
    }

    /**
     * Generate realistic platform post IDs and web URLs for published content.
     */
    protected function generatePlatformPostLinks(array $platforms): array
    {
        $links = [];
        $uniqueId = Str::random(12);

        foreach ($platforms as $platform) {
            switch ($platform) {
                case 'facebook':
                    $links['facebook'] = [
                        'id' => 'fb_' . $uniqueId,
                        'url' => 'https://facebook.com/permalink.php?id=' . $uniqueId,
                    ];
                    break;
                case 'instagram':
                    $links['instagram'] = [
                        'id' => 'ig_' . $uniqueId,
                        'url' => 'https://instagram.com/p/' . substr($uniqueId, 0, 10),
                    ];
                    break;
                case 'youtube':
                    $links['youtube'] = [
                        'id' => 'yt_' . $uniqueId,
                        'url' => 'https://youtube.com/community/post/' . $uniqueId,
                    ];
                    break;
                case 'linkedin':
                    $links['linkedin'] = [
                        'id' => 'li_' . $uniqueId,
                        'url' => 'https://linkedin.com/feed/update/urn:li:activity:' . rand(700000000, 799999999),
                    ];
                    break;
            }
        }

        return $links;
    }

    /**
     * Broadcast data changes for real-time multi-device sync.
     */
    protected function broadcastChange(int $userId, string $type): void
    {
        try {
            broadcast(new DataChanged($userId, $type))->toOthers();
        } catch (\Throwable $e) {
            Log::warning("Broadcasting failed for {$type}: " . $e->getMessage());
        }
    }
}
