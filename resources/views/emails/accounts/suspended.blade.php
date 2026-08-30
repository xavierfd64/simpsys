<x-emails.layout :subject="'Account Suspended'">
    <h1 style="margin:0 0 16px; font-size:20px; color:#1c1a17;">Your account has been suspended</h1>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#3f3a33;">
        <strong>{{ $tenant->name }}</strong> has been suspended and sign-in is temporarily disabled.
        If you believe this is a mistake, please contact support.
    </p>
</x-emails.layout>
