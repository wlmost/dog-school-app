<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue Rechnung</title>
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
        .amount-box {
            background-color: #dbeafe;
            border: 2px solid #2563eb;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
            border-radius: 4px;
        }
        .amount {
            font-size: 32px;
            font-weight: bold;
            color: #1e40af;
            margin: 10px 0;
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
        .payment-info {
            background-color: #fffbeb;
            border-left: 4px solid: #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background-color: #f3f4f6;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📄 Neue Rechnung</h1>
        </div>
        
        <div class="content">
            <p>Sehr geehrte(r) {{ $invoice->customer->user->full_name }},</p>
            
            <p>anbei erhalten Sie Ihre Rechnung für unsere Dienstleistungen.</p>
            
            <div class="info-box">
                <h2 style="margin-top: 0; color: #1e40af;">Rechnungsdetails</h2>
                
                <div class="info-row">
                    <span class="info-label">Rechnungsnummer:</span>
                    {{ $invoice->invoice_number }}
                </div>
                
                <div class="info-row">
                    <span class="info-label">Rechnungsdatum:</span>
                    {{ $invoice->issue_date->format('d.m.Y') }}
                </div>
                
                <div class="info-row">
                    <span class="info-label">Fälligkeitsdatum:</span>
                    {{ $invoice->due_date->format('d.m.Y') }}
                </div>
            </div>
            
            <div class="amount-box">
                <div style="font-size: 14px; color: #64748b;">Rechnungsbetrag</div>
                <div class="amount">{{ number_format($invoice->total_amount, 2, ',', '.') }} €</div>
            </div>
            
            <h3>Rechnungspositionen</h3>
            <table>
                <thead>
                    <tr>
                        <th>Beschreibung</th>
                        <th style="text-align: right;">Menge</th>
                        <th style="text-align: right;">Betrag</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td style="text-align: right;">{{ $item->quantity }}</td>
                            <td style="text-align: right;">{{ number_format($item->amount, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div class="payment-info">
                <h3 style="margin-top: 0; color: #92400e;">Zahlungsinformationen</h3>
                
                <div class="info-row">
                    <span class="info-label">Zahlungsziel:</span>
                    {{ $invoice->due_date->format('d.m.Y') }}
                </div>
                
                <div class="info-row">
                    <span class="info-label">IBAN:</span>
                    DE89 3704 0044 0532 0130 00
                </div>
                
                <div class="info-row">
                    <span class="info-label">BIC:</span>
                    COBADEFFXXX
                </div>
                
                <div class="info-row">
                    <span class="info-label">Verwendungszweck:</span>
                    {{ $invoice->invoice_number }}
                </div>
            </div>
            
            @if($invoice->notes)
                <h3>Anmerkungen</h3>
                <p>{{ $invoice->notes }}</p>
            @endif
            
            <p>Bei Fragen zu dieser Rechnung stehen wir Ihnen gerne zur Verfügung.</p>
            
            <p>Mit freundlichen Grüßen,<br>
            Ihr Team von Hundeschule Mustermann</p>
        </div>
        
        <div class="footer">
            <p><strong>Hundeschule Mustermann</strong></p>
            <p>Musterstraße 123 • 12345 Musterstadt</p>
            <p>Tel: +49 123 456789 • E-Mail: info@hundeschule-mustermann.de</p>
            <p>USt-IdNr: DE123456789</p>
            <p style="margin-top: 15px;">© {{ date('Y') }} Hundeschule Mustermann. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</body>
</html>
