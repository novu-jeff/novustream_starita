<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application received</title>
</head>
<body style="margin:0;padding:0;background:#eef3f8;font-family:Arial,Helvetica,sans-serif;color:#1c2833;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3f8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#0b4f9c;padding:28px 32px 24px;text-align:center;">
                            <img src="{{ $message->embed(public_path('images/client1nobg.png')) }}" alt="Sta. Rita Water District" width="96" height="96" style="display:block;margin:0 auto 12px;border:0;">
                            <p style="margin:0;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#d6e6f7;">Republic of the Philippines</p>
                            <h1 style="margin:6px 0 0;font-size:22px;line-height:1.3;color:#ffffff;">Sta. Rita Water District</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 8px;font-size:13px;font-weight:bold;letter-spacing:.4px;text-transform:uppercase;color:#0b4f9c;">Application update</p>
                            <h2 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#16324f;">We received your application</h2>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Good day, {{ $applicantName }}.</p>
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Your concessionaire application has been received and is now <strong>in review</strong>. Our staff will check the details you submitted. You do not need to send this application again.</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;background:#f4f8fc;border:1px solid #d7e4f2;border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 18px;font-size:14px;line-height:1.7;color:#1c2833;">
                                        <strong style="color:#0b4f9c;">Application details</strong><br>
                                        Name: {{ $applicantName }}<br>
                                        Email: {{ $email }}<br>
                                        Account no.: {{ $accountNo }}<br>
                                        Service address: {{ $address }}<br>
                                        Status: Received — In review<br>
                                        Date received: {{ $receivedOn }}
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 8px;font-size:16px;line-height:1.6;"><strong>What happens next</strong></p>
                            <p style="margin:0 0 8px;font-size:15px;line-height:1.6;">1. The office reviews your account details and supporting documents.</p>
                            <p style="margin:0 0 8px;font-size:15px;line-height:1.6;">2. We will email you when the application is approved, or if more information is needed.</p>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">3. After approval, sign in to the portal to view your bills and payments.</p>
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background:#0b4f9c;border-radius:6px;">
                                        <a href="{{ $portalUrl }}" style="display:inline-block;padding:12px 22px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Open the portal</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#102033;padding:22px 32px;text-align:center;">
                            <img src="{{ $message->embed(public_path('images/novustreamlogo.png')) }}" alt="NovuStream" width="140" style="display:block;margin:0 auto 12px;border:0;">
                            <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:#d5deea;">Zone 6 Dila-Dila, Santa Rita, Pampanga</p>
                            <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:#d5deea;">Office hours: 8:00 AM – 5:00 PM</p>
                            <p style="margin:0;font-size:13px;line-height:1.5;color:#d5deea;">0917-103-2421 · 0917-104-7196</p>
                            <p style="margin:12px 0 0;font-size:12px;line-height:1.5;color:#8ea0b5;">This message was sent by Sta. Rita Water District. Please do not reply to this email.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
