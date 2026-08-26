Hi {!! $order->customer?->company_name !!},

Thank you — your order has been submitted successfully.

PO Number: {!! $order->po_number !!}

Items:
@foreach ($itemLines as $index => $line)
{{ $index + 1 }}. {!! $line !!}
@endforeach
Our team has been notified and will review your order shortly. You'll receive another update once your order is processed.

— {!! config('app.name') !!}
