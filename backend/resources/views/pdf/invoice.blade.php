<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Rechnung {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        h1 {
            color: #2563eb;
            font-size: 18pt;
            margin-bottom: 5px;
        }
        
        h2 {
            font-size: 14pt;
            color: #1e40af;
            margin: 20px 0 10px 0;
        }
        
        h3 {
            font-size: 11pt;
            color: #1e40af;
            margin: 15px 0 8px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        table.items th {
            background-color: #2563eb;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        
        table.items td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .text-right {
            text-align: right;
        }
        
        .company-info {
            text-align: right;
            font-size: 9pt;
            color: #666;
            margin-bottom: 20px;
        }
        
        .customer-box {
            margin: 20px 0;
            padding: 10px;
            border: 1px solid #ddd;
            width: 45%;
        }
        
        .invoice-details {
            margin: 20px 0;
            font-size: 9pt;
        }
        
        .totals-table {
            margin-left: auto;
            margin-right: 0;
            width: 250px;
            margin-top: 20px;
        }
        
        .totals-table td {
            padding: 5px;
            border: none;
        }
        
        .total-row {
            font-size: 12pt;
            font-weight: bold;
            color: #1e40af;
            border-top: 2px solid #2563eb;
        }
        
        .payment-box {
            margin-top: 30px;
            padding: 15px;
            background-color: #f3f4f6;
            border-left: 3px solid #2563eb;
        }
        
        .notes-box {
            margin-top: 20px;
            padding: 10px;
            background-color: #fffbeb;
            border-left: 3px solid #f59e0b;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
        
        .status-badge {
            padding: 3px 10px;
            background-color: #d1fae5;
            color: #065f46;
            font-weight: bold;
            font-size: 8pt;
        }
    </style>
</head>
<body>
    @php
        $isSmallBusiness = \App\Models\Setting::get('company_small_business', false);
    @endphp
    
    <!-- Company Header -->
    <div class="company-info">
        <h1>Hundeschule Max Mustermann</h1>
        <p>Musterstraße 123 • 12345 Musterstadt</p>
        <p>Tel: +49 123 456789 • E-Mail: info@hundeschule-mustermann.de</p>
    </div>

    <h2>RECHNUNG</h2>

    <!-- Customer Address -->
    <div class="customer-box">
        <p><strong>{{ $invoice->customer->user->full_name }}</strong></p>
        @if($invoice->customer->address_line1)
            <p>{{ $invoice->customer->address_line1 }}</p>
        @endif
        @if($invoice->customer->address_line2)
            <p>{{ $invoice->customer->address_line2 }}</p>
        @endif
        @if($invoice->customer->postal_code || $invoice->customer->city)
            <p>{{ $invoice->customer->postal_code }} {{ $invoice->customer->city }}</p>
        @endif
    </div>

    <!-- Invoice Details -->
    <div class="invoice-details">
        <p><strong>Rechnungsnummer:</strong> {{ $invoice->invoice_number }}</p>
        <p><strong>Rechnungsdatum:</strong> {{ $invoice->issue_date->format('d.m.Y') }}</p>
        <p><strong>Fälligkeitsdatum:</strong> {{ $invoice->due_date->format('d.m.Y') }}</p>
        <p><strong>Status:</strong> 
            <span class="status-badge">
                @if($invoice->status === 'paid') BEZAHLT 
                @elseif($invoice->status === 'overdue') ÜBERFÄLLIG
                @else {{ strtoupper($invoice->status) }}
                @endif
            </span>
        </p>
    </div>

    <!-- Invoice Items -->
    <table class="items">
        <thead>
            <tr>
                <th>Beschreibung</th>
                <th class="text-right">Menge</th>
                <th class="text-right">Einzelpreis</th>
                @if(!$isSmallBusiness)
                    <th class="text-right">MwSt.</th>
                @endif
                <th class="text-right">Gesamt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2, ',', '.') }} €</td>
                    @if(!$isSmallBusiness)
                        <td class="text-right">{{ number_format($item->tax_rate, 0) }}%</td>
                    @endif
                    <td class="text-right">{{ number_format($item->amount, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <table class="totals-table">
        <tr>
            <td>{{ $isSmallBusiness ? 'Gesamt (netto):' : 'Zwischensumme:' }}</td>
            <td class="text-right"><strong>{{ number_format($invoice->items->sum('amount'), 2, ',', '.') }} €</strong></td>
        </tr>
        @if(!$isSmallBusiness)
            @php
                $taxGroups = $invoice->items->groupBy('tax_rate');
            @endphp
            @foreach($taxGroups as $taxRate => $items)
                @php
                    $taxableAmount = $items->sum('amount');
                    $taxAmount = $taxableAmount * ($taxRate / 100);
                @endphp
                <tr>
                    <td>MwSt. {{ number_format($taxRate, 0) }}%:</td>
                    <td class="text-right"><strong>{{ number_format($taxAmount, 2, ',', '.') }} €</strong></td>
                </tr>
            @endforeach
        @endif
        @if($isSmallBusiness)
            <tr>
                <td colspan="2" style="font-size: 8pt; color: #666; font-style: italic; padding-top: 5px;">
                    Gemäß §19 UStG wird keine Umsatzsteuer berechnet
                </td>
            </tr>
        @endif
        <tr class="total-row">
            <td><strong>Gesamtsumme:</strong></td>
            <td class="text-right"><strong>{{ number_format($invoice->total_amount, 2, ',', '.') }} €</strong></td>
        </tr>
        @if($invoice->total_paid > 0)
            <tr>
                <td>Bereits bezahlt:</td>
                <td class="text-right"><strong>{{ number_format($invoice->total_paid, 2, ',', '.') }} €</strong></td>
            </tr>
            <tr>
                <td>Noch offen:</td>
                <td class="text-right"><strong>{{ number_format($invoice->remaining_balance, 2, ',', '.') }} €</strong></td>
            </tr>
        @endif
    </table>

    <!-- Payment Information -->
    @if($invoice->status !== 'paid')
        <div class="payment-box">
            <h3>Zahlungsinformationen</h3>
            <p><strong>Zahlungsziel:</strong> {{ $invoice->due_date->format('d.m.Y') }}</p>
            <p><strong>IBAN:</strong> DE89 3704 0044 0532 0130 00</p>
            <p><strong>BIC:</strong> COBADEFFXXX</p>
            <p><strong>Verwendungszweck:</strong> {{ $invoice->invoice_number }}</p>
        </div>
    @else
        <div class="payment-box" style="background-color: #d1fae5; border-left-color: #059669;">
            <h3 style="color: #065f46;">Zahlungsbestätigung</h3>
            <p><strong>Diese Rechnung wurde vollständig bezahlt.</strong></p>
        </div>
    @endif

    <!-- Notes -->
    @if($invoice->notes)
        <div class="notes-box">
            <h3>Anmerkungen</h3>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>Hundeschule Max Mustermann • Musterstraße 123 • 12345 Musterstadt</p>
        <p>USt-IdNr: DE123456789</p>
    </div>
</body>
</html>
