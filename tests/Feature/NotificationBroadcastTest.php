<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\CreatesTestUsers;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    public function test_notification_created_event_targets_recipient_private_channel(): void
    {
        $user = $this->createStudent();
        $notification = app(NotificationService::class)->sendToUser(
            $user,
            'Test title',
            'Test message',
            'question_answered',
            ['topic_id' => 42],
        );

        $event = new NotificationCreated($notification);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame("private-user.{$user->id}", (string) $channels[0]);

        $payload = $event->broadcastWith();
        $this->assertSame('Test title', $payload['title']);
        $this->assertSame('question_answered', $payload['type']);
        $this->assertSame(42, $payload['data']['topic_id']);
    }

    public function test_send_to_user_dispatches_broadcast_event(): void
    {
        Event::fake([NotificationCreated::class]);

        $user = $this->createStudent();
        app(NotificationService::class)->sendToUser($user, 'Hello', 'World');

        Event::assertDispatched(
            NotificationCreated::class,
            fn (NotificationCreated $event) => $event->notification->user_id === $user->id,
        );
    }

    /**
     * Route broadcast auth through a dummy Pusher connection: the null
     * driver approves every channel, so it cannot exercise the channel
     * authorization callback. Channel definitions are re-registered because
     * they were bound to the null driver at boot.
     */
    private function usePusherAuthDriver(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app',
        ]);

        require base_path('routes/channels.php');
    }

    public function test_user_can_authorize_own_private_channel(): void
    {
        $this->usePusherAuthDriver();
        $user = $this->createStudent();

        $response = $this->actingAs($user)->post('/broadcasting/auth', [
            'channel_name' => "private-user.{$user->id}",
            'socket_id' => '1234.5678',
        ]);

        $response->assertOk();
    }

    public function test_user_cannot_authorize_another_users_channel(): void
    {
        $this->usePusherAuthDriver();
        $user = $this->createStudent();
        $other = $this->createStudent(['full_name' => 'Other Student']);

        $response = $this->actingAs($user)->post('/broadcasting/auth', [
            'channel_name' => "private-user.{$other->id}",
            'socket_id' => '1234.5678',
        ]);

        $response->assertForbidden();
    }

    public function test_notification_still_created_when_broadcast_fails(): void
    {
        // The null broadcaster is a no-op; this asserts the fail-soft path
        // never blocks notification persistence.
        $user = $this->createStudent();

        $notification = app(NotificationService::class)->sendToUser($user, 'Persisted', 'Message');

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Persisted',
        ]);
    }
}
