<x-emails.layout :subject="'Payment Received'">
    <h1 style="margin:0 0 16px; font-size:20px; color:#1c1a17;">Payment received — thank you!</h1>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#3f3a33;">
        We've recorded your payment of <strong>₱{{ number_format($payment->amount) }}</strong> for
        <strong>{{ $payment->tenant->name }}</strong>
        @if ($payment->reference)
            (ref: {{ $payment->reference }})
        @endif
        . Your subscription has been renewed.
    </p>
</x-emails.layout>
