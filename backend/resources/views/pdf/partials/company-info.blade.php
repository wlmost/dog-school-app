@php
    $companyName = \App\Models\Setting::get('company_name', 'Hundeschule');
    $companyStreet = \App\Models\Setting::get('company_street', '');
    $companyZip = \App\Models\Setting::get('company_zip', '');
    $companyCity = \App\Models\Setting::get('company_city', '');
    $companyPhone = \App\Models\Setting::get('company_phone', '');
    $companyEmail = \App\Models\Setting::get('company_email', '');
@endphp
<h1>{{ $companyName }}</h1>
<p>{{ $companyStreet }} • {{ $companyZip }} {{ $companyCity }}</p>
<p>Tel: {{ $companyPhone }} • E-Mail: {{ $companyEmail }}</p>
