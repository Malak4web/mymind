<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\SocialSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SocialOAuthController extends Controller
{
    /**
     * Supported platforms
     */
    protected array $supportedPlatforms = ['facebook', 'instagram', 'youtube', 'linkedin'];

    /**
     * Generate the OAuth redirect URL for a given platform.
     * The frontend opens this URL in a popup window.
     */
    public function getRedirectUrl(Request $request, string $platform)
    {
        $user = $this->currentUser($request);
        $this->validatePlatform($platform);

        $setting = SocialSetting::firstOrCreate([
            'user_id' => $user->id,
            'platform' => $platform,
        ]);

        $appId     = $setting->app_id ?: env(strtoupper($platform) . '_CLIENT_ID');
        $appSecret = $setting->app_secret ?: env(strtoupper($platform) . '_CLIENT_SECRET');

        if (!$appId || !$appSecret) {
            return response()->json([
                'error' => 'missing_credentials',
                'message' => 'يجب إدخال معرّف التطبيق (App ID) والمفتاح السري (App Secret) في إعدادات المنصة أولاً لتسجيل الدخول عبر الـ OAuth الرسمي',
                'settings_required' => true,
            ], 422);
        }

        // Build callback URL
        $callbackUrl = url("/api/social/oauth/{$platform}/callback");

        // Generate state token
        $stateToken = Str::random(40);
        $setting->update([
            'webhook_verify_token' => $stateToken,
        ]);

        $redirectUrl = $this->buildOAuthUrl($platform, $appId, $callbackUrl, $stateToken);

        return response()->json([
            'redirect_url' => $redirectUrl,
            'state' => $stateToken,
        ]);
    }

    /**
     * Handle the OAuth callback from the platform.
     * Public endpoint called by the platform redirect in the popup.
     */
    public function handleCallback(Request $request, string $platform)
    {
        $this->validatePlatform($platform);

        $code  = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error') ?? $request->query('error_description');

        if ($error || !$code) {
            return $this->renderCallbackPage(false, $platform, $error ?: 'تم إلغاء عملية تسجيل الدخول أو رفض الإذن');
        }

        // Match state token to identify the user
        $setting = SocialSetting::where('platform', $platform)
            ->where('webhook_verify_token', $state)
            ->first();

        if (!$setting) {
            return $this->renderCallbackPage(false, $platform, 'انتهت صلاحية جلسة تسجيل الدخول أو الرمز غير صحيح');
        }

        $callbackUrl = url("/api/social/oauth/{$platform}/callback");

        $appId     = $setting->app_id ?: env(strtoupper($platform) . '_CLIENT_ID');
        $appSecret = $setting->app_secret ?: env(strtoupper($platform) . '_CLIENT_SECRET');

        // Exchange code for token
        $tokenData = $this->exchangeCodeForToken(
            $platform,
            $code,
            $appId,
            $appSecret,
            $callbackUrl
        );

        if (!$tokenData || isset($tokenData['error'])) {
            $errMsg = $tokenData['error']['message'] ?? $tokenData['error_description'] ?? 'فشل في استلام رمز الوصول من المنصة';
            return $this->renderCallbackPage(false, $platform, $errMsg);
        }

        $accessToken = $tokenData['access_token'] ?? null;
        if ($accessToken) {
            $setting->update([
                'access_token' => $accessToken,
                'webhook_verify_token' => null,
                'is_active' => true,
            ]);
        }

        return $this->renderCallbackPage(true, $platform);
    }

    /**
     * Check whether a user is currently authenticated with a platform.
     */
    public function checkAuthStatus(Request $request, string $platform)
    {
        $user = $this->currentUser($request);
        $this->validatePlatform($platform);

        $setting = SocialSetting::where('user_id', $user->id)
            ->where('platform', $platform)
            ->first();

        $hasToken    = !empty($setting->access_token ?? null);
        $hasAppCreds = !empty($setting->app_id ?? null) && !empty($setting->app_secret ?? null);

        return response()->json([
            'platform' => $platform,
            'is_authenticated' => $hasToken,
            'has_app_credentials' => $hasAppCreds,
            'token_preview' => $hasToken ? substr($setting->access_token, 0, 8) . '...' : null,
        ]);
    }

    /**
     * Fast login / Demo login or manual token submission.
     */
    public function demoLogin(Request $request, string $platform)
    {
        $user = $this->currentUser($request);
        $this->validatePlatform($platform);

        $customToken = $request->input('access_token');
        $token = $customToken ?: ('demo_token_' . Str::random(32));

        $setting = SocialSetting::updateOrCreate(
            ['user_id' => $user->id, 'platform' => $platform],
            [
                'access_token' => $token,
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح!',
            'platform' => $platform,
            'is_authenticated' => true,
        ]);
    }

    /**
     * Logout / disconnect from platform.
     */
    public function logout(Request $request, string $platform)
    {
        $user = $this->currentUser($request);
        $this->validatePlatform($platform);

        $setting = SocialSetting::where('user_id', $user->id)
            ->where('platform', $platform)
            ->first();

        if ($setting) {
            $setting->update([
                'access_token' => null,
            ]);
        }

        return response()->json([
            'message' => 'تم تسجيل الخروج من ' . $platform,
            'platform' => $platform,
            'is_authenticated' => false,
        ]);
    }

    /**
     * Fetch available pages/channels.
     * Returns needs_login: true if user is NOT authenticated.
     */
    public function getAvailablePages(Request $request)
    {
        $user     = $this->currentUser($request);
        $platform = $request->query('platform', 'facebook');

        $this->validatePlatform($platform);

        $setting = SocialSetting::where('user_id', $user->id)
            ->where('platform', $platform)
            ->first();

        $accessToken = $setting->access_token ?? null;

        // If user hasn't logged in on this platform yet:
        if (!$accessToken) {
            return response()->json([
                'error' => 'not_authenticated',
                'message' => 'يجب تسجيل الدخول بالحساب أولاً لعرض الصفحات',
                'needs_login' => true,
                'pages' => [],
            ]);
        }

        $connectedIds = SocialAccount::where('user_id', $user->id)
            ->where('platform', $platform)
            ->pluck('account_id')
            ->toArray();

        $pages = [];

        if (str_starts_with($accessToken, 'demo_token_')) {
            $pages = $this->getMockPagesForUser($platform, $user);
        } else {
            try {
                $pages = $this->fetchPagesFromPlatform($platform, $accessToken, $user);
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch pages from {$platform}: " . $e->getMessage());

                return response()->json([
                    'error' => 'fetch_failed',
                    'message' => 'فشل جلب الصفحات من ' . $platform . '. يرجى إعادة تسجيل الدخول.',
                    'needs_login' => true,
                    'pages' => [],
                ]);
            }
        }

        $authUser = null;
        if (!str_starts_with($accessToken, 'demo_token_')) {
            if ($platform === 'facebook' || $platform === 'instagram') {
                try {
                    $uRes = Http::get('https://graph.facebook.com/v21.0/me', [
                        'access_token' => $accessToken,
                        'fields' => 'id,name,picture{url}',
                    ]);
                    if ($uRes->ok()) {
                        $authUser = [
                            'id' => $uRes->json('id'),
                            'name' => $uRes->json('name'),
                            'avatar' => $uRes->json('picture.data.url'),
                        ];
                    }
                } catch (\Throwable $e) {}
            }
        }

        foreach ($pages as &$p) {
            $p['is_connected'] = in_array($p['account_id'], $connectedIds);
        }

        return response()->json([
            'error' => null,
            'needs_login' => false,
            'auth_user' => $authUser,
            'pages' => $pages,
        ]);
    }

    // ==========================================
    // Private Helpers
    // ==========================================

    private function validatePlatform(string $platform): void
    {
        if (!in_array($platform, $this->supportedPlatforms)) {
            abort(422, 'منصة غير مدعومة');
        }
    }

    private function buildOAuthUrl(string $platform, string $appId, string $callbackUrl, string $state): string
    {
        switch ($platform) {
            case 'facebook':
                return 'https://www.facebook.com/v21.0/dialog/oauth?' . http_build_query([
                    'client_id'     => $appId,
                    'redirect_uri'  => $callbackUrl,
                    'state'         => $state,
                    'scope'         => 'public_profile,pages_show_list,pages_read_engagement,pages_manage_posts,pages_manage_metadata,business_management',
                    'response_type' => 'code',
                    'auth_type'     => 'rerequest',
                ]);

            case 'instagram':
                return 'https://www.facebook.com/v21.0/dialog/oauth?' . http_build_query([
                    'client_id'     => $appId,
                    'redirect_uri'  => $callbackUrl,
                    'state'         => $state,
                    'scope'         => 'public_profile,instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement,business_management',
                    'response_type' => 'code',
                    'auth_type'     => 'rerequest',
                ]);

            case 'youtube':
                return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                    'client_id'     => $appId,
                    'redirect_uri'  => $callbackUrl,
                    'state'         => $state,
                    'scope'         => 'https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/youtube.upload',
                    'response_type' => 'code',
                    'access_type'   => 'offline',
                    'prompt'        => 'consent',
                ]);

            case 'linkedin':
                return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
                    'client_id'     => $appId,
                    'redirect_uri'  => $callbackUrl,
                    'state'         => $state,
                    'scope'         => 'r_liteprofile r_organization_social w_organization_social rw_organization_admin',
                    'response_type' => 'code',
                ]);

            default:
                abort(422, 'منصة غير مدعومة');
        }
    }

    private function exchangeCodeForToken(string $platform, string $code, string $appId, string $appSecret, string $callbackUrl): ?array
    {
        try {
            switch ($platform) {
                case 'facebook':
                case 'instagram':
                    $response = Http::get('https://graph.facebook.com/v21.0/oauth/access_token', [
                        'client_id'     => $appId,
                        'client_secret' => $appSecret,
                        'redirect_uri'  => $callbackUrl,
                        'code'          => $code,
                    ]);
                    return $response->json();

                case 'youtube':
                    $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                        'client_id'     => $appId,
                        'client_secret' => $appSecret,
                        'redirect_uri'  => $callbackUrl,
                        'code'          => $code,
                        'grant_type'    => 'authorization_code',
                    ]);
                    return $response->json();

                case 'linkedin':
                    $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
                        'client_id'     => $appId,
                        'client_secret' => $appSecret,
                        'redirect_uri'  => $callbackUrl,
                        'code'          => $code,
                        'grant_type'    => 'authorization_code',
                    ]);
                    return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error("OAuth token exchange failed for {$platform}: " . $e->getMessage());
            return ['error' => ['message' => $e->getMessage()]];
        }

        return null;
    }

    private function fetchPagesFromPlatform(string $platform, string $accessToken, $user): array
    {
        switch ($platform) {
            case 'facebook':
                return $this->fetchFacebookPages($accessToken);

            case 'instagram':
                return $this->fetchInstagramAccounts($accessToken);

            case 'youtube':
                return $this->fetchYouTubeChannels($accessToken);

            case 'linkedin':
                return $this->fetchLinkedInOrganizations($accessToken);

            default:
                return [];
        }
    }

    private function fetchFacebookPages(string $accessToken): array
    {
        $response = Http::get('https://graph.facebook.com/v21.0/me/accounts', [
            'access_token' => $accessToken,
            'fields'       => 'id,name,username,picture{url},category,fan_count,access_token',
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('Facebook API error: ' . $response->body());
        }

        $data = $response->json('data', []);

        // Fallback: If personal accounts returned empty, check Meta Business accounts (owned or client pages)
        if (empty($data)) {
            try {
                $bizRes = Http::get('https://graph.facebook.com/v21.0/me/businesses', [
                    'access_token' => $accessToken,
                    'fields'       => 'id,name,client_pages{id,name,username,picture{url},category,fan_count,access_token},owned_pages{id,name,username,picture{url},category,fan_count,access_token}',
                ]);
                if ($bizRes->ok()) {
                    foreach ($bizRes->json('data', []) as $biz) {
                        $clientPages = $biz['client_pages']['data'] ?? [];
                        $ownedPages  = $biz['owned_pages']['data'] ?? [];
                        foreach (array_merge($clientPages, $ownedPages) as $bp) {
                            $data[] = $bp;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::info('Facebook businesses check: ' . $e->getMessage());
            }
        }

        return array_map(function ($page) {
            return [
                'account_id'       => (string)$page['id'],
                'account_name'     => $page['name'],
                'account_username' => $page['username'] ?? '',
                'avatar_url'       => $page['picture']['data']['url'] ?? '',
                'category'         => $page['category'] ?? 'صفحة فيسبوك',
                'followers_count'  => $page['followers_count'] ?? $page['fan_count'] ?? 0,
                'page_access_token' => $page['access_token'] ?? null,
            ];
        }, $data);
    }

    private function fetchInstagramAccounts(string $accessToken): array
    {
        $response = Http::get('https://graph.facebook.com/v21.0/me/accounts', [
            'access_token' => $accessToken,
            'fields'       => 'id,name,access_token,instagram_business_account{id,name,username,profile_picture_url,followers_count,biography}',
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('Instagram API error: ' . $response->body());
        }

        $data     = $response->json('data', []);
        $accounts = [];

        foreach ($data as $page) {
            if (!empty($page['instagram_business_account'])) {
                $ig = $page['instagram_business_account'];
                $accounts[] = [
                    'account_id'       => (string)$ig['id'],
                    'account_name'     => $ig['name'] ?? $ig['username'] ?? $page['name'],
                    'account_username' => $ig['username'] ?? '',
                    'avatar_url'       => $ig['profile_picture_url'] ?? '',
                    'category'         => 'حساب أعمال إنستجرام',
                    'followers_count'  => $ig['followers_count'] ?? 0,
                    'page_access_token' => $page['access_token'] ?? null,
                ];
            }
        }

        return $accounts;
    }

    private function fetchYouTubeChannels(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
        ])->get('https://www.googleapis.com/youtube/v3/channels', [
            'part' => 'snippet,statistics',
            'mine' => 'true',
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('YouTube API error: ' . $response->body());
        }

        $items = $response->json('items', []);

        return array_map(function ($channel) {
            $snippet = $channel['snippet'] ?? [];
            $stats   = $channel['statistics'] ?? [];

            return [
                'account_id'       => (string)$channel['id'],
                'account_name'     => $snippet['title'] ?? '',
                'account_username' => $snippet['customUrl'] ?? '',
                'avatar_url'       => $snippet['thumbnails']['default']['url'] ?? '',
                'category'         => 'قناة يوتيوب',
                'followers_count'  => (int)($stats['subscriberCount'] ?? 0),
            ];
        }, $items);
    }

    private function fetchLinkedInOrganizations(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->get('https://api.linkedin.com/v2/organizationAcls', [
            'q'          => 'roleAssignee',
            'role'       => 'ADMINISTRATOR',
            'projection' => '(elements*(organization~(id,localizedName,vanityName,logoV2(original~:playableStreams))))',
        ]);

        if (!$response->ok()) {
            throw new \RuntimeException('LinkedIn API error: ' . $response->body());
        }

        $elements = $response->json('elements', []);
        $orgs     = [];

        foreach ($elements as $el) {
            $org = $el['organization~'] ?? [];
            if (!empty($org)) {
                $logoUrl = '';
                if (!empty($org['logoV2']['original~']['elements'][0]['identifiers'][0]['identifier'])) {
                    $logoUrl = $org['logoV2']['original~']['elements'][0]['identifiers'][0]['identifier'];
                }

                $orgs[] = [
                    'account_id'       => (string)($org['id'] ?? ''),
                    'account_name'     => $org['localizedName'] ?? '',
                    'account_username' => $org['vanityName'] ?? '',
                    'avatar_url'       => $logoUrl,
                    'category'         => 'صفحة شركة / منظمة',
                    'followers_count'  => 0,
                ];
            }
        }

        return $orgs;
    }

    private function getMockPagesForUser(string $platform, $user): array
    {
        $slug = Str::slug($user->name, '_');
        switch ($platform) {
            case 'facebook':
                return [
                    [
                        'account_id' => 'fb_' . $user->id . '_page_1',
                        'account_name' => $user->name . ' - الصفحة الرسمية',
                        'account_username' => 'official_' . $slug,
                        'avatar_url' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=100&auto=format&fit=crop&q=60',
                        'category' => 'صفحة أعمال / شركة',
                        'followers_count' => 12450,
                    ],
                    [
                        'account_id' => 'fb_' . $user->id . '_page_2',
                        'account_name' => 'مجتمع ' . $user->name,
                        'account_username' => 'community_' . $slug,
                        'avatar_url' => 'https://images.unsplash.com/photo-1557683316-973673baf926?w=100&auto=format&fit=crop&q=60',
                        'category' => 'مجتمع وتقنية',
                        'followers_count' => 3800,
                    ],
                ];
            case 'instagram':
                return [
                    [
                        'account_id' => 'ig_' . $user->id . '_biz_1',
                        'account_name' => $user->name . ' (Business)',
                        'account_username' => $slug . '_biz',
                        'avatar_url' => 'https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=100&auto=format&fit=crop&q=60',
                        'category' => 'حساب أعمال إنستجرام',
                        'followers_count' => 28900,
                    ],
                ];
            case 'youtube':
                return [
                    [
                        'account_id' => 'yt_' . $user->id . '_chan_1',
                        'account_name' => 'قناة ' . $user->name . ' الرسمية',
                        'account_username' => '@' . Str::slug($user->name, ''),
                        'avatar_url' => 'https://images.unsplash.com/photo-1611162616475-46b635cb6868?w=100&auto=format&fit=crop&q=60',
                        'category' => 'قناة تقنية وتعليمية',
                        'followers_count' => 9500,
                    ],
                ];
            case 'linkedin':
                return [
                    [
                        'account_id' => 'li_' . $user->id . '_org_1',
                        'account_name' => 'شركة ' . $user->name . ' للحلول الذكية',
                        'account_username' => Str::slug($user->name, '-') . '-solutions',
                        'avatar_url' => 'https://images.unsplash.com/photo-1560179707-f14e90ef3623?w=100&auto=format&fit=crop&q=60',
                        'category' => 'صفحة منظمة / شركة',
                        'followers_count' => 7200,
                    ],
                ];
            default:
                return [];
        }
    }

    private function renderCallbackPage(bool $success, string $platform, ?string $errorMessage = null): \Illuminate\Http\Response
    {
        $data = json_encode([
            'type'     => 'social_oauth_callback',
            'success'  => $success,
            'platform' => $platform,
            'error'    => $errorMessage,
        ], JSON_UNESCAPED_UNICODE);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ربط الحساب</title>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #0f172a; text-align: center; direction: rtl; color: #f8fafc; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 24px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,.4); max-width: 380px; }
        .success { font-size: 3rem; }
        .error { font-size: 3rem; }
        h2 { margin: 1rem 0 .5rem; font-size: 1.15rem; color: #f8fafc; font-weight: 800; }
        p { color: #94a3b8; font-size: .85rem; margin: 0; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <div class="{$this->getStatusClass($success)}">{$this->getStatusIcon($success)}</div>
        <h2>{$this->getStatusTitle($success, $platform)}</h2>
        <p>{$this->getStatusMessage($success, $errorMessage)}</p>
    </div>
    <script>
        try {
            if (window.opener) {
                window.opener.postMessage({$data}, '*');
            }
        } catch(e) {}
        setTimeout(function() { window.close(); }, 2000);
    </script>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }

    private function getStatusClass(bool $success): string
    {
        return $success ? 'success' : 'error';
    }

    private function getStatusIcon(bool $success): string
    {
        return $success ? '✅' : '❌';
    }

    private function getStatusTitle(bool $success, string $platform): string
    {
        $names = [
            'facebook'  => 'فيسبوك',
            'instagram' => 'إنستجرام',
            'youtube'   => 'يوتيوب',
            'linkedin'  => 'لينكد إن',
        ];
        $name = $names[$platform] ?? $platform;

        return $success
            ? "تم تسجيل الدخول على {$name} بنجاح!"
            : "تعذر تسجيل الدخول بـ {$name}";
    }

    private function getStatusMessage(bool $success, ?string $error): string
    {
        if ($success) {
            return 'تم التحقق من حسابك بنجاح، سيتم إغلاق النافذة وتحديث صفحاتك...';
        }

        return $error ?: 'حدث خطأ أثناء الاتصال بالمنصة';
    }
}
