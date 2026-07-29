<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $appName }} Employee Registration</title>
</head>
<body style="margin: 0; padding: 0; background-color: #e8edf5; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #e8edf5; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #a8b8d4; box-shadow: 0 4px 24px rgba(0, 42, 122, 0.08);">
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #fcd116 0%, #e5bc00 100%); line-height: 4px; font-size: 4px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="background: linear-gradient(135deg, #001f6b 0%, #00164d 55%, #000f33 100%); padding: 32px 40px; text-align: center;">
                            <img src="{{ rtrim($appUrl, '/') }}/images/logo2.png" alt="{{ $appName }}" width="64" height="64" style="display: block; margin: 0 auto 16px; border-radius: 8px; background: #ffffff; padding: 4px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.02em;">{{ $appName }}</h1>
                            <p style="margin: 6px 0 0; color: #c5d0e8; font-size: 13px; font-weight: 500;">Iriga City Division Supply Unit Inventory</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px 40px 28px;">
                            <p style="margin: 0 0 8px; font-size: 15px; color: #4a6490; font-weight: 500;">Hello,</p>
                            <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: #00164d; line-height: 1.3;">
                                {{ $employeeName }}
                            </h2>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #000f33;">
                                Your employee record has been registered in OBIMS. Please keep this information for reference when receiving supplies or equipment.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #e2e8f2; border: 1px solid #a8b8d4; border-radius: 10px; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #4a6490;">
                                            Employee Details
                                        </p>
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #c5d0e8;">
                                                    <span style="display: block; font-size: 12px; color: #4a6490; margin-bottom: 4px;">Employee No.</span>
                                                    <span style="font-size: 15px; font-weight: 600; color: #00164d;">{{ $employeeNumber }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #c5d0e8;">
                                                    <span style="display: block; font-size: 12px; color: #4a6490; margin-bottom: 4px;">Department</span>
                                                    <span style="font-size: 15px; font-weight: 600; color: #00164d;">{{ $departmentName }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 10px 0; border-bottom: 1px solid #c5d0e8;">
                                                    <span style="display: block; font-size: 12px; color: #4a6490; margin-bottom: 4px;">Position</span>
                                                    <span style="font-size: 15px; font-weight: 600; color: #00164d;">{{ $position }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 10px 0;">
                                                    <span style="display: block; font-size: 12px; color: #4a6490; margin-bottom: 4px;">Contact Email</span>
                                                    <span style="font-size: 15px; font-weight: 600; color: #00164d;">{{ $contactEmail }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #4a6490;">
                                If you did not expect this email, contact your system administrator.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #e2e8f2; border-top: 1px solid #a8b8d4; padding: 24px 40px; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #4a6490;">
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
