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
            color: #dc2626;
            margin: 10px 0;
        }
        .fee-info {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
            <p>Sehr geehrte(r) {{ $dunning->invoice->customer->user->full_name }},</p>

            <p>trotz Fälligkeit haben wir bislang keinen Zahlungseingang zu folgender Rechnung feststellen können. Wir erlauben uns daher, Sie mit dieser Mahnung Stufe {{ $dunning->level }} an die ausstehende Zahlung zu erinnern.</p>

            <div class="info-box">
                <h2 style="margin-top: 0; color: #1e40af;">Rechnungsdetails</h2>

                <div class="info-row">
                    <span class="info-label">Rechnungsnummer:</span>
                    {{ $dunning->invoice->invoice_number }}
                </div>

                <div class="info-row">
                    <span class="info-label">Fälligkeitsdatum:</span>
                    {{ $dunning->invoice->due_date->format('d.m.Y') }}
                </div>

                <div class="info-row">
                    <span class="info-label">Restbetrag:</span>
                    {{ number_format($dunning->invoice->remaining_balance, 2, ',', '.') }} €
                </div>
            </div>

            <div class="fee-info">
                <h3 style="margin-top: 0; color: #92400e;">Mahnstufe {{ $dunning->level }}</h3>
                <p>Für diese Mahnung wird eine Mahngebühr fällig. Die zugehörige Gebührenrechnung finden Sie als PDF-Anhang zu dieser E-Mail.</p>
            </div>

            <div class="amount-box">
                <div style="font-size: 14px; color: #64748b;">Mahngebühr Stufe {{ $dunning->level }}</div>
                <div class="amount">{{ number_format($dunning->fee_amount, 2, ',', '.') }} €</div>
            </div>

            <p>Bitte begleichen Sie den offenen Restbetrag sowie die Mahngebühr umgehend. Sollten Sie die Zahlung bereits veranlasst haben, betrachten Sie diese Mahnung als gegenstandslos.</p>

            <p>Bei Fragen zu dieser Mahnung stehen wir Ihnen gerne zur Verfügung.</p>

            <p>Mit freundlichen Grüßen,<br>
            Ihr Team von {{ $settings['company_name'] ?? 'Hundeschule' }}</p>
@endsection
