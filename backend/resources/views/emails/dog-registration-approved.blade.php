@extends('layouts.email')

@section('content')
            <p>Liebe(r) {{ $registrationRequest->customer->user->first_name }},</p>

            <p>Wir freuen uns, Ihnen mitteilen zu können, dass Ihr Hund <strong>{{ $registrationRequest->name }}</strong> erfolgreich in unserem System angelegt wurde.</p>

            <div class="info-box">
                <h2 style="margin-top: 0; color: #1e40af;">Angaben zu Ihrem Hund</h2>

                <div class="info-row">
                    <span class="info-label">Name:</span>
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
            </div>

            <p>Sie können ab sofort an unseren Kursen teilnehmen und Trainingseinheiten für {{ $registrationRequest->name }} buchen.</p>

            <div class="divider"></div>

            <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>

            <p>Herzliche Grüße,<br>
            Ihr Team von {{ $settings['company_name'] ?? 'Hundeschule' }}</p>
@endsection
