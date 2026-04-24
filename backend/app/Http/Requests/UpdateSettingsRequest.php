<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Settings Request
 *
 * Validates settings update requests.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Company settings
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_zip' => ['sometimes', 'nullable', 'string', 'max:20'],
            'company_city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'company_website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'company_tax_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_registration_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_small_business' => ['sometimes', 'nullable', 'in:true,false,1,0'],
            'company_logo' => ['sometimes', 'nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,svg'],
            'company_favicon' => ['sometimes', 'nullable', 'image', 'max:512', 'mimes:png,ico'],

            // Email settings
            'email_from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'email_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email_driver' => ['sometimes', 'nullable', 'string', 'in:smtp,sendmail,mailgun,ses,postmark,log'],
            
            // SMTP settings
            'smtp_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_encryption' => ['sometimes', 'nullable', 'string', 'in:tls,ssl,null'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => 'Firmenname',
            'company_street' => 'Straße',
            'company_zip' => 'PLZ',
            'company_city' => 'Stadt',
            'company_country' => 'Land',
            'company_phone' => 'Telefon',
            'company_email' => 'E-Mail',
            'company_website' => 'Webseite',
            'company_tax_id' => 'Steuernummer',
            'company_registration_number' => 'Handelsregisternummer',
            'company_small_business' => 'Kleinunternehmerregelung',
            'company_logo' => 'Logo',
            'company_favicon' => 'Favicon',
            'email_from_address' => 'Absender E-Mail',
            'email_from_name' => 'Absender Name',
            'email_driver' => 'E-Mail Treiber',
            'smtp_host' => 'SMTP Host',
            'smtp_port' => 'SMTP Port',
            'smtp_username' => 'SMTP Benutzername',
            'smtp_password' => 'SMTP Passwort',
            'smtp_encryption' => 'SMTP Verschlüsselung',
        ];
    }
}
