@extends('layouts.email')

@section('content')
            <h2 style="margin-top: 0; color: #1e40af;">Neue Kontaktanfrage</h2>

            <p>Sie haben eine neue Nachricht über das Kontaktformular Ihrer Website erhalten:</p>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    {{ $senderName }}
                </div>

                <div class="info-row">
                    <span class="info-label">E-Mail:</span>
                    <a href="mailto:{{ $senderEmail }}">{{ $senderEmail }}</a>
                </div>

                @if($phone)
                    <div class="info-row">
                        <span class="info-label">Telefon:</span>
                        {{ $phone }}
                    </div>
                @endif

                <div class="info-row">
                    <span class="info-label">Betreff:</span>
                    {{ $contactSubject }}
                </div>
            </div>

            <h3>Nachricht</h3>
            <div style="background-color: #f9fafb; border-left: 4px solid #3b82f6; padding: 16px; margin: 16px 0; border-radius: 0 8px 8px 0;">
                <p style="margin: 0; white-space: pre-wrap;">{{ $contactMessage }}</p>
            </div>

            <div class="divider"></div>

            <p>Klicken Sie auf <strong>"Antworten"</strong> in Ihrem E-Mail-Programm, um direkt an <strong>{{ $senderEmail }}</strong> zu antworten.</p>
@endsection
