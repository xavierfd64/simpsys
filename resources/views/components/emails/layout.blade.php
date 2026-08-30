@php
    $platform = \App\Models\PlatformSetting::current();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $subject ?? $platform->displayName() }}</title>
</head>
<body style="margin:0; padding:0; background:#f8f7f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1c1a17;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f7f5; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #ece7e0;">
                <tr>
                    <td style="padding:24px 32px; border-bottom:1px solid #ece7e0;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                @if ($platform->logo_path)
                                    <td style="padding-right:10px;">
                                        <img src="{{ \App\Support\TenantStorage::url($platform->logo_path) }}" alt="{{ $platform->displayName() }}" width="28" height="28" style="border-radius:6px; display:block;">
                                    </td>
                                @endif
                                <td>
                                    <span style="font-size:18px; font-weight:600; color:#1c1a17;">{{ $platform->displayName() }}</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        {{ $slot }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px; border-top:1px solid #ece7e0; font-size:12px; color:#a39a8c;">
                        <p style="margin:0 0 4px;">&copy; {{ now()->year }} {{ $platform->displayName() }}. All rights reserved.</p>
                        @if ($platform->support_email || $platform->support_phone)
                            <p style="margin:0;">
                                @if ($platform->support_email)
                                    Need help? <a href="mailto:{{ $platform->support_email }}" style="color:#2563eb;">{{ $platform->support_email }}</a>
                                @endif
                                @if ($platform->support_phone)
                                    {{ $platform->support_email ? ' · ' : '' }}{{ $platform->support_phone }}
                                @endif
                            </p>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
