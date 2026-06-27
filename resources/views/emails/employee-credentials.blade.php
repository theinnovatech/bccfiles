<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Your {{ $appName }} Account</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0f4fb; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f0f4fb; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #c8d6ef; box-shadow: 0 4px 24px rgba(0, 42, 122, 0.08);">

                    <!-- Gold accent bar -->
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #fcd116 0%, #e5bc00 100%); line-height: 4px; font-size: 4px;">&nbsp;</td>
                    </tr>

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0038a8 0%, #002a7a 55%, #001e5a 100%); padding: 32px 40px; text-align: center;">
                            <img src="{{ rtrim($appUrl, '/') }}/images/logo2.png" alt="{{ $appName }}" width="64" height="64" style="display: block; margin: 0 auto 16px; border-radius: 8px; background: #ffffff; padding: 4px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.02em;">{{ $appName }}</h1>
                            <p style="margin: 6px 0 0; color: #d6e0f5; font-size: 13px; font-weight: 500;">Iriga City Division Supply Unit Inventory</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 36px 40px 28px;">
                            <p style="margin: 0 0 8px; font-size: 15px; color: #5b7fbf; font-weight: 500;">Welcome,</p>
                            <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: #002a7a; line-height: 1.3;">
                                {{ $employeeName }}
                            </h2>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #001e5a;">
                                Your account has been created. Use the credentials below to sign in to the system.
                            </p>

                            <!-- Credentials card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #eef2fa; border: 1px solid #c8d6ef; border-radius: 10px; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #5b7fbf;">
                                            Login Credentials
                                        </p>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #d6e0f5;">
                                                    <span style="display: block; font-size: 12px; color: #5b7fbf; margin-bottom: 4px;">Login Email</span>
                                                    <span style="font-size: 15px; font-weight: 600; color: #002a7a;">{{ $loginEmail }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 10px 0;">
                                                    <span style="display: block; font-size: 12px; color: #5b7fbf; margin-bottom: 4px;">Password</span>
                                                    <span style="font-size: 15px; font-weight: 600; color: #002a7a; font-family: 'Courier New', Courier, monospace; background: #ffffff; padding: 6px 10px; border-radius: 6px; border: 1px solid #c8d6ef; display: inline-block;">{{ $plainPassword }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security notice -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #fffbeb; border: 1px solid #fcd116; border-radius: 8px; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 14px 18px;">
                                        <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #001e5a;">
                                            <strong style="color: #002a7a;">Security tip:</strong> Please log in and change your password as soon as possible. Do not share these credentials with anyone.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 8px;">
                                        <a href="{{ $appUrl }}" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #0038a8 0%, #002a7a 100%); color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 36px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 56, 168, 0.35);">
                                            Log In to {{ $appName }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #eef2fa; border-top: 1px solid #c8d6ef; padding: 24px 40px; text-align: center;">
                            <p style="margin: 0 0 6px; font-size: 13px; color: #5b7fbf;">
                                If you did not expect this email, contact your system administrator.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #5b7fbf;">
                                &copy; {{ date('Y') }} {{ $appName }} &mdash; Iriga City Division Supply Unit Inventory
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
