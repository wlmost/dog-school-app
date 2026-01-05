<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? 'E-Mail' }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .email-header img {
            max-width: 200px;
            height: auto;
        }
        .email-header h1 {
            color: #ffffff;
            margin: 15px 0 0 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 40px 30px;
        }
        .email-body h2 {
            color: #667eea;
            font-size: 22px;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .email-body p {
            margin: 0 0 15px 0;
            color: #555555;
        }
        .email-body ul {
            margin: 15px 0;
            padding-left: 20px;
        }
        .email-body li {
            margin-bottom: 10px;
            color: #555555;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            margin: 20px 0;
            background-color: #667eea;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        .button:hover {
            background-color: #5568d3;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin: 20px 0;
        }
        .info-box strong {
            color: #667eea;
        }
        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin: 20px 0;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 30px 30px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        .email-footer p {
            margin: 5px 0;
            font-size: 14px;
            color: #666666;
        }
        .email-footer a {
            color: #667eea;
            text-decoration: none;
        }
        .divider {
            height: 1px;
            background-color: #e0e0e0;
            margin: 30px 0;
        }
        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 20px 15px !important;
            }
            .email-header {
                padding: 20px 15px !important;
            }
            .email-footer {
                padding: 20px 15px !important;
            }
        }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <div class="email-container">
                    <!-- Header -->
                    <div class="email-header">
                        @if(!empty($settings['company_logo']) && file_exists(storage_path('app/public/' . $settings['company_logo'])))
                            <img src="{{ asset('storage/' . $settings['company_logo']) }}" alt="{{ $settings['company_name'] ?? 'Hundeschule' }}">
                        @else
                            <h1 style="margin: 0;">{{ $settings['company_name'] ?? 'Hundeschule' }}</h1>
                        @endif
                    </div>

                    <!-- Body -->
                    <div class="email-body">
                        @yield('content')
                    </div>

                    <!-- Footer -->
                    <div class="email-footer">
                        <p><strong>{{ $settings['company_name'] ?? 'Hundeschule Mustermann' }}</strong></p>
                        <p>
                            {{ $settings['company_address'] ?? 'Musterstraße 123' }}<br>
                            {{ $settings['company_zip'] ?? '12345' }} {{ $settings['company_city'] ?? 'Musterstadt' }}
                        </p>
                        <p>
                            Tel: {{ $settings['company_phone'] ?? '+49 123 456789' }}<br>
                            E-Mail: <a href="mailto:{{ $settings['company_email'] ?? 'info@hundeschule.de' }}">{{ $settings['company_email'] ?? 'info@hundeschule.de' }}</a>
                        </p>
                        @if(!empty($settings['company_website']))
                        <p>
                            Web: <a href="{{ $settings['company_website'] }}">{{ $settings['company_website'] }}</a>
                        </p>
                        @endif
                        <div class="divider"></div>
                        <p style="font-size: 12px; color: #999999;">
                            Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese E-Mail.
                        </p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
