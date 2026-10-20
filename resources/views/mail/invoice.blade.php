@php
    $seller = $invoice->seller_snapshot ?? [];
@endphp
<x-mail::message>
{{ $recipientName ? "Dear {$recipientName}," : 'Hello,' }}

Please find attached invoice **{{ $invoice->number }}** for **{{ \App\Support\Money::format($invoice->balanceDue()) }}**, due by **{{ $invoice->due_date->format('d M Y') }}**.

@if (filled($seller['upi_id'] ?? null))
You can pay by UPI to **{{ $seller['upi_id'] }}** or scan the QR code on the invoice. Bank details are on the invoice too.
@endif

Thank you,<br>
{{ $seller['trade_name'] ?? $seller['legal_name'] ?? config('app.name') }}
</x-mail::message>
