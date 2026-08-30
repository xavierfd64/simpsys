<x-emails.layout :subject="'Reset Your Password'">
    <h1 style="margin:0 0 16px; font-size:20px; color:#1c1a17;">Reset your password</h1>
    <p style="margin:0 0 20px; font-size:14px; line-height:1.6; color:#3f3a33;">
        You're receiving this email because we received a password reset request for your account.
        This link expires in 60 minutes.
    </p>
    <a href="{{ $resetUrl }}" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:14px; font-weight:600;">
        Reset Password
    </a>
    <p style="margin:20px 0 0; font-size:13px; line-height:1.6; color:#a39a8c;">
        If you did not request a password reset, no further action is required.
    </p>
</x-emails.layout>
