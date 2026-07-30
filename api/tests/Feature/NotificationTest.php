<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateUser();
    }

    public function test_can_list_notifications()
    {
        $response = $this->getJson('/api/notifications');
        $response->assertStatus(200);
    }

    public function test_can_mark_notification_as_read()
    {
        // We will seed or mock notification manually in code or via test helper
        $response = $this->postJson('/api/testing-notifications-helper');
        $notif = $response->json();

        $readResponse = $this->postJson("/api/notifications/{$notif['id']}/read");
        $readResponse->assertStatus(200)
                     ->assertJsonPath('is_read', true);

        $this->assertDatabaseHas('notifications', [
            'id' => $notif['id'],
            'is_read' => true
        ]);
    }

    public function test_can_mark_all_notifications_as_read()
    {
        $this->postJson('/api/testing-notifications-helper');
        $this->postJson('/api/testing-notifications-helper');

        $readAllResponse = $this->postJson('/api/notifications/read-all');
        $readAllResponse->assertStatus(200);

        $this->assertDatabaseMissing('notifications', ['is_read' => false]);
    }
}
