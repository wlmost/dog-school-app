<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buchungsbestätigung</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background-color: #2563eb;
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px 20px;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #2563eb;
            padding: 15px;
            margin: 20px 0;
        }
        .info-row {
            margin: 10px 0;
        }
        .info-label {
            font-weight: bold;
            color: #1e40af;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
        }
        .divider {
            border-top: 1px solid #e5e7eb;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Buchungsbestätigung</h1>
        </div>
        
        <div class="content">
            <p>Hallo {{ $booking->dog->customer->user->first_name }},</p>
            
            <p>vielen Dank für Ihre Buchung! Wir freuen uns, Sie und <strong>{{ $booking->dog->name }}</strong> beim Training begrüßen zu dürfen.</p>
            
            <div class="info-box">
                <h2 style="margin-top: 0; color: #1e40af;">Buchungsdetails</h2>
                
                <div class="info-row">
                    <span class="info-label">Kurs:</span>
                    {{ $booking->trainingSession->course->name }}
                </div>
                
                <div class="info-row">
                    <span class="info-label">Datum & Uhrzeit:</span>
                    {{ $booking->trainingSession->session_date->format('d.m.Y') }} um {{ $booking->trainingSession->session_date->format('H:i') }} Uhr
                </div>
                
                <div class="info-row">
                    <span class="info-label">Dauer:</span>
                    {{ $booking->trainingSession->course->duration }} Minuten
                </div>
                
                @if($booking->trainingSession->location)
                    <div class="info-row">
                        <span class="info-label">Ort:</span>
                        {{ $booking->trainingSession->location }}
                    </div>
                @endif
                
                <div class="info-row">
                    <span class="info-label">Hund:</span>
                    {{ $booking->dog->name }}
                    @if($booking->dog->breed)
                        ({{ $booking->dog->breed }})
                    @endif
                </div>
                
                <div class="info-row">
                    <span class="info-label">Buchungsnummer:</span>
                    #{{ $booking->id }}
                </div>
            </div>
            
            @if($booking->trainingSession->course->description)
                <h3>Über diesen Kurs</h3>
                <p>{{ $booking->trainingSession->course->description }}</p>
            @endif
            
            <div class="divider"></div>
            
            <h3>Was Sie mitbringen sollten</h3>
            <ul>
                <li>Leine und Halsband/Geschirr</li>
                <li>Leckerlis für die Belohnung</li>
                <li>Lieblingsspielzeug Ihres Hundes</li>
                <li>Wasser für Ihren Hund</li>
                <li>Gute Laune! 😊</li>
            </ul>
            
            <div class="divider"></div>
            
            <p><strong>Wichtig:</strong> Sollten Sie den Termin nicht wahrnehmen können, bitten wir um eine rechtzeitige Absage (mindestens 24 Stunden vorher), damit wir anderen Kunden die Möglichkeit geben können, an dem Training teilzunehmen.</p>
            
            <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung!</p>
            
            <p>Bis bald,<br>
            Ihr Team von Hundeschule Mustermann</p>
        </div>
        
        <div class="footer">
            <p><strong>Hundeschule Mustermann</strong></p>
            <p>Musterstraße 123 • 12345 Musterstadt</p>
            <p>Tel: +49 123 456789 • E-Mail: info@hundeschule-mustermann.de</p>
            <p style="margin-top: 15px;">© {{ date('Y') }} Hundeschule Mustermann. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</body>
</html>
