<x-emails.layout :subject="'Payment Overdue'">
    <h1 style="margin:0 0 16px; font-size:20px; color:#1c1a17;">Your subscription has expired</h1>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#3f3a33;">
        <strong>{{ $tenant->name }}</strong>'s subscription period ended without a recorded
        payment, so it's now marked expired. Please contact support to renew and restore access.
    </p>
</x-emails.layout>
