<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Request update | CampusDesk</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; color:#1e293b; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f1f5f9;">
        <tr><td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px;">
                <tr><td style="padding:0 8px 16px; color:#334155; font-size:14px; font-weight:700; letter-spacing:0.2px;"><span style="display:inline-block; width:10px; height:10px; margin-right:8px; border-radius:50%; background-color:#475569;"></span>CampusDesk</td></tr>
                <tr><td style="overflow:hidden; background-color:#ffffff; border:1px solid #e2e8f0; border-radius:14px;">
                    <div style="height:6px; background-color:#475569; font-size:0; line-height:0;">&nbsp;</div>
                    <div style="padding:32px 32px 28px;">
                        <p style="margin:0 0 8px; color:#64748b; font-size:14px; line-height:21px;">REQUEST STATUS UPDATE</p>
                        <h1 style="margin:0 0 16px; color:#0f172a; font-size:26px; line-height:34px; font-weight:700;">Hello, {{ $user->name }}</h1>
                        <p style="margin:0; color:#475569; font-size:16px; line-height:25px;">There is a new update on your <strong style="color:#1e293b;">{{ $documentRequest->requestType->name }}</strong> request.</p>

                        <div style="margin:24px 0; padding:20px; background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
                            <p style="margin:0 0 10px; color:#64748b; font-size:12px; font-weight:700; letter-spacing:0.6px;">CURRENT STATUS</p>
                            <span style="display:inline-block; padding:6px 10px; border-radius:999px; background-color:{{ $statusBackground }}; color:{{ $statusColor }}; font-size:13px; font-weight:700;">{{ $statusLabel }}</span>
                            <p style="margin:14px 0 0; color:#334155; font-size:16px; line-height:24px;">{{ $statusMessage }}</p>
                        </div>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0;">
                            <tr><td style="padding:13px 0; color:#64748b; font-size:14px;">Request reference</td><td align="right" style="padding:13px 0; color:#1e293b; font-size:14px; font-weight:700;">{{ $requestReference }}</td></tr>
                            <tr><td style="padding:0 0 13px; color:#64748b; font-size:14px;">Request type</td><td align="right" style="padding:0 0 13px; color:#1e293b; font-size:14px; font-weight:700;">{{ $documentRequest->requestType->name }}</td></tr>
                        </table>

                        @if ($staffNote)
                            <div style="margin-top:24px; padding:16px; border-left:4px solid {{ $statusColor }}; background-color:#f8fafc;">
                                <p style="margin:0 0 6px; color:#475569; font-size:13px; font-weight:700;">NOTE FROM THE PROCESSING TEAM</p>
                                <p style="margin:0; color:#334155; font-size:15px; line-height:23px; white-space:pre-line;">{{ $staffNote }}</p>
                            </div>
                        @endif

                        <p style="margin:24px 0 0; color:#475569; font-size:15px; line-height:23px;">{{ $nextStep }}</p>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:24px;"><tr><td style="border-radius:8px; background-color:#475569;"><a href="{{ $dashboardUrl }}" style="display:inline-block; padding:12px 18px; color:#ffffff; font-size:15px; font-weight:700; line-height:20px; text-decoration:none;">View request in CampusDesk</a></td></tr></table>
                    </div>
                </td></tr>
                <tr><td style="padding:18px 12px 0; color:#64748b; font-size:12px; line-height:18px; text-align:center;">This is an automated update from CampusDesk. Please do not reply to this email.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
