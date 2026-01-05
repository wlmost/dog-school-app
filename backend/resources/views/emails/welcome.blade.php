@extends('layouts.email')

@section('content')
    <h2>Willkommen, {{ $user->first_name }}!</h2>
    
    <p>Wir freuen uns, Sie in unserem System begrüßen zu dürfen!</p>
    
    <p>Ihr Zugang wurde erfolgreich eingerichtet. Hier sind Ihre Zugangsdaten:</p>
    
    <div class="info-box">
        <h3 style="margin-top: 0;">Ihre Zugangsdaten</h3>
        <p><strong>E-Mail:</strong> {{ $user->email }}</p>
        <p><strong>Vorläufiges Passwort:</strong> <code style="background-color: #f3f4f6; padding: 2px 8px; border-radius: 3px; font-family: monospace;">{{ $temporaryPassword }}</code></p>
    </div>
    
    <div class="warning-box">
        <p style="margin: 0;"><strong>Wichtig:</strong></p>
        <p style="margin: 10px 0 0 0;">Bitte ändern Sie Ihr Passwort nach dem ersten Login aus Sicherheitsgründen. Sie finden die Option dazu in Ihren Profileinstellungen.</p>
    </div>
    
    <p style="text-align: center;">
        <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/login" class="button">Jetzt anmelden</a>
    </p>
    
    <h3>Erste Schritte</h3>
    <ul>
        <li>Melden Sie sich mit Ihren Zugangsdaten an</li>
        <li>Ändern Sie Ihr Passwort</li>
        <li>Vervollständigen Sie Ihr Profil</li>
        @if($user->role === 'customer')
        <li>Registrieren Sie Ihre Hunde</li>
        <li>Buchen Sie Ihren ersten Kurs</li>
        @endif
    </ul>
    
    <p>Bei Fragen oder Problemen stehen wir Ihnen jederzeit gerne zur Verfügung!</p>
    
    <p>Mit freundlichen Grüßen,<br>
    Ihr Team von {{ $settings['company_name'] ?? 'Hundeschule' }}</p>
@endsection
