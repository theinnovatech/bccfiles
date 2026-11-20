<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Password reset</title>
</head>
<body style="margin:0;padding:0;background:#eef2f9;font-family:Arial,Helvetica,sans-serif;color:#00164d;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f9;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#001f6b;padding:18px 24px;">
                            <div style="height:4px;width:40px;background:#fcd116;border-radius:2px;margin-bottom:10px;"></div>
                            <div style="color:#ffffff;font-size:18px;font-weight:bold;">OBIMS</div>
                            <div style="color:#c5d0e8;font-size:12px;">{{ $organizationName }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 12px;font-size:15px;">Hello {{ $user->name }},</p>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.5;">
                                We received a request to reset the password of your OBIMS account
                                (<strong>{{ $user->email }}</strong>). Enter this code on the reset page:
                            </p>
                            <div style="text-align:center;margin:0 0 18px;">
                                <span style="display:inline-block;font-size:30px;letter-spacing:8px;font-weight:bold;background:#f4f7fb;border:1px solid #c9d4e6;border-radius:8px;padding:12px 20px;font-family:'Courier New',monospace;">{{ $code }}</span>
                            </div>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.5;">Or open this link to choose a new password:</p>
                            <p style="text-align:center;margin:0 0 18px;">
                                <a href="{{ $link }}" style="display:inline-block;background:#fcd116;color:#000f33;font-weight:bold;text-decoration:none;padding:11px 22px;border-radius:8px;font-size:14px;">Reset my password</a>
                            </p>
                            <p style="margin:0 0 8px;font-size:13px;color:#4a6490;line-height:1.5;">
                                The code and the link expire in {{ $minutes }} minutes and work only once.
                            </p>
                            <p style="margin:0;font-size:13px;color:#4a6490;line-height:1.5;">
                                If you did not ask to reset your password, ignore this email. Your password stays the same.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 24px;border-top:1px solid #e2e8f2;font-size:11px;color:#8fa7db;">
                            If the button does not work, copy this address into your browser:<br>
                            <span style="word-break:break-all;">{{ $link }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
