<?php

namespace Tests\Feature\Messages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class ListTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_customer_sees_only_their_own_thread(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $own = $this->makeCustomer('Own Co', $user);
        $other = $this->makeCustomer('Other Co');
        $this->makeThread($own, ['subject' => 'Mine']);
        $this->makeThread($other, ['subject' => 'Not mine']);

        $response = $this->actingAsUser($user)->get('/messages');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('threads.total', 1)
            ->where('threads.data.0.subject', 'Mine')
        );
    }

    public function test_orphaned_customer_sees_empty_inbox_not_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAsUser($user)->get('/messages');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('threads.total', 0));
    }

    public function test_staff_sees_all_threads(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $this->makeThread($this->makeCustomer('A'));
        $this->makeThread($this->makeCustomer('B'));

        $response = $this->actingAsUser($staff)->get('/messages');

        $response->assertInertia(fn ($page) => $page->where('threads.total', 2));
    }

    public function test_unread_filter_matches_root_or_reply_unread(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();

        // Root is read (sender_type=customer, staff's incoming side),
        // but reply is unread -- still should show under "unread".
        $thread = $this->makeThread($customer, ['sender_type' => 'customer', 'is_read' => true]);
        $thread->replies()->create([
            'customer_id' => $customer->id,
            'subject' => $thread->subject,
            'body' => 'reply',
            'sender_type' => 'customer',
            'is_read' => false,
            'status' => 'open',
            'channel' => 'portal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $readThread = $this->makeThread($customer, ['sender_type' => 'customer', 'is_read' => true]);

        $response = $this->actingAsUser($staff)->get('/messages?status=unread');

        $response->assertInertia(fn ($page) => $page->where('threads.total', 1));
    }

    public function test_list_paginates(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);
        $customer = $this->makeCustomer();
        for ($i = 0; $i < 30; $i++) {
            $this->makeThread($customer, ['subject' => "Thread {$i}"]);
        }

        $response = $this->actingAsUser($staff)->get('/messages');

        $response->assertInertia(fn ($page) => $page
            ->where('threads.total', 30)
            ->where('threads.last_page', 2)
        );
    }
}
