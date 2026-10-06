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

    public function test_user_can_discover_and_connect_available_pages()
    {
        Sanctum::actingAs($this->userA);

        // Fetch available Facebook pages
        $res = $this->getJson('/api/social/available-pages?platform=facebook');
        $res->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['account_id', 'account_name', 'account_username', 'avatar_url', 'category', 'followers_count', 'is_connected']
            ]);

        $firstPage = $res->json(0);
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

        // Now re-fetch available pages -> is_connected should be true for that page
        $resAfter = $this->getJson('/api/social/available-pages?platform=facebook');
        $resAfter->assertStatus(200);
        $updatedFirstPage = $resAfter->json(0);
        $this->assertTrue($updatedFirstPage['is_connected']);
    }
}

