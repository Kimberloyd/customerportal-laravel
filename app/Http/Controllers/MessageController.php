<?php

namespace App\Http\Controllers;

use App\Events\CustomerMessageSent;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\User;
use App\Support\CustomerScope;
use App\Support\FacebookMessenger;
use App\Support\MessageThread;
use App\Support\MessengerApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Backs the floating chat widget (see resources/js/components/messaging/ChatWidget.jsx)
 * and the header's Chats dropdown / unread badge -- the full-page inbox this
 * used to serve (list/show/reply/status/delete/Facebook-linking/guest-link
 * management) was replaced by the widget and removed. The unauthenticated
 * guest flow (customer_conversation) is a separate PublicConversationController,
 * kept out of the `auth` middleware group, and is unrelated to this removal.
 */
class MessageController extends Controller
{
    public function unreadCount()
    {
        return response()->json(['count' => MessageThread::unreadCount()]);
    }

    public function widgetShow(Request $request, Customer $customer)
    {
        $this->authorizeWidgetAccess($customer);
        $assignedUserId = $this->resolveAssignedUserId($request, $customer);

        $senderType = MessageThread::recipientSenderType();
        $thread = $this->latestPortalThreadFor($customer, $assignedUserId);

        // A customer can have more than one orphaned (pre-split, or
        // automated-notification) root thread; only the most recently
        // updated one gets claimed into this conversation above. Any others
        // would otherwise stay unread forever -- nothing else can ever reach
        // them now that there's no full-page inbox. Clear those too.
        CustomerMessage::where('customer_id', $customer->id)
            ->whereNull('assigned_user_id')
            ->where('sender_type', $senderType)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if (! $thread) {
            return response()->json([
                'customer' => ['id' => $customer->id, 'company_name' => $customer->company_name],
                'thread' => null,
                'messages' => [],
                'unread_message_ids' => [],
            ]);
        }

        $messages = MessageThread::threadMessages($thread);

        // Snapshot which of the incoming messages were unread *before* this
        // view marks them read, so the client can still show "new since you
        // last looked" (divider, dot) for this one response.
        $unreadMessageIds = $messages
            ->filter(fn (CustomerMessage $m) => $m->sender_type === $senderType && ! $m->is_read)
            ->pluck('id')
            ->values()
            ->all();

        MessageThread::markReceivedMessagesRead($messages, $senderType);

        return response()->json([
            'customer' => ['id' => $customer->id, 'company_name' => $customer->company_name],
            'thread' => $this->widgetThreadPayload($thread),
            'messages' => $this->widgetMessagesPayload($messages),
            'unread_message_ids' => $unreadMessageIds,
        ]);
    }

    public function widgetSend(Request $request, Customer $customer)
    {
        $this->authorizeWidgetAccess($customer);
        $assignedUserId = $this->resolveAssignedUserId($request, $customer);

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Enter a message.']);
        }

        $senderType = Auth::user()->role === 'customer' ? 'customer' : 'company';
        $thread = $this->latestPortalThreadFor($customer, $assignedUserId);

        if ($thread) {
            if ($thread->status === 'closed') {
                $thread->status = 'open';
                $thread->save();
            }
            MessageThread::createReply($thread, $body, $senderType);
        } else {
            $ttlHours = (int) config('services.po_notifications.public_conversation_link_ttl_hours', 720);
            $rawToken = Str::random(43);
            $now = now();

            $thread = CustomerMessage::create([
                'customer_id' => $customer->id,
                'assigned_user_id' => $assignedUserId,
                'subject' => "Conversation with {$customer->company_name}",
                'body' => $body,
                'sender_type' => $senderType,
                'is_read' => false,
                'public_token' => CustomerMessage::hashPublicToken($rawToken),
                'public_token_expires_at' => $now->clone()->addHours($ttlHours),
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            CustomerMessageSent::dispatch($thread->id, $customer->id);
        }

        $messages = MessageThread::threadMessages($thread->fresh());

        return response()->json([
            'thread' => $this->widgetThreadPayload($thread->fresh()),
            'messages' => $this->widgetMessagesPayload($messages),
        ]);
    }

    public function widgetFacebookShow(CustomerMessage $thread)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        abort_unless($thread->isFacebookMessenger(), 404);

        FacebookMessenger::refreshSenderProfile($thread);

        $senderType = MessageThread::recipientSenderType();
        $messages = MessageThread::threadMessages($thread);

        $unreadMessageIds = $messages
            ->filter(fn (CustomerMessage $m) => $m->sender_type === $senderType && ! $m->is_read)
            ->pluck('id')
            ->values()
            ->all();

        MessageThread::markReceivedMessagesRead($messages, $senderType);

        return response()->json([
            'thread' => $this->widgetThreadPayload($thread),
            'messages' => $this->widgetMessagesPayload($messages),
            'unread_message_ids' => $unreadMessageIds,
        ]);
    }

    public function widgetFacebookSend(Request $request, CustomerMessage $thread)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        abort_unless($thread->isFacebookMessenger(), 404);

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Enter a message.']);
        }
        if ($thread->status === 'closed') {
            throw ValidationException::withMessages(['body' => 'Reopen this conversation before replying.']);
        }

        try {
            $externalMessageId = FacebookMessenger::sendReply($thread, $body);
        } catch (MessengerApiException $e) {
            report($e);

            throw ValidationException::withMessages([
                'body' => 'Facebook Messenger is unavailable right now. Try again shortly.',
            ]);
        }

        MessageThread::createReply($thread, $body, 'company', $externalMessageId);

        $messages = MessageThread::threadMessages($thread->fresh());

        return response()->json([
            'thread' => $this->widgetThreadPayload($thread->fresh()),
            'messages' => $this->widgetMessagesPayload($messages),
        ]);
    }

    /**
     * Links (or, with a null user_id, unlinks) a Facebook thread to a staff
     * User -- these Facebook contacts are sales agents, not customers, so
     * App\Support\OrderNotifications can notify "whoever's linked" when an
     * order comes in. Reuses the assigned_user_id column that portal
     * threads use for their own (different) per-staff-member split -- the
     * two meanings never collide since they're read separately per channel.
     * A staff member can only have one linked Facebook thread at a time, so
     * linking a new one displaces whichever thread was linked to them before.
     */
    public function widgetFacebookLink(Request $request, CustomerMessage $thread)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        abort_unless($thread->isFacebookMessenger(), 404);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $userId = $validated['user_id'] ?? null;

        if ($userId !== null) {
            abort_unless(
                User::whereIn('role', ['admin', 'employee'])->whereKey($userId)->exists(),
                422,
            );

            CustomerMessage::whereNull('parent_id')
                ->where('channel', 'facebook_messenger')
                ->where('assigned_user_id', $userId)
                ->where('id', '!=', $thread->id)
                ->update(['assigned_user_id' => null]);
        }

        $thread->assigned_user_id = $userId;
        $thread->save();

        return response()->json([
            'thread' => $this->widgetThreadPayload($thread->fresh()),
        ]);
    }

    public function widgetFacebookRename(Request $request, CustomerMessage $thread)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        abort_unless($thread->isFacebookMessenger(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
        ]);

        $thread->external_sender_name = trim($validated['name']);
        $thread->save();

        return response()->json([
            'thread' => $this->widgetThreadPayload($thread->fresh()),
        ]);
    }

    public function usersSearch(Request $request)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $query = trim((string) $request->query('q', ''));

        $users = User::query()
            ->whereIn('role', ['admin', 'employee'])
            ->where('is_active', true)
            ->when($query !== '', fn ($q) => $q->where('full_name', 'like', '%'.$query.'%'))
            ->orderBy('full_name')
            ->limit(50)
            ->get(['id', 'full_name']);

        return response()->json(['users' => $users]);
    }

    public function recipients()
    {
        $user = Auth::user();

        if ($user->role === 'customer') {
            $customer = CustomerScope::forCurrentUser(required: false);

            if (! $customer) {
                return response()->json(['recipients' => []]);
            }

            $staff = User::query()
                ->whereIn('role', ['admin', 'employee'])
                ->where('is_active', true)
                ->orderByRaw("role = 'admin' desc")
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'role']);

            // Per staff member, not a blanket flag -- each is a separate
            // conversation, so an unread message in Employee Jay's thread
            // shouldn't light up the Administrator row too.
            $unreadStaffIds = CustomerMessage::where('customer_id', $customer->id)
                ->where('sender_type', 'company')
                ->where('is_read', false)
                ->whereIn('assigned_user_id', $staff->pluck('id'))
                ->distinct()
                ->pluck('assigned_user_id')
                ->flip();

            return response()->json([
                'recipients' => $staff->map(fn (User $member) => [
                    'customer' => [
                        'id' => $customer->id,
                        'company_name' => $customer->company_name,
                    ],
                    'user_full_name' => $member->full_name,
                    'contact_id' => $member->id,
                    'contact_role' => $member->role,
                    'has_unread' => $unreadStaffIds->has($member->id),
                ])->all(),
            ]);
        }

        return response()->json([
            'recipients' => array_merge($this->messageRecipients(), $this->facebookRecipients()),
        ]);
    }

    /**
     * Facebook Messenger contacts for the Chats dropdown -- these are
     * existing conversations (a customer can't be "started" on Facebook the
     * way a portal one can; Meta only lets a page reply after the person
     * has messaged it), so this lists threads, not potential recipients.
     */
    private function facebookRecipients(): array
    {
        $senderType = 'customer';

        $threads = CustomerMessage::whereNull('parent_id')
            ->where('channel', 'facebook_messenger')
            ->with('assignedUser:id,full_name')
            ->withExists(['replies as has_unread_replies' => fn ($q) => $q
                ->where('sender_type', $senderType)
                ->where('is_read', false)])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return $threads->map(fn (CustomerMessage $thread) => [
            'channel' => 'facebook',
            'thread_id' => $thread->id,
            'name' => $thread->conversationName(),
            'has_unread' => ($thread->sender_type === $senderType && ! $thread->is_read)
                || (bool) $thread->has_unread_replies,
            'linked_agent_name' => $thread->assignedUser?->full_name,
        ])->all();
    }

    private function rootThreadsQuery()
    {
        $query = CustomerMessage::whereNull('parent_id');
        $customer = CustomerScope::forCurrentUser(required: false);

        if (Auth::user()->role === 'customer') {
            if (! $customer) {
                return $query->whereRaw('1 = 0');
            }
            $query->where('customer_id', $customer->id);
        }

        return $query;
    }

    private function authorizeWidgetAccess(Customer $customer): void
    {
        if (Auth::user()->role === 'customer') {
            $own = CustomerScope::forCurrentUser(required: false);
            abort_unless($own && $own->id === $customer->id, 403);

            return;
        }

        $isRecipient = collect($this->messageRecipients())
            ->contains(fn ($recipient) => $recipient['customer']['id'] === $customer->id);

        abort_unless($isRecipient, 404);
    }

    private function latestPortalThreadFor(Customer $customer, ?int $assignedUserId): ?CustomerMessage
    {
        $notFacebook = fn ($q) => $q->whereNull('channel')->orWhere('channel', '!=', 'facebook_messenger');

        $thread = CustomerMessage::whereNull('parent_id')
            ->where('customer_id', $customer->id)
            ->where('assigned_user_id', $assignedUserId)
            ->where($notFacebook)
            ->orderByDesc('updated_at')
            ->first();

        if ($thread) {
            return $thread;
        }

        // Threads from before conversations were split per staff member, and
        // automated notifications (e.g. "order submitted"), have no assigned
        // staff member yet. Claim the most recent one into this conversation
        // instead of leaving it permanently unreadable by anyone.
        $legacy = CustomerMessage::whereNull('parent_id')
            ->where('customer_id', $customer->id)
            ->whereNull('assigned_user_id')
            ->where($notFacebook)
            ->orderByDesc('updated_at')
            ->first();

        if (! $legacy) {
            return null;
        }

        $legacy->assigned_user_id = $assignedUserId;
        $legacy->replies()->update(['assigned_user_id' => $assignedUserId]);
        $legacy->save();

        return $legacy;
    }

    /**
     * Which staff member a widget conversation belongs to -- Administrator and
     * each Employee get their own thread with a customer. For a staff viewer
     * that's simply themselves; a customer viewer has to say which staff
     * member's conversation they mean, since they're the one choosing from a
     * list of contacts.
     */
    private function resolveAssignedUserId(Request $request, Customer $customer): int
    {
        if (Auth::user()->role !== 'customer') {
            return (int) Auth::id();
        }

        $staffId = (int) $request->input('staff_id');
        abort_unless($staffId > 0, 422, 'Select who you want to message.');

        $isStaff = User::whereIn('role', ['admin', 'employee'])
            ->where('is_active', true)
            ->whereKey($staffId)
            ->exists();
        abort_unless($isStaff, 404);

        return $staffId;
    }

    private function widgetThreadPayload(CustomerMessage $thread): array
    {
        return [
            'id' => $thread->id,
            'name' => $thread->conversationName(),
            'subject' => $thread->subject,
            'status' => $thread->status,
            'assigned_user' => $thread->isFacebookMessenger() && $thread->assignedUser
                ? ['id' => $thread->assignedUser->id, 'full_name' => $thread->assignedUser->full_name]
                : null,
        ];
    }

    private function widgetMessagesPayload($messages): array
    {
        return $messages->map(fn (CustomerMessage $m) => [
            'id' => $m->id,
            'body' => $m->body,
            'sender_type' => $m->sender_type,
            'created_at' => $m->created_at?->toIso8601String(),
            'is_read' => (bool) $m->is_read,
        ])->values()->all();
    }

    private function messageRecipients(): array
    {
        $rows = Customer::query()
            ->join('users', 'users.id', '=', 'customers.user_id')
            ->where('customers.is_active', true)
            ->where('users.is_active', true)
            ->where('users.role', 'customer')
            ->orderBy('users.full_name')
            ->orderBy('customers.company_name')
            ->get(['customers.id as customer_id', 'customers.company_name', 'users.full_name']);

        // Scoped to the viewing staff member's own thread with each customer --
        // conversations are per staff member now, so another staff member's
        // unread message shouldn't light up this viewer's dropdown.
        $unreadCustomerIds = CustomerMessage::where('sender_type', 'customer')
            ->where('is_read', false)
            ->where('assigned_user_id', Auth::id())
            ->whereIn('customer_id', $rows->pluck('customer_id'))
            ->distinct()
            ->pluck('customer_id')
            ->flip();

        return $rows
            ->map(fn ($row) => [
                'customer' => ['id' => $row->customer_id, 'company_name' => $row->company_name],
                'user_full_name' => $row->full_name,
                'has_unread' => $unreadCustomerIds->has($row->customer_id),
            ])
            ->all();
    }
}
