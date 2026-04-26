@extends('layouts.email')

@section('content')
            <p>Liebe(r) {{ $customerFirstName }},</p>

            <p>wir möchten Sie darüber informieren, dass Ihr Hund <strong>{{ $dogName }}</strong> aus unserem System entfernt wurde.</p>

            <div class="info-box">
                <h2 style="margin-top: 0; color: #1e40af;">Entfernter Hund</h2>

                <div class="info-row">
                    <span class="info-label">Name:</span>
                    {{ $dogName }}
                </div>
            </div>

            <p>Wenn Sie Fragen haben oder dies ein Fehler ist, wenden Sie sich bitte an uns.</p>

            <div class="divider"></div>

            <p>Herzliche Grüße,<br>
            Ihr Team von {{ $settings['company_name'] ?? 'Hundeschule' }}</p>
@endsection
