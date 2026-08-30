<x-emails.layout :subject="'Account Reactivated'">
    <h1 style="margin:0 0 16px; font-size:20px; color:#1c1a17;">Your account is active again</h1>
    <p style="margin:0 0 20px; font-size:14px; line-height:1.6; color:#3f3a33;">
        Good news — <strong>{{ $tenant->name }}</strong> has been reactivated. You can sign in and
        pick up right where you left off.
    </p>
    <a href="{{ url('/login') }}" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:14px; font-weight:600;">
        Go to Log In
    </a>
</x-emails.layout>
