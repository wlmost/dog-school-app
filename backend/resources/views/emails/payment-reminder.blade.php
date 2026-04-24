@extends('layouts.email')

@section('content')
    <style>
        .amount-box {
            background-color: #fee2e2;
            border: 2px solid #dc2626;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
            border-radius: 4px;
        }
        .amount {
            font-size: 32px;
            font-weight: bold;
            color: #991b1b;
            margin: 10px 0;
        }
        .payment-info {
            background-color: #dbeafe;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
            <p>Sehr geehrte(r) {{ $invoice->customer->user->full_name }},</p>
            
            <div class="warning-box">
                <p style="margin: 0;"><strong>Wichtiger Hinweis:</strong></p>
                <p style="margin: 10px 0 0 0;">
                    @if($invoice->isOverdue())
                        Die Rechnung <strong>{{ $invoice->invoice_number }}</strong> ist seit dem {{ $invoice->due_date->format('d.m.Y') }} überfällig.
                    @else
                        Die Rechnung <strong>{{ $invoice->invoice_number }}</strong> ist am {{ $invoice->due_date->format('d.m.Y') }} fällig.
                    @endif
                </p>
            </div>
            
            <p>wir möchten Sie freundlich daran erinnern, dass wir bisher noch keinen Zahlungseingang für die folgende Rechnung verbuchen konnten:</p>
            
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
                    <strong style="color: #dc2626;">{{ $invoice->due_date->format('d.m.Y') }}</strong>
                    @if($invoice->isOverdue())
                        <span style="color: #dc2626;">({{ $invoice->due_date->diffInDays(now()) }} Tage überfällig)</span>
                    @endif
                </div>
            </div>
            
            <div class="amount-box">
                <div style="font-size: 14px; color: #7f1d1d;">Offener Betrag</div>
                <div class="amount">{{ number_format($invoice->remaining_balance, 2, ',', '.') }} €</div>
            </div>
            
            <div class="payment-info">
                <h3 style="margin-top: 0; color: #1e40af;">Zahlungsinformationen</h3>
                
                <p>Bitte überweisen Sie den offenen Betrag unter Angabe der Rechnungsnummer auf folgendes Konto:</p>
                
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
                    <strong>{{ $invoice->invoice_number }}</strong>
                </div>
            </div>
            
            <p>Falls Sie die Zahlung bereits veranlasst haben, betrachten Sie dieses Schreiben bitte als gegenstandslos. Sollte es Probleme oder Fragen bezüglich dieser Rechnung geben, kontaktieren Sie uns bitte umgehend.</p>
            
            <p>Wir danken Ihnen für Ihr Verständnis und Ihre prompte Zahlung.</p>
            
            <p>Mit freundlichen Grüßen,<br>
            Ihr Team von {{ $settings['company_name'] ?? 'Hundeschule' }}</p>
@endsection
