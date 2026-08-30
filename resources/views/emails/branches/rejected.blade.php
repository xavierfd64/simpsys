<x-emails.layout :subject="'Branch Not Approved'">
    <h1 style="margin:0 0 16px; font-size:20px; color:#1c1a17;">Branch not approved</h1>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#3f3a33;">
        Your branch <strong>{{ $branch->name }}</strong> was not approved.
    </p>
    <div style="margin:0 0 16px; padding:12px 16px; background:#fdf6f2; border-radius:8px; font-size:14px; line-height:1.6; color:#3f3a33;">
        <strong>Reason:</strong> {{ $reason }}
    </div>
    <p style="margin:0; font-size:14px; line-height:1.6; color:#3f3a33;">
        Contact support if you have questions or would like to resubmit.
    </p>
</x-emails.layout>
