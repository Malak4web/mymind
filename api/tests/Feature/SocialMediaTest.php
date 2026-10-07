<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialMediaTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create(['name' => 'المستخدم أ', 'email' => 'userA@mymind.com']);
        $this->userB = User::factory()->create(['name' => 'المستخدم ب', 'email' => 'userB@mymind.com']);
    }

    public function test_user_can_connect_and_list_own_social_accounts()
    {
        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/social/accounts', [
            'platform' => 'facebook',
            'account_id' => 'fb_page_100',
            'account_name' => 'صفحة فيسبوك أ',
            'account_username' => 'page_a',
            'followers_count' => 1500,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('account_name', 'صفحة فيسبوك أ');

        $list = $this->getJson('/api/social/accounts');

        $list->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_strict_isolation_user_b_cannot_see_user_a_accounts()
    {
        SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'instagram',
            'account_id' => 'ig_100',
            'account_name' => 'إنستجرام المستخدم أ',
        ]);

        Sanctum::actingAs($this->userB);

        $responseB = $this->getJson('/api/social/accounts');

        $responseB->assertStatus(200)
            ->assertJsonCount(0);
    }

    public function test_user_can_create_and_schedule_post()
    {
        Sanctum::actingAs($this->userA);

        $scheduledTime = now()->addDays(2)->toIso8601String();

        $response = $this->postJson('/api/social/posts', [
            'content' => 'منشور مجدول تجريبي عبر API',
            'platforms' => ['facebook', 'linkedin'],
            'status' => 'scheduled',
            'scheduled_at' => $scheduledTime,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'scheduled')
            ->assertJsonPath('content', 'منشور مجدول تجريبي عبر API');

        $posts = $this->getJson('/api/social/posts?status=scheduled');

        $posts->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_strict_isolation_user_b_cannot_see_user_a_posts()
    {
        SocialPost::create([
            'user_id' => $this->userA->id,
            'content' => 'منشور خاص بالمستخدم أ',
            'platforms' => ['youtube'],
            'status' => 'draft',
        ]);

        Sanctum::actingAs($this->userB);

        $responseB = $this->getJson('/api/social/posts');

        $responseB->assertStatus(200)
            ->assertJsonCount(0);
    }

    public function test_strict_isolation_for_social_settings_and_api_keys()
    {
        // User A saves Facebook App credentials
        Sanctum::actingAs($this->userA);

        $saveResponse = $this->postJson('/api/social/settings', [
            'platform' => 'facebook',
            'app_id' => 'app_id_user_a',
            'app_secret' => 'super_secret_user_a',
            'access_token' => 'token_user_a',
        ]);

        $saveResponse->assertStatus(200);

        // User A retrieves settings
        $settingsA = $this->getJson('/api/social/settings');

        $settingsA->assertStatus(200)
            ->assertJsonPath('facebook.app_id', 'app_id_user_a')
            ->assertJsonPath('facebook.app_secret', 'super_secret_user_a');

        // User B retrieves settings -> must be blank / NOT see User A credentials
        Sanctum::actingAs($this->userB);

        $settingsB = $this->getJson('/api/social/settings');

        $settingsB->assertStatus(200)
            ->assertJsonPath('facebook.app_id', '')
            ->assertJsonPath('facebook.app_secret', '');
    }

    public function test_user_must_login_before_discovering_pages()
    {
        Sanctum::actingAs($this->userA);

        // Before logging in on platform -> must return needs_login: true and 0 pages
        $res = $this->getJson('/api/social/available-pages?platform=facebook');
        $res->assertStatus(200)
            ->assertJsonPath('needs_login', true)
            ->assertJsonCount(0, 'pages');

        // Check auth status
        $statusRes = $this->getJson('/api/social/oauth/facebook/status');
        $statusRes->assertStatus(200)
            ->assertJsonPath('is_authenticated', false);

        // Perform login on the platform
        $loginRes = $this->postJson('/api/social/oauth/facebook/demo-login');
        $loginRes->assertStatus(200)
            ->assertJsonPath('is_authenticated', true);

        // Now fetch available Facebook pages -> must return pages
        $resAfter = $this->getJson('/api/social/available-pages?platform=facebook');
        $resAfter->assertStatus(200)
            ->assertJsonPath('needs_login', false)
            ->assertJsonStructure([
                'pages' => [
                    '*' => ['account_id', 'account_name', 'account_username', 'avatar_url', 'category', 'followers_count', 'is_connected']
                ]
            ]);

        $firstPage = $resAfter->json('pages.0');
        $this->assertFalse($firstPage['is_connected']);

        // Connect this discovered page with 1-click
        $connectRes = $this->postJson('/api/social/accounts', [
            'platform' => 'facebook',
            'account_id' => $firstPage['account_id'],
            'account_name' => $firstPage['account_name'],
            'account_username' => $firstPage['account_username'],
            'avatar_url' => $firstPage['avatar_url'],
            'followers_count' => $firstPage['followers_count'],
        ]);
        $connectRes->assertStatus(201);

        // Re-fetch available pages -> is_connected should be true for that page
        $resConnected = $this->getJson('/api/social/available-pages?platform=facebook');
        $resConnected->assertStatus(200);
        $updatedFirstPage = $resConnected->json('pages.0');
        $this->assertTrue($updatedFirstPage['is_connected']);
    }

    public function test_user_can_retrieve_analytics_and_per_page_percentages()
    {
        Sanctum::actingAs($this->userA);

        $acc = SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'facebook',
            'account_id' => 'fb_page_anal_1',
            'account_name' => 'صفحة التحليلات',
            'followers_count' => 10000,
        ]);

        SocialPost::create([
            'user_id' => $this->userA->id,
            'content' => 'منشور فيسبوك تحليلي',
            'platforms' => ['facebook'],
            'account_ids' => ['fb_page_anal_1'],
            'status' => 'published',
            'published_at' => now(),
            'metrics' => [
                'likes' => 500,
                'comments' => 100,
                'shares' => 50,
                'views' => 4500,
                'engagement_rate' => 6.5,
            ],
        ]);

        $res = $this->getJson('/api/social/analytics?platform=facebook');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'summary' => [
                    'total_pages',
                    'total_posts',
                    'total_followers',
                    'total_likes',
                    'total_comments',
                    'total_shares',
                    'total_interactions',
                    'average_engagement_rate',
                    'total_reach',
                ],
                'pages' => [
                    '*' => [
                        'account_id',
                        'account_name',
                        'followers_count',
                        'posts_count',
                        'total_likes',
                        'total_comments',
                        'total_shares',
                        'total_interactions',
                        'engagement_rate',
                        'likes_percentage',
                        'comments_percentage',
                        'shares_percentage',
                        'interactions_per_post',
                    ]
                ],
                'posts',
            ]);

        $page = $res->json('pages.0');
        $this->assertEquals('fb_page_anal_1', $page['account_id']);
        $this->assertEquals(650, $page['total_interactions']);
        $this->assertEquals(6.5, $page['engagement_rate']);
        $this->assertGreaterThan(0, $page['likes_percentage']);
    }

    public function test_user_can_sync_external_posts_and_populate_metrics()
    {
        Sanctum::actingAs($this->userA);

        \Illuminate\Support\Facades\Http::fake([
            'https://graph.facebook.com/v21.0/fb_page_sync_1/videos*' => \Illuminate\Support\Facades\Http::response([
                'data' => []
            ], 200),
            'https://graph.facebook.com/v21.0/fb_page_sync_1/*' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    [
                        'id' => 'fb_page_sync_1_post_1001',
                        'message' => 'منشور فيسبوك حقيقي عبر Graph API',
                        'created_time' => now()->subDay()->toIso8601String(),
                        'permalink_url' => 'https://facebook.com/fb_page_sync_1/posts/1001',
                        'shares' => ['count' => 15],
                        'reactions' => ['summary' => ['total_count' => 120]],
                        'comments' => ['summary' => ['total_count' => 30]],
                    ]
                ]
            ], 200),
        ]);

        SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'facebook',
            'account_id' => 'fb_page_sync_1',
            'account_name' => 'صفحة المزامنة',
            'followers_count' => 8000,
            'access_token' => 'real_page_access_token_123',
        ]);

        $res = $this->postJson('/api/social/sync-posts');

        $res->assertStatus(200)
            ->assertJsonPath('synced_accounts', 1)
            ->assertJsonPath('synced_posts', 1);

        $postsRes = $this->getJson('/api/social/posts');
        $postsRes->assertStatus(200);
        $this->assertNotEmpty($postsRes->json());

        $firstPost = $postsRes->json('0');
        $this->assertEquals('منشور فيسبوك حقيقي عبر Graph API', $firstPost['content']);
        $this->assertEquals(120, $firstPost['metrics']['likes']);
        $this->assertEquals(30, $firstPost['metrics']['comments']);
        $this->assertEquals(15, $firstPost['metrics']['shares']);
        $this->assertArrayHasKey('engagement_rate', $firstPost['metrics']);
    }

    public function test_no_fake_sample_posts_are_generated_when_platform_returns_no_posts()
    {
        Sanctum::actingAs($this->userA);

        \Illuminate\Support\Facades\Http::fake([
            'https://graph.facebook.com/v21.0/fb_page_empty_1/*' => \Illuminate\Support\Facades\Http::response([
                'data' => []
            ], 200),
        ]);

        SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'facebook',
            'account_id' => 'fb_page_empty_1',
            'account_name' => 'صفحة فارغة بدون منشورات',
            'followers_count' => 1,
            'access_token' => 'real_page_access_token_empty',
        ]);

        $res = $this->postJson('/api/social/sync-posts');

        $res->assertStatus(200)
            ->assertJsonPath('synced_accounts', 1)
            ->assertJsonPath('synced_posts', 0);

        $postsRes = $this->getJson('/api/social/posts');
        $postsRes->assertStatus(200);
        $this->assertEmpty($postsRes->json());
    }

    public function test_user_can_upload_image_for_social_post()
    {
        Sanctum::actingAs($this->userA);
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->image('post_photo.jpg', 600, 600);

        $res = $this->postJson('/api/social/upload-media', [
            'file' => $file,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('name', 'post_photo.jpg')
            ->assertJsonPath('type', 'image')
            ->assertJsonPath('is_video', false);

        $this->assertNotNull($res->json('url'));
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($res->json('path'));
    }

    public function test_user_can_upload_video_for_social_post()
    {
        Sanctum::actingAs($this->userA);
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->create('reel_video.mp4', 5000, 'video/mp4');

        $res = $this->postJson('/api/social/upload-media', [
            'file' => $file,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('name', 'reel_video.mp4')
            ->assertJsonPath('type', 'video')
            ->assertJsonPath('is_video', true);

        $this->assertNotNull($res->json('url'));
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($res->json('path'));
    }

    public function test_disallowed_media_file_types_are_rejected()
    {
        Sanctum::actingAs($this->userA);
        \Illuminate\Support\Facades\Storage::fake('public');

        $file = \Illuminate\Http\UploadedFile::fake()->create('shell.php', 10, 'text/x-php');

        $res = $this->postJson('/api/social/upload-media', [
            'file' => $file,
        ]);

        $res->assertStatus(422);
    }

    public function test_user_can_sync_youtube_channel_videos_and_metrics()
    {
        Sanctum::actingAs($this->userA);

        \Illuminate\Support\Facades\Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => \Illuminate\Support\Facades\Http::response([
                'items' => [
                    [
                        'id' => 'UC_test_channel_123',
                        'snippet' => ['title' => 'قناة يوتيوب تقنية'],
                        'contentDetails' => [
                            'relatedPlaylists' => ['uploads' => 'UU_test_channel_123']
                        ],
                        'statistics' => ['subscriberCount' => '25000']
                    ]
                ]
            ], 200),
            'https://www.googleapis.com/youtube/v3/playlistItems*' => \Illuminate\Support\Facades\Http::response([
                'items' => [
                    [
                        'contentDetails' => ['videoId' => 'vid_yt_2001'],
                        'snippet' => [
                            'title' => 'فيديو شرح النظام الجديد 2026',
                            'description' => 'شرح كامل ومفصل لجميع الميزات الجديدة',
                            'publishedAt' => now()->subHours(5)->toIso8601String(),
                            'thumbnails' => [
                                'high' => ['url' => 'https://i.ytimg.com/vi/vid_yt_2001/hqdefault.jpg']
                            ]
                        ]
                    ]
                ]
            ], 200),
            'https://www.googleapis.com/youtube/v3/videos*' => \Illuminate\Support\Facades\Http::response([
                'items' => [
                    [
                        'id' => 'vid_yt_2001',
                        'snippet' => [
                            'title' => 'فيديو شرح النظام الجديد 2026',
                            'description' => 'شرح كامل ومفصل لجميع الميزات الجديدة',
                            'publishedAt' => now()->subHours(5)->toIso8601String(),
                            'thumbnails' => [
                                'high' => ['url' => 'https://i.ytimg.com/vi/vid_yt_2001/hqdefault.jpg']
                            ]
                        ],
                        'statistics' => [
                            'viewCount' => '15400',
                            'likeCount' => '850',
                            'commentCount' => '95'
                        ]
                    ]
                ]
            ], 200),
        ]);

        SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'youtube',
            'account_id' => 'UC_test_channel_123',
            'account_name' => 'قناة يوتيوب تقنية',
            'followers_count' => 1000,
            'access_token' => 'real_yt_access_token_xyz',
        ]);

        $res = $this->postJson('/api/social/sync-posts');
        $res->assertStatus(200)
            ->assertJsonPath('synced_accounts', 1)
            ->assertJsonPath('synced_posts', 1);

        $postsRes = $this->getJson('/api/social/posts?platform=youtube');
        $postsRes->assertStatus(200);
        $this->assertCount(1, $postsRes->json());

        $ytPost = $postsRes->json('0');
        $this->assertStringContainsString('فيديو شرح النظام الجديد 2026', $ytPost['content']);
        $this->assertEquals(850, $ytPost['metrics']['likes']);
        $this->assertEquals(95, $ytPost['metrics']['comments']);
        $this->assertEquals(15400, $ytPost['metrics']['views']);
        $this->assertEquals('https://www.youtube.com/watch?v=vid_yt_2001', $ytPost['platform_post_ids']['youtube']['url']);

        // Check analytics for YouTube
        $analRes = $this->getJson('/api/social/analytics?platform=youtube');
        $analRes->assertStatus(200);
        $this->assertEquals(1, $analRes->json('summary.total_pages'));
        $this->assertEquals(1, $analRes->json('summary.total_posts'));
        $this->assertEquals(850, $analRes->json('summary.total_likes'));
    }

    public function test_disconnecting_account_removes_its_posts_and_prevents_orphan_posts()
    {
        Sanctum::actingAs($this->userA);

        $acc = SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'facebook',
            'account_id' => 'fb_page_to_delete',
            'account_name' => 'صفحة ستُحذف',
        ]);

        SocialPost::create([
            'user_id' => $this->userA->id,
            'content' => 'منشور على صفحة ستُحذف',
            'platforms' => ['facebook'],
            'account_ids' => ['fb_page_to_delete'],
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Post exists
        $postsBefore = $this->getJson('/api/social/posts');
        $postsBefore->assertStatus(200)
            ->assertJsonCount(1);

        // Delete the account
        $delRes = $this->deleteJson("/api/social/accounts/{$acc->id}");
        $delRes->assertStatus(200);

        // Posts belonging to deleted account must be gone
        $postsAfter = $this->getJson('/api/social/posts');
        $postsAfter->assertStatus(200)
            ->assertJsonCount(0);
    }
}

