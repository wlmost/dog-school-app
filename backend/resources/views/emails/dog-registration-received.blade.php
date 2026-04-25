@extends('layouts.email')

@section('content')
            <p>Hallo Admin,</p>

            <p>es ist eine neue Hunderegistrierungsanfrage eingegangen. Bitte überprüfen Sie die Anfrage im Dashboard und genehmigen oder lehnen Sie diese ab.</p>

            <div class="info-box">
                <h2 style="margin-top: 0; color: #1e40af;">Details der Anfrage</h2>

                <div class="info-row">
                    <span class="info-label">Kunde:</span>
                    {{ $registrationRequest->customer->user->full_name ?? 'Unbekannt' }}
                </div>

                <div class="info-row">
                    <span class="info-label">E-Mail:</span>
                    {{ $registrationRequest->customer->user->email ?? '-' }}
                </div>

                <div class="info-row">
                    <span class="info-label">Hundename:</span>
                    {{ $registrationRequest->name }}
                </div>

                @if($registrationRequest->breed)
                <div class="info-row">
                    <span class="info-label">Rasse:</span>
                    {{ $registrationRequest->breed }}
                </div>
                @endif

                @if($registrationRequest->gender)
                <div class="info-row">
                    <span class="info-label">Geschlecht:</span>
                    {{ $registrationRequest->gender === 'male' ? 'Männlich' : 'Weiblich' }}
                </div>
                @endif

                @if($registrationRequest->date_of_birth)
                <div class="info-row">
                    <span class="info-label">Geburtsdatum:</span>
                    {{ $registrationRequest->date_of_birth->format('d.m.Y') }}
                </div>
                @endif

                <div class="info-row">
                    <span class="info-label">Kastriert:</span>
                    {{ $registrationRequest->neutered ? 'Ja' : 'Nein' }}
                </div>

                @if($registrationRequest->chip_number)
                <div class="info-row">
                    <span class="info-label">Chipnummer:</span>
                    {{ $registrationRequest->chip_number }}
                </div>
                @endif

                @if($registrationRequest->notes)
                <div class="info-row">
                    <span class="info-label">Hinweise:</span>
                    {{ $registrationRequest->notes }}
                </div>
                @endif

                <div class="info-row">
                    <span class="info-label">Eingereicht am:</span>
                    {{ $registrationRequest->created_at->format('d.m.Y H:i') }} Uhr
                </div>
            </div>

            <div class="warning-box">
                <strong>Aktion erforderlich:</strong> Bitte überprüfen Sie diese Anfrage im Admin-Dashboard und genehmigen oder lehnen Sie sie ab.
            </div>

            <p>Herzliche Grüße,<br>
            Ihr {{ $settings['company_name'] ?? 'Hundeschule' }}-System</p>
@endsection
