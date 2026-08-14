<?php

namespace App\Http\Controllers;

use App\Models\CustomerMessage;
use App\Models\PublicConversationReplyAttempt;
use App\Support\MessageThread;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Ports app/messages/message_routes.py's customer_conversation route --
 * the token-gated guest view/reply flow, deliberately NOT under the
 * `auth` middleware (see routes/web.php).
 */
class PublicConversationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $thread = $this->findThreadOrFail($token);

        $messages = MessageThread::threadMessages($thread);
        MessageThread::markReceivedMessagesRead($messages, 'company');

        return Inertia::render('PublicConversation/Show', [
            'token' => $token,
            'thread' => [
                'subject' => $thread->subject,
                'status' => $thread->status,
            ],
            'messages' => $messages->map(fn (CustomerMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'sender_type' => $m->sender_type,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function reply(Request $request, string $token)
    {
        $thread = $this->findThreadOrFail($token);

        if (! $this->publicReplyIsAllowed($thread, $request)) {
            abort(429, 'Too many reply attempts. Please try again later.');
        }

        $body = trim((string) $request->input('body', ''));
        $maxLength = (int) config('services.messages.public_reply_max_length', 2000);

        if ($body === '') {
            return back()->with('error', 'Enter a reply before sending.');
        }
        if (strlen($body) > $maxLength) {
            return back()->with('error', 'Your reply is too long.');
        }
        if ($thread->status === 'closed') {
            return back()->with('error', 'This conversation is closed.');
        }

        MessageThread::createReply($thread, $body, 'customer');

        return redirect()->route('messages.customer-conversation', ['token' => $token])
            ->with('success', 'Your reply was added.');
    }

    private function findThreadOrFail(string $token): CustomerMessage
    {
        $thread = CustomerMessage::whereNull('parent_id')
            ->where('channel', 'portal')
            ->where('public_token', CustomerMessage::hashPublicToken($token))
            ->first();

        if (! $thread || ! $thread->publicLinkIsActive()) {
            throw new HttpException(404);
        }

        return $thread;
    }

    private function publicReplyIsAllowed(CustomerMessage $thread, Request $request): bool
    {
        $windowSeconds = (int) config('services.messages.public_reply_window_seconds', 900);
        $limit = (int) config('services.messages.public_reply_limit', 5);
        $cutoff = now()->subSeconds($windowSeconds);

        $clientKey = hash_hmac('sha256', "{$thread->id}:{$request->ip()}", config('app.key'));

        $attempts = PublicConversationReplyAttempt::where('customer_message_id', $thread->id)
            ->where('client_key', $clientKey)
            ->where('attempted_at', '>=', $cutoff)
            ->count();

        if ($attempts >= $limit) {
            return false;
        }

        PublicConversationReplyAttempt::create([
            'customer_message_id' => $thread->id,
            'client_key' => $clientKey,
            'attempted_at' => now(),
        ]);

        return true;
    }
}
