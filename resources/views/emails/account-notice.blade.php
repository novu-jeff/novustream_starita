<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }}</title>
</head>
<body style="margin:0;padding:0;background:#e8eef5;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#e8eef5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:22px 28px;background:#ffffff;border-bottom:4px solid #004aad;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="left" valign="middle" style="width:50%;">
                                        <img src="{{ $message->embed(public_path('images/client1nobg.png')) }}" alt="Sta. Rita Water District" height="78" style="display:block;height:78px;width:auto;border:0;">
                                    </td>
                                    <td align="right" valign="middle" style="width:50%;">
                                        <img src="{{ $message->embed(public_path('images/novustreamlogo.png')) }}" alt="NovuStream" height="58" style="display:block;height:58px;width:auto;margin-left:auto;border:0;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 8px;font-size:22px;line-height:1.3;font-weight:700;color:#0b2540;">
                            {{ $headline }}
                        </td>
                    </tr>
                    @if(!empty($greeting))
                    <tr>
                        <td style="padding:8px 28px 0;font-size:16px;line-height:1.5;color:#1f2933;">
                            {{ $greeting }}
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:12px 28px 8px;font-size:15px;line-height:1.7;color:#334155;white-space:pre-line;">{{ $bodyText }}</td>
                    </tr>
                    @if(!empty($details))
                    <tr>
                        <td style="padding:8px 28px 8px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f8fc;border-radius:8px;">
                                @foreach($details as $label => $value)
                                    @if($value !== null && $value !== '')
                                    <tr>
                                        <td style="padding:10px 16px;width:42%;font-size:13px;color:#5c6b7a;border-bottom:1px solid #e2e8f0;">{{ $label }}</td>
                                        <td style="padding:10px 16px;font-size:14px;font-weight:700;color:#0b2540;border-bottom:1px solid #e2e8f0;">{{ $value }}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    @endif
                    @if(!empty($actionUrl) && !empty($actionText))
                    <tr>
                        <td style="padding:18px 28px 8px;">
                            <a href="{{ $actionUrl }}" style="display:inline-block;background:#004aad;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:12px 22px;border-radius:6px;">{{ $actionText }}</a>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:20px 28px 28px;font-size:12px;line-height:1.6;color:#64748b;">
                            This message was sent by Sta. Rita Water District through NovuStream. Please do not reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
