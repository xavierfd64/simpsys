<x-emails.layout :subject="'Welcome to '.\App\Models\PlatformSetting::current()->displayName()">
    <h1 style="margin:0 0 16px; font-size:20px; color:#1c1a17;">Welcome, {{ $ownerName }}!</h1>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#3f3a33;">
        Your account for <strong>{{ $tenant->name }}</strong> is ready. You're on a free trial —
        no card required — so you can explore everything right away: point of sale, inventory,
        expenses, reports, and more.
    </p>
    <a href="{{ url('/login') }}" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:14px; font-weight:600;">
        Go to Log In
    </a>
</x-emails.layout>
