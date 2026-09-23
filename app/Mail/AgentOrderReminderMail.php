<?php

namespace App\Mail;

use App\Models\OrderFollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgentOrderReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly OrderFollowUp $followUp,
        public readonly string $message,
    ) {
        $followUp->loadMissing('purchaseOrder');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: order {$this->followUp->purchaseOrder->po_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.orders.agent-reminder',
            with: [
                'order' => $this->followUp->purchaseOrder,
                'message' => $this->message,
            ],
        );
    }
}
