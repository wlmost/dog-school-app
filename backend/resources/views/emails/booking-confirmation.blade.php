@extends('layouts.email')

@section('content')
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
            Ihr Team von {{ $settings['company_name'] ?? 'Hundeschule' }}</p>
@endsection
