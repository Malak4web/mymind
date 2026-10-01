<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ChallengeCheer;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $partner;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'عضو', 'description' => 'عضو عادي']);

        $this->user = User::create([
            'name' => 'محمد التحدي',
            'email' => 'challenger@mymind.com',
            'password' => bcrypt('secret12345'),
            'role_id' => $role->id,
        ]);

        $this->partner = User::create([
            'name' => 'أحمد الشريك',
            'email' => 'partner@mymind.com',
            'password' => bcrypt('secret12345'),
            'role_id' => $role->id,
        ]);

        $this->stranger = User::create([
            'name' => 'شخص غريب',
            'email' => 'stranger@mymind.com',
            'password' => bcrypt('secret12345'),
            'role_id' => $role->id,
        ]);
    }

    public function test_can_create_challenge_with_conditions_reward_and_partner()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/challenges', [
            'title' => 'تحدي 30 يوم لياقة',
            'description' => 'تحدي للوصول للوزن المثالي واللياقة العالية',
            'category' => 'صحة ولياقة',
            'icon' => '🏃',
            'color' => 'from-emerald-500 to-teal-600',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-30',
            'total_days' => 30,
            'reward_title' => 'ساعة ذكية رياضية',
            'reward_icon' => '⌚',
            'reward_description' => 'شراء ساعة جارمين فور إتمام الـ 30 يوم بنجاح',
            'conditions' => ['تمرين 45 دقيقة', 'شرب 3 لتر ماء', 'بدون سكر مضاف'],
            'partner_id' => $this->partner->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'تحدي 30 يوم لياقة')
            ->assertJsonPath('total_days', 30)
            ->assertJsonPath('reward_title', 'ساعة ذكية رياضية')
            ->assertJsonPath('partner.id', $this->partner->id);

        $this->assertDatabaseHas('challenges', [
            'title' => 'تحدي 30 يوم لياقة',
            'user_id' => $this->user->id,
            'partner_id' => $this->partner->id,
        ]);

        // Partner receives an invitation notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->partner->id,
        ]);
    }

    public function test_user_and_partner_can_view_challenge_but_stranger_cannot()
    {
        $challenge = Challenge::create([
            'user_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'title' => 'تحدي مشترك',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-07',
            'total_days' => 7,
            'conditions' => ['قراءة كتاب'],
            'status' => 'active',
        ]);

        // Owner can see
        Sanctum::actingAs($this->user);
        $this->getJson("/api/challenges/{$challenge->id}")->assertStatus(200);

        // Partner can see
        Sanctum::actingAs($this->partner);
        $this->getJson("/api/challenges/{$challenge->id}")->assertStatus(200);

        // Stranger cannot see
        Sanctum::actingAs($this->stranger);
        $this->getJson("/api/challenges/{$challenge->id}")->assertStatus(404);
    }

    public function test_updating_challenge_progress_and_completion()
    {
        Sanctum::actingAs($this->user);

        $challenge = Challenge::create([
            'user_id' => $this->user->id,
            'title' => 'تحدي يومين',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'total_days' => 2,
            'conditions' => ['شرط 1', 'شرط 2'],
            'status' => 'active',
            'days_progress' => [],
        ]);

        // Update day 1 progress: partially done
        $this->putJson("/api/challenges/{$challenge->id}", [
            'days_progress' => [
                '1' => ['completed' => false, 'items' => [true, false]],
            ],
        ])->assertStatus(200)->assertJsonPath('status', 'active');

        // Complete both days
        $this->putJson("/api/challenges/{$challenge->id}", [
            'days_progress' => [
                '1' => ['completed' => true, 'items' => [true, true]],
                '2' => ['completed' => true, 'items' => [true, true]],
            ],
        ])->assertStatus(200)->assertJsonPath('status', 'completed');

        $this->assertEquals('completed', $challenge->fresh()->status);
    }

    public function test_partner_can_send_cheer_and_reactions_generating_notification()
    {
        $challenge = Challenge::create([
            'user_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'title' => 'تحدي الانضباط',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'total_days' => 10,
            'conditions' => ['استيقاظ مبكر'],
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->partner);

        $response = $this->postJson("/api/challenges/{$challenge->id}/cheer", [
            'message' => 'عاش يا بطل! كمل بقوة!',
            'reaction' => '🔥',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'عاش يا بطل! كمل بقوة!')
            ->assertJsonPath('reaction', '🔥');

        $this->assertDatabaseHas('challenge_cheers', [
            'challenge_id' => $challenge->id,
            'user_id' => $this->partner->id,
            'reaction' => '🔥',
        ]);

        // Challenge owner received cheer notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_only_creator_can_delete_challenge()
    {
        $challenge = Challenge::create([
            'user_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'title' => 'تحدي للحذف',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-07',
            'total_days' => 7,
            'conditions' => ['اختبار'],
            'status' => 'active',
        ]);

        // Partner tries to delete -> 404
        Sanctum::actingAs($this->partner);
        $this->deleteJson("/api/challenges/{$challenge->id}")->assertStatus(404);

        // Creator deletes -> 200
        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/challenges/{$challenge->id}")->assertStatus(200);

        $this->assertDatabaseMissing('challenges', ['id' => $challenge->id]);
    }
}
