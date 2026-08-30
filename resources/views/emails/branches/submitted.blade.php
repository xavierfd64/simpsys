<x-emails.layout :subject="'Branch Submitted for Approval'">
    <h1 style="margin:0 0 16px; font-size:20px; color:#1c1a17;">Branch submitted for approval</h1>
    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#3f3a33;">
        Your new branch <strong>{{ $branch->name }}</strong> has been submitted and is now
        <strong>pending Platform Admin approval</strong>. You'll receive another email as soon as
        it's reviewed — this usually only takes a short while.
    </p>
    <p style="margin:0; font-size:14px; line-height:1.6; color:#3f3a33;">
        The branch won't be usable for sign-in until it's approved.
    </p>
</x-emails.layout>
