<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order)
    {
        $order->loadMissing(['customer', 'items']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order {$this->order->po_number} submitted",
        );
    }

    public function content(): Content
    {
        $itemLines = $this->order->items->map(function ($item) {
            $details = collect([$item->generic_name, $item->dosage])->filter()->implode(', ');
            $name = $details === '' ? $item->product_name : "{$item->product_name} ({$details})";

            return "{$name} \u{2014} {$item->quantity} {$item->unit}";
        });

        return new Content(
            text: 'emails.orders.submitted',
            with: [
                'order' => $this->order,
                'itemLines' => $itemLines,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
