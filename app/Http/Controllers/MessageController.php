<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Support\CustomerScope;
use App\Support\FacebookMessenger;
use App\Support\MessageThread;
use App\Support\MessengerApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ports app/messages/message_routes.py's authenticated routes
 * (message_list, message_create, message_view, message_customer_link,
 * message_sender_name, message_public_link, message_reply,
 * message_status, message_delete, message_unread_count). The
 * unauthenticated guest flow (customer_conversation) is a separate
 * PublicConversationController, kept out of the `auth` middleware group.
 */
class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = strtolower(trim((string) $request->query('status', 'all'))) ?: 'all';
        $senderType = MessageThread::recipientSenderType();

        $query = $this->rootThreadsQuery()
            ->with(['customer', 'latestReply'])
            ->withCount('replies')
            ->withExists(['replies as has_unread_replies' => fn ($q) => $q
                ->where('sender_type', $senderType)
                ->where('is_read', false)]);

        if ($statusFilter === 'open' || $statusFilter === 'closed') {
            $query->where('status', $statusFilter);
        } elseif ($statusFilter === 'unread') {
            $query->where(function ($q) use ($senderType) {
                $q->where(function ($q2) use ($senderType) {
                    $q2->where('sender_type', $senderType)->where('is_read', false);
                })->orWhereHas('replies', function ($q2) use ($senderType) {
                    $q2->where('sender_type', $senderType)->where('is_read', false);
                });
            });
        } else {
            $statusFilter = 'all';
        }

        $threads = $query->orderByDesc('updated_at')->paginate(25)->withQueryString();

        $threads->through(function (CustomerMessage $thread) use ($senderType) {
            $latest = $thread->latestReply ?? $thread;

            return [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'customer_name' => $thread->conversationName(),
                'is_facebook' => $thread->isFacebookMessenger(),
                'status' => $thread->status,
                'updated_at' => $thread->updated_at?->toIso8601String(),
                'reply_count' => (int) $thread->replies_count,
                'latest_preview' => $latest ? Str::limit($latest->body, 120) : null,
                'latest_sender_type' => $latest?->sender_type,
                'has_unread' => ($thread->sender_type === $senderType && ! $thread->is_read)
                    || (bool) $thread->has_unread_replies,
            ];
        });

        return Inertia::render('Messages/Index', [
            'threads' => $threads,
            'filters' => ['status' => $statusFilter],
        ]);
    }

    public function create(Request $request): Response
    {
        $linkedCustomer = CustomerScope::forCurrentUser(required: false);
        $recipients = $linkedCustomer ? [] : $this->messageRecipients();

        return Inertia::render('Messages/Create', [
            'recipients' => $recipients,
            'linkedCustomer' => $linkedCustomer ? ['id' => $linkedCustomer->id, 'company_name' => $linkedCustomer->company_name] : null,
            'selectedCustomerId' => $linkedCustomer?->id ?? ($request->query('customer_id') ? (int) $request->query('customer_id') : null),
        ]);
    }

    public function store(Request $request)
    {
        $linkedCustomer = CustomerScope::forCurrentUser(required: false);
        $recipients = $linkedCustomer ? [] : $this->messageRecipients();
        $recipientIds = collect($recipients)->pluck('customer.id');

        $customer = $linkedCustomer ?? Customer::find($request->input('customer_id'));

        if (! $customer || (! $linkedCustomer && ! $recipientIds->contains($customer->id))) {
            throw ValidationException::withMessages([
                'customer_id' => 'Select a customer with an active linked portal account.',
            ]);
        }

        $subject = trim((string) $request->input('subject', ''));
        $body = trim((string) $request->input('body', ''));
        $missingFields = [];
        if ($subject === '') {
            $missingFields['subject'] = 'Enter a subject.';
        }
        if ($body === '') {
            $missingFields['body'] = 'Enter a message.';
        }
        if ($missingFields !== []) {
            throw ValidationException::withMessages($missingFields);
        }

        $ttlHours = (int) config('services.po_notifications.public_conversation_link_ttl_hours', 720);
        $rawToken = Str::random(43);
        $now = now();

        $message = CustomerMessage::create([
            'customer_id' => $customer->id,
            'subject' => $subject,
            'body' => $body,
            'sender_type' => Auth::user()->role === 'customer' ? 'customer' : 'company',
            'is_read' => false,
            'public_token' => CustomerMessage::hashPublicToken($rawToken),
            'public_token_expires_at' => $now->clone()->addHours($ttlHours),
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $link = route('messages.customer-conversation', ['token' => $rawToken]);

        return redirect()->route('messages.show', $message->id)
            ->with('success', 'Conversation started.')
            ->with('link', "Customer link (copy it now -- it won't be shown again; use \"Rotate customer link\" to get a new one later): {$link}");
    }

    public function show(CustomerMessage $thread): Response
    {
        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        $this->authorizeThreadAccess($thread);

        FacebookMessenger::refreshSenderProfile($thread);

        $messages = MessageThread::threadMessages($thread);
        MessageThread::markReceivedMessagesRead($messages, MessageThread::recipientSenderType());

        $isCustomerViewer = Auth::user()->role === 'customer';

        $facebookCustomers = [];
        if (! $isCustomerViewer && $thread->isFacebookMessenger()) {
            $facebookCustomers = Customer::where('is_active', true)->orderBy('company_name')->get(['id', 'company_name']);
        }

        return Inertia::render('Messages/Show', [
            'thread' => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'status' => $thread->status,
                'is_facebook' => $thread->isFacebookMessenger(),
                'customer_name' => $thread->conversationName(),
                'external_sender_name' => $thread->external_sender_name,
                'public_link_active' => $thread->publicLinkIsActive(),
                'customer_id' => $thread->customer_id,
            ],
            'messages' => $messages->map(fn (CustomerMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'sender_type' => $m->sender_type,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values(),
            'isCustomerViewer' => $isCustomerViewer,
            'facebookCustomers' => $facebookCustomers,
        ]);
    }

    public function reply(Request $request, CustomerMessage $thread)
    {
        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        $this->authorizeThreadAccess($thread);

        $body = trim((string) $request->input('body', ''));

        if ($body === '') {
            return back()->with('error', 'Enter a reply before sending.');
        }
        if ($thread->status === 'closed') {
            return back()->with('error', 'Reopen this conversation before replying.');
        }

        $senderType = Auth::user()->role === 'customer' ? 'customer' : 'company';

        if ($thread->isFacebookMessenger()) {
            abort_if(Auth::user()->role === 'customer', 403);
            try {
                $externalMessageId = FacebookMessenger::sendReply($thread, $body);
            } catch (MessengerApiException $e) {
                report($e);

                return redirect()->route('messages.show', $thread->id)
                    ->with('error', 'Facebook Messenger is unavailable. Send the reply through Messenger or contact the system administrator.');
            }
            MessageThread::createReply($thread, $body, $senderType, $externalMessageId);

            return redirect()->route('messages.show', $thread->id)->with('success', 'Reply sent through Facebook Messenger.');
        }

        MessageThread::createReply($thread, $body, $senderType);

        return redirect()->route('messages.show', $thread->id)->with('success', 'Reply added to the conversation.');
    }

    public function status(Request $request, CustomerMessage $thread)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        $thread->status = $request->input('status') === 'closed' ? 'closed' : 'open';
        $thread->updated_at = now();
        $thread->save();

        return redirect()->route('messages.show', $thread->id)->with('success', "Conversation marked {$thread->status}.");
    }

    public function destroy(Request $request, CustomerMessage $thread)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();

        if ($thread->status !== 'closed') {
            return redirect()->route('messages.show', $thread->id)->with('error', 'Only closed conversations can be deleted.');
        }

        $returnStatus = strtolower((string) $request->input('return_status', 'all'));
        if (! in_array($returnStatus, ['all', 'unread', 'open', 'closed'], true)) {
            $returnStatus = 'all';
        }

        DB::transaction(function () use ($thread) {
            $thread->replies()->delete();
            $thread->delete();
        });

        return redirect()->route('messages.index', ['status' => $returnStatus])->with('success', 'Closed conversation deleted.');
    }

    public function customerLink(Request $request, CustomerMessage $thread)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        abort_if(! $thread->isFacebookMessenger(), 404);

        $customerId = $request->input('customer_id') ? (int) $request->input('customer_id') : null;
        $customer = $customerId ? Customer::find($customerId) : null;

        if ($customerId && (! $customer || ! $customer->is_active)) {
            return redirect()->route('messages.show', $thread->id)->with('error', 'Select an active customer to link this Facebook conversation.');
        }

        DB::transaction(function () use ($thread, $customer) {
            $displaced = [];
            if ($customer) {
                $displaced = CustomerMessage::whereNull('parent_id')
                    ->where('channel', 'facebook_messenger')
                    ->where('customer_id', $customer->id)
                    ->where('id', '!=', $thread->id)
                    ->get();

                foreach ($displaced as $otherThread) {
                    $otherThread->customer_id = null;
                    $otherThread->updated_at = now();
                    $otherThread->save();
                }
            }

            $thread->customer_id = $customer?->id;
            $thread->updated_at = now();
            $thread->save();
        });

        if ($customer) {
            return redirect()->route('messages.show', $thread->id)->with('success', "Facebook conversation linked to {$customer->company_name}. Future orders will receive a Messenger summary.");
        }

        return redirect()->route('messages.show', $thread->id)->with('success', 'Facebook conversation is no longer linked to a customer.');
    }

    public function senderName(Request $request, CustomerMessage $thread)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        abort_if(! $thread->isFacebookMessenger(), 404);

        $senderName = trim((string) $request->input('sender_name', ''));
        $senderName = $senderName === '' ? null : substr($senderName, 0, 200);

        $thread->external_sender_name = $senderName;
        $thread->updated_at = now();
        $thread->save();

        $message = $senderName
            ? "Display name set to \"{$senderName}\"."
            : 'Display name cleared. It will show as a Facebook customer ID again until a name is set or Facebook\'s API resolves one.';

        return redirect()->route('messages.show', $thread->id)->with('success', $message);
    }

    public function publicLink(Request $request, CustomerMessage $thread)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $thread = $this->rootThreadsQuery()->whereKey($thread->id)->firstOrFail();
        abort_if($thread->isFacebookMessenger(), 404);

        $action = $request->input('action');

        if ($action === 'revoke') {
            $thread->public_token_revoked_at = now();
            $thread->save();

            return redirect()->route('messages.show', $thread->id)->with('success', 'Customer conversation link revoked.');
        }

        if ($action === 'rotate') {
            $ttlHours = (int) config('services.po_notifications.public_conversation_link_ttl_hours', 720);
            $rawToken = Str::random(43);
            $thread->public_token = CustomerMessage::hashPublicToken($rawToken);
            $thread->public_token_expires_at = now()->addHours($ttlHours);
            $thread->public_token_revoked_at = null;
            $thread->save();

            $link = route('messages.customer-conversation', ['token' => $rawToken]);

            return redirect()->route('messages.show', $thread->id)
                ->with('success', 'New customer conversation link created. The previous link no longer works.')
                ->with('link', "Customer link (copy it now -- it won't be shown again): {$link}");
        }

        abort(400);
    }

    public function unreadCount()
    {
        return response()->json(['count' => MessageThread::unreadCount()]);
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

    private function authorizeThreadAccess(CustomerMessage $thread): void
    {
        if (Auth::user()->role !== 'customer') {
            return;
        }
        $customer = CustomerScope::forCurrentUser(required: false);
        if (! $customer || $thread->customer_id !== $customer->id) {
            abort(403);
        }
    }

    private function messageRecipients(): array
    {
        return Customer::query()
            ->join('users', 'users.id', '=', 'customers.user_id')
            ->where('customers.is_active', true)
            ->where('users.is_active', true)
            ->where('users.role', 'customer')
            ->orderBy('users.full_name')
            ->orderBy('customers.company_name')
            ->get(['customers.id as customer_id', 'customers.company_name', 'users.full_name'])
            ->map(fn ($row) => [
                'customer' => ['id' => $row->customer_id, 'company_name' => $row->company_name],
                'user_full_name' => $row->full_name,
            ])
            ->all();
    }
}
