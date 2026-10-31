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
            'https://graph.facebook.com/v21.0/fb_page_sync_1/video_reels*' => \Illuminate\Support\Facades\Http::response([
                'data' => []
            ], 200),
            'https://graph.facebook.com/v21.0/fb_page_sync_1/scheduled_posts*' => \Illuminate\Support\Facades\Http::response([
                'data' => []
            ], 200),
            'https://graph.facebook.com/v21.0/*' => \Illuminate\Support\Facades\Http::response([
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
            'https://graph.facebook.com/v21.0/fb_page_empty_1*' => \Illuminate\Support\Facades\Http::response([
                'data' => []
            ], 200),
            'graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response([
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
            'https://www.googleapis.com/youtube/v3/*' => \Illuminate\Support\Facades\Http::response([
                'items' => []
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

    public function test_youtube_refresh_updates_current_numbers_even_when_oauth_token_expired()
    {
        Sanctum::actingAs($this->userA);

        // Simulate expired OAuth token on YouTube account (no API key), falling back to live RSS + InnerTube
        \Illuminate\Support\Facades\Http::fake([
            'googleapis.com/youtube/v3/*' => \Illuminate\Support\Facades\Http::response(['error' => ['code' => 401, 'message' => 'Invalid Credentials']], 401),
            'www.youtube.com/youtubei/v1/browse*' => \Illuminate\Support\Facades\Http::response('"subtitle":{"content":"@tech • 18.5K subscribers"}', 200),
            'www.youtube.com/feeds/videos.xml*' => \Illuminate\Support\Facades\Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns:media="http://search.yahoo.com/mrss/" xmlns="http://www.w3.org/2005/Atom">
  <entry>
    <yt:videoId>vid_yt_2001</yt:videoId>
    <title>فيديو شرح النظام الجديد 2026</title>
    <published>2026-10-30T10:00:00+00:00</published>
    <media:group>
      <media:description>وصف الفيديو</media:description>
      <media:thumbnail url="https://i.ytimg.com/vi/vid_yt_2001/hqdefault.jpg"/>
      <media:community>
        <media:starRating count="900"/>
        <media:statistics views="16000"/>
      </media:community>
    </media:group>
  </entry>
</feed>
XML, 200),
            'www.youtube.com/youtubei/v1/next*' => \Illuminate\Support\Facades\Http::response(
                '{"viewCountNumber":"21500","likeCountIfIndifferentNumber":"1420","contextualInfo":{"runs":[{"text":"165"}]},"subtitle":{"content":"18.5K subscribers"}}',
                200
            ),
        ]);

        SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'youtube',
            'account_id' => 'UC_test_channel_123',
            'account_name' => 'قناة يوتيوب تقنية',
            'followers_count' => 1000,
            'access_token' => 'expired_ya29_token',
        ]);

        SocialPost::create([
            'user_id' => $this->userA->id,
            'content' => 'فيديو شرح النظام الجديد 2026',
            'platforms' => ['youtube'],
            'account_ids' => ['UC_test_channel_123'],
            'status' => 'published',
            'published_at' => now()->subDay(),
            'platform_post_ids' => [
                'youtube' => [
                    'id' => 'vid_yt_2001',
                    'url' => 'https://www.youtube.com/watch?v=vid_yt_2001',
                ],
            ],
            'metrics' => [
                'likes' => 100,
                'comments' => 10,
                'shares' => 0,
                'views' => 2000,
                'impressions' => 2000,
                'engagement_rate' => 11,
            ],
        ]);

        $res = $this->postJson('/api/social/sync-posts?platform=youtube&account_id=UC_test_channel_123');
        $res->assertStatus(200)
            ->assertJsonPath('synced_accounts', 1)
            ->assertJsonPath('accounts.0.followers_count', 18500);

        $postsRes = $this->getJson('/api/social/posts?platform=youtube');
        $postsRes->assertStatus(200);
        $updatedPost = $postsRes->json('0');
        $this->assertEquals(21500, $updatedPost['metrics']['views']);
        $this->assertEquals(1420, $updatedPost['metrics']['likes']);
        $this->assertEquals(165, $updatedPost['metrics']['comments']);
    }

    public function test_syncing_facebook_and_youtube_imports_scheduled_posts()
    {
        Sanctum::actingAs($this->userA);

        $futureDate = now()->addDays(2)->setMicrosecond(0);

        \Illuminate\Support\Facades\Http::fake([
            // FB scheduled posts: 1 scheduled post
            'graph.facebook.com/v21.0/fb_page_100/scheduled_posts*' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    [
                        'id' => 'fb_sched_999',
                        'message' => 'منشور مجدول على فيسبوك من المنصة مباشرة',
                        'created_time' => now()->toIso8601String(),
                        'scheduled_publish_time' => $futureDate->timestamp,
                        'full_picture' => 'https://example.com/sched.jpg',
                        'permalink_url' => 'https://facebook.com/fb_sched_999',
                    ],
                ]
            ], 200),
            'graph.facebook.com/v21.0/fb_page_100/published_posts*' => \Illuminate\Support\Facades\Http::response(['data' => []], 200),
            'graph.facebook.com/v21.0/fb_page_100/posts*' => \Illuminate\Support\Facades\Http::response(['data' => []], 200),
            'graph.facebook.com/v21.0/fb_page_100/videos*' => \Illuminate\Support\Facades\Http::response(['data' => []], 200),
            'graph.facebook.com/v21.0/fb_page_100/video_reels*' => \Illuminate\Support\Facades\Http::response(['data' => []], 200),
            'graph.facebook.com/v21.0/fb_page_100/promotable_posts*' => \Illuminate\Support\Facades\Http::response(['data' => []], 200),
            'graph.facebook.com/v21.0/fb_page_100*' => \Illuminate\Support\Facades\Http::response(['id' => 'fb_page_100', 'followers_count' => 500], 200),
            'graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response(['data' => []], 200),
        ]);

        SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'facebook',
            'account_id' => 'fb_page_100',
            'account_name' => 'صفحة فيسبوك تجريبية',
            'followers_count' => 500,
            'access_token' => 'real_fb_token_123',
        ]);

        $syncRes = $this->postJson('/api/social/sync-posts?platform=facebook');
        $syncRes->assertStatus(200);

        // Check posts in scheduled tab/filter
        $schedPostsRes = $this->getJson('/api/social/posts?status=scheduled');
        $schedPostsRes->assertStatus(200);
        $this->assertCount(1, $schedPostsRes->json());

        $schedPost = $schedPostsRes->json('0');
        $this->assertEquals('fb_sched_999', $schedPost['platform_post_ids']['facebook']['id']);
        $this->assertEquals('scheduled', $schedPost['status']);
        $this->assertStringContainsString('منشور مجدول على فيسبوك من المنصة مباشرة', $schedPost['content']);
        $this->assertNotNull($schedPost['scheduled_at']);

        // Test YouTube scheduled premiere / video sync
        \Illuminate\Support\Facades\Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => \Illuminate\Support\Facades\Http::response([
                'items' => [
                    [
                        'id' => 'UC_sched_yt_1',
                        'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU_sched_yt_1']],
                        'statistics' => ['subscriberCount' => 1200],
                    ]
                ]
            ], 200),
            'https://www.googleapis.com/youtube/v3/playlistItems*' => \Illuminate\Support\Facades\Http::response([
                'items' => [] // Uploads playlist is empty of scheduled videos
            ], 200),
            'https://www.googleapis.com/youtube/v3/search*eventType=upcoming*' => \Illuminate\Support\Facades\Http::response([
                'items' => [
                    [
                        'id' => ['videoId' => 'yt_sched_vid_777'],
                        'snippet' => [
                            'title' => 'عرض أول مجدول على يوتيوب',
                            'description' => 'شرح مفصل سيتم عرضه قريباً',
                            'publishedAt' => $futureDate->toIso8601String(),
                        ]
                    ]
                ]
            ], 200),
            'https://www.googleapis.com/youtube/v3/videos*' => \Illuminate\Support\Facades\Http::response([
                'items' => [
                    [
                        'id' => 'yt_sched_vid_777',
                        'snippet' => [
                            'title' => 'عرض أول مجدول على يوتيوب',
                            'description' => 'شرح مفصل سيتم عرضه قريباً',
                            'publishedAt' => $futureDate->toIso8601String(),
                        ],
                        'statistics' => ['viewCount' => 0, 'likeCount' => 0, 'commentCount' => 0],
                        'liveStreamingDetails' => [
                            'scheduledStartTime' => $futureDate->toIso8601String(),
                        ],
                        'status' => [
                            'privacyStatus' => 'public',
                            'uploadStatus' => 'uploaded',
                        ]
                    ]
                ]
            ], 200),
            'https://www.googleapis.com/youtube/v3/*' => \Illuminate\Support\Facades\Http::response(['items' => []], 200),
        ]);

        SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'youtube',
            'account_id' => 'UC_sched_yt_1',
            'account_name' => 'قناة يوتيوب مع فيديوهات مجدولة',
            'followers_count' => 1200,
            'access_token' => 'real_yt_token_sched',
        ]);

        $ytSyncRes = $this->postJson('/api/social/sync-posts?platform=youtube');
        $ytSyncRes->assertStatus(200);

        $schedPostsRes2 = $this->getJson('/api/social/posts?status=scheduled');
        $schedPostsRes2->assertStatus(200);
        $this->assertCount(2, $schedPostsRes2->json());

        $ytSchedPost = collect($schedPostsRes2->json())->firstWhere('platforms.0', 'youtube');
        $this->assertNotNull($ytSchedPost);
        $this->assertEquals('yt_sched_vid_777', $ytSchedPost['platform_post_ids']['youtube']['id']);
        $this->assertEquals('scheduled', $ytSchedPost['status']);
        $this->assertStringContainsString('عرض أول مجدول على يوتيوب', $ytSchedPost['content']);
    }

    public function test_syncing_instagram_reels_populates_exact_views_and_scheduled_posts()
    {
        Sanctum::actingAs($this->userA);

        $futureDate = now()->addDays(1)->setMicrosecond(0);

        \Illuminate\Support\Facades\Http::fake([
            'graph.facebook.com/v21.0/ig_biz_444/media*' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    [
                        'id' => 'ig_reel_101',
                        'caption' => 'تمرين من البيت هيخسّسك الكرش 🔥',
                        'media_type' => 'VIDEO',
                        'media_product_type' => 'REELS',
                        'media_url' => 'https://example.com/reel101.mp4',
                        'thumbnail_url' => 'https://example.com/reel101.jpg',
                        'permalink' => 'https://www.instagram.com/reel/C101/',
                        'timestamp' => now()->subHours(6)->toIso8601String(),
                        'like_count' => 11,
                        'comments_count' => 3,
                    ],
                ],
            ], 200),
            'graph.facebook.com/v21.0/ig_reel_101/insights*' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    ['name' => 'views', 'values' => [['value' => 115]]],
                    ['name' => 'reach', 'values' => [['value' => 98]]],
                    ['name' => 'shares', 'values' => [['value' => 7]]],
                    ['name' => 'saved', 'values' => [['value' => 4]]],
                    ['name' => 'total_interactions', 'values' => [['value' => 25]]],
                ],
            ], 200),
            'graph.facebook.com/v21.0/fb_linked_page_1/scheduled_posts*' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    [
                        'id' => 'ig_meta_sched_55',
                        'message' => 'ريلز مجدول على إنستجرام عبر ميتا',
                        'created_time' => now()->toIso8601String(),
                        'scheduled_publish_time' => $futureDate->timestamp,
                        'full_picture' => 'https://example.com/ig_sched.jpg',
                        'permalink_url' => 'https://instagram.com/p/sched55',
                    ],
                ],
            ], 200),
            'graph.facebook.com/v21.0/ig_biz_444*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'ig_biz_444',
                'username' => 'form_aar',
                'followers_count' => 1000,
                'media_count' => 10,
            ], 200),
            'graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response(['data' => []], 200),
        ]);

        SocialAccount::create([
            'user_id' => $this->userA->id,
            'platform' => 'instagram',
            'account_id' => 'ig_biz_444',
            'account_name' => 'form_aar',
            'account_username' => 'form_aar',
            'followers_count' => 1000,
            'access_token' => 'real_ig_token_444',
            'metadata' => ['page_id' => 'fb_linked_page_1'],
        ]);

        $res = $this->postJson('/api/social/sync-posts?platform=instagram');
        $res->assertStatus(200)
            ->assertJsonPath('synced_accounts', 1)
            ->assertJsonPath('synced_posts', 2);

        $published = $this->getJson('/api/social/posts?platform=instagram&status=published')->json();
        $this->assertCount(1, $published);
        $this->assertEquals(115, $published[0]['metrics']['views']);
        $this->assertEquals(11, $published[0]['metrics']['likes']);
        $this->assertEquals(3, $published[0]['metrics']['comments']);
        $this->assertEquals(7, $published[0]['metrics']['shares']);

        $scheduled = $this->getJson('/api/social/posts?platform=instagram&status=scheduled')->json();
        $this->assertCount(1, $scheduled);
        $this->assertEquals('scheduled', $scheduled[0]['status']);
        $this->assertStringContainsString('ريلز مجدول على إنستجرام عبر ميتا', $scheduled[0]['content']);
    }
}

