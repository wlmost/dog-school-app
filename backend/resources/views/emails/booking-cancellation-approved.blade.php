@extends('layouts.email')

@section('content')
            <p>Hallo {{ $booking->dog->customer->user->first_name }},</p>

            <p>Ihre Stornierungsanfrage für die folgende Buchung wurde vom Trainer genehmigt. Die Buchung ist nun storniert.</p>

            <div class="info-box">
                <h2 style="margin-top: 0; color: #1e40af;">Stornierte Buchung</h2>

                <div class="info-row">
                    <span class="info-label">Kurs:</span>
                    {{ $booking->trainingSession->course->name }}
                </div>

                <div class="info-row">
                    <span class="info-label">Datum:</span>
                    {{ $booking->trainingSession->session_date->format('d.m.Y') }}
                    @if($booking->trainingSession->start_time)
                        um {{ \Carbon\Carbon::parse($booking->trainingSession->start_time)->format('H:i') }} Uhr
                    @endif
                </div>

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

                @if($booking->cancellation_reason)
                    <div class="info-row">
                        <span class="info-label">Stornierungsgrund:</span>
                        {{ $booking->cancellation_reason }}
                    </div>
                @endif
            </div>

            <p>Wir hoffen, Sie bald wieder bei uns begrüßen zu dürfen!</p>

            <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>

            <p>Bis bald,<br>
            Ihr Team von {{ $settings['company_name'] ?? 'Hundeschule' }}</p>
@endsection
