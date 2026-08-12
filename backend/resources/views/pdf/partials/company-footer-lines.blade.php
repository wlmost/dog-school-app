@php
    $companyName = \App\Models\Setting::get('company_name', 'Hundeschule');
    $companyStreet = \App\Models\Setting::get('company_street', '');
    $companyZip = \App\Models\Setting::get('company_zip', '');
    $companyCity = \App\Models\Setting::get('company_city', '');
    $companyTaxId = \App\Models\Setting::get('company_tax_id', '');
@endphp
<p>{{ $companyName }} • {{ $companyStreet }} • {{ $companyZip }} {{ $companyCity }}</p>
<p>USt-IdNr: {{ $companyTaxId }}</p>
