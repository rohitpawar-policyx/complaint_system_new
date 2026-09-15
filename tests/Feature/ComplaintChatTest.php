<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Complaint;
use App\Models\ComplaintReason;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ComplaintChatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fakes the event everywhere in this class so the real, network-dependent
     * broadcast() call (which needs a live Reverb server) never actually
     * fires during automated tests - only the channel-authorization tests
     * need the real "reverb" driver's local auth logic, not a live server.
     * Real end-to-end delivery is verified manually with Reverb running.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ChatMessageSent::class]);
    }

    private function makeUser(string $role, string $status = 'approved'): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role], ['description' => $role]);

        return User::factory()->create(['role_id' => $roleModel->id, 'status' => $status]);
    }

    private function makeComplaint(User $owner, string $status = 'in_progress'): Complaint
    {
        $reason = ComplaintReason::create([
            'name' => 'Reason '.uniqid(),
            'priority' => 'LOW',
            'active' => true,
        ]);

        return Complaint::create([
            'user_id' => $owner->id,
            'reason_id' => $reason->id,
            'message' => 'Test complaint',
            'priority' => 'LOW',
            'status' => $status,
        ]);
    }

    /** 1. Customer can access own complaint chat when allowed. */
    public function test_customer_can_view_own_complaint_chat_when_not_pending(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'in_progress');

        $response = $this->actingAs($customer)->get(route('complaints.show', $complaint));

        $response->assertOk();
        $response->assertSee('data-chat', false);
    }

    /** 2. Customer cannot initiate chat while complaint is pending. */
    public function test_customer_cannot_send_message_while_complaint_pending(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'pending');

        $response = $this->actingAs($customer)
            ->postJson(route('complaints.chat.store', $complaint), ['message' => 'Hello']);

        $response->assertStatus(403);
        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertDatabaseCount('chat_conversations', 0);
    }

    /** Pending complaint page does not even render the chat panel for the customer. */
    public function test_pending_complaint_page_shows_no_chat_panel_for_customer(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'pending');

        $response = $this->actingAs($customer)->get(route('complaints.show', $complaint));

        $response->assertOk();
        $response->assertDontSee('data-chat', false);
    }

    /** 3. Customer cannot access another customer's chat (ownership - 404, matching existing convention). */
    public function test_customer_cannot_send_message_on_another_customers_complaint(): void
    {
        $owner = $this->makeUser('user');
        $intruder = $this->makeUser('user');
        $complaint = $this->makeComplaint($owner, 'in_progress');

        $response = $this->actingAs($intruder)
            ->postJson(route('complaints.chat.store', $complaint), ['message' => 'Hello']);

        $response->assertStatus(404);
        $this->assertDatabaseCount('chat_messages', 0);
    }

    /** 3b. Nor can they subscribe to its private broadcast channel. */
    public function test_customer_cannot_authorize_broadcast_channel_for_another_customers_complaint(): void
    {
        $owner = $this->makeUser('user');
        $intruder = $this->makeUser('user');
        $complaint = $this->makeComplaint($owner, 'in_progress');

        $response = $this->actingAs($intruder)->post('/broadcasting/auth', [
            'channel_name' => "private-chat.{$complaint->id}",
        ]);

        $response->assertStatus(403);
    }

    public function test_complaint_owner_can_authorize_broadcast_channel_for_their_own_complaint(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'in_progress');

        $response = $this->actingAs($customer)->post('/broadcasting/auth', [
            'channel_name' => "private-chat.{$complaint->id}",
            'socket_id' => '1234.5678',
        ]);

        $response->assertOk();
    }

    /** 4. Authorized admin can initiate chat - including on a pending complaint. */
    public function test_admin_can_send_message_even_when_complaint_pending(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'pending');

        $response = $this->actingAs($admin)
            ->postJson(route('admin.complaints.chat.store', $complaint), ['message' => 'We are reviewing this.']);

        $response->assertOk();
        $this->assertDatabaseCount('chat_conversations', 1);
        $this->assertDatabaseHas('chat_messages', [
            'message' => 'We are reviewing this.',
            'sender_id' => $admin->id,
        ]);
    }

    public function test_admin_can_authorize_broadcast_channel_for_any_complaint(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'in_progress');

        $response = $this->actingAs($admin)->post('/broadcasting/auth', [
            'channel_name' => "private-chat.{$complaint->id}",
            'socket_id' => '1234.5678',
        ]);

        $response->assertOk();
    }

    /** 5. Unauthorized (unauthenticated) user cannot access chat at all. */
    public function test_guest_cannot_send_chat_message(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'in_progress');

        $response = $this->postJson(route('complaints.chat.store', $complaint), ['message' => 'Hello']);

        $response->assertStatus(401);
    }

    /** 6. Message is persisted. 8. Conversation belongs to the correct complaint. 9. Message belongs to the correct sender. */
    public function test_valid_message_is_persisted_with_correct_conversation_and_sender(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'in_progress');

        $this->actingAs($customer)
            ->postJson(route('complaints.chat.store', $complaint), ['message' => 'Where do things stand?'])
            ->assertOk();

        $conversation = ChatConversation::first();
        $this->assertNotNull($conversation);
        $this->assertSame($complaint->id, $conversation->complaint_id);
        $this->assertSame($customer->id, $conversation->created_by);

        $message = ChatMessage::first();
        $this->assertNotNull($message);
        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertSame($customer->id, $message->sender_id);
        $this->assertSame('Where do things stand?', $message->message);
    }

    /** One complaint has at most one conversation, even across repeated sends. */
    public function test_repeated_messages_reuse_the_same_conversation(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'in_progress');

        $this->actingAs($customer)->postJson(route('complaints.chat.store', $complaint), ['message' => 'First']);
        $this->actingAs($customer)->postJson(route('complaints.chat.store', $complaint), ['message' => 'Second']);

        $this->assertDatabaseCount('chat_conversations', 1);
        $this->assertDatabaseCount('chat_messages', 2);
    }

    /** 7. Empty/invalid message is rejected. */
    public function test_empty_message_is_rejected(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'in_progress');

        $response = $this->actingAs($customer)
            ->postJson(route('complaints.chat.store', $complaint), ['message' => '   ']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_overly_long_message_is_rejected(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'in_progress');

        $response = $this->actingAs($customer)
            ->postJson(route('complaints.chat.store', $complaint), ['message' => str_repeat('a', 2001)]);

        $response->assertStatus(422);
    }

    /** Resolved/closed/rejected complaints are read-only for both sides. */
    public function test_customer_cannot_send_message_on_resolved_complaint(): void
    {
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'resolved');

        $response = $this->actingAs($customer)
            ->postJson(route('complaints.chat.store', $complaint), ['message' => 'Hello']);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_send_message_on_closed_complaint(): void
    {
        $admin = $this->makeUser('admin');
        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'closed');

        $response = $this->actingAs($admin)
            ->postJson(route('admin.complaints.chat.store', $complaint), ['message' => 'Hello']);

        $response->assertStatus(403);
    }

    /** 10. Broadcasting event is fired when a valid message is created. */
    public function test_broadcasting_event_is_fired_on_valid_message(): void
    {
        Event::fake([ChatMessageSent::class]);

        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'in_progress');

        $this->actingAs($customer)
            ->postJson(route('complaints.chat.store', $complaint), ['message' => 'Broadcast me']);

        Event::assertDispatched(ChatMessageSent::class, function (ChatMessageSent $event) use ($complaint) {
            return $event->message->conversation->complaint_id === $complaint->id
                && $event->message->message === 'Broadcast me';
        });
    }

    public function test_broadcasting_event_is_not_fired_when_message_rejected(): void
    {
        Event::fake([ChatMessageSent::class]);

        $customer = $this->makeUser('user');
        $complaint = $this->makeComplaint($customer, 'pending');

        $this->actingAs($customer)
            ->postJson(route('complaints.chat.store', $complaint), ['message' => 'Should not broadcast']);

        Event::assertNotDispatched(ChatMessageSent::class);
    }
}
