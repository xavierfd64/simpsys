<x-emails.layout :subject="'Upcoming Renewal'">
    <h1 style="margin:0 0 16px; font-size:20px; color:#1c1a17;">Your subscription renews soon</h1>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#3f3a33;">
        <strong>{{ $statement->subscription->tenant->name }}</strong>'s
        {{ $statement->subscription->plan->name }} subscription renews on
        <strong>{{ $statement->subscription->current_period_end->format('M j, Y') }}</strong>.
    </p>
    <div style="margin:0 0 20px; padding:14px 16px; background:#f8f7f5; border-radius:8px; font-size:14px; color:#3f3a33;">
        Amount due: <strong>₱{{ number_format($statement->balance) }}</strong>
    </div>
    <a href="{{ url('/app/billing') }}" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:14px; font-weight:600;">
        View Statement
    </a>
</x-emails.layout>
