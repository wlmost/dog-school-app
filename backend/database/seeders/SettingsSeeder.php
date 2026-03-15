<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Company settings
        $companySettings = [
            ['key' => 'company_name', 'value' => 'Hundeschule Beispiel', 'type' => 'string', 'description' => 'Firmenname', 'group' => 'company'],
            ['key' => 'company_street', 'value' => 'Musterstraße 123', 'type' => 'string', 'description' => 'Straße und Hausnummer', 'group' => 'company'],
            ['key' => 'company_zip', 'value' => '12345', 'type' => 'string', 'description' => 'Postleitzahl', 'group' => 'company'],
            ['key' => 'company_city', 'value' => 'Musterstadt', 'type' => 'string', 'description' => 'Stadt', 'group' => 'company'],
            ['key' => 'company_country', 'value' => 'Deutschland', 'type' => 'string', 'description' => 'Land', 'group' => 'company'],
            ['key' => 'company_phone', 'value' => '+49 123 456789', 'type' => 'string', 'description' => 'Telefonnummer', 'group' => 'company'],
            ['key' => 'company_email', 'value' => 'info@hundeschule-beispiel.de', 'type' => 'string', 'description' => 'E-Mail-Adresse', 'group' => 'company'],
            ['key' => 'company_website', 'value' => 'https://www.hundeschule-beispiel.de', 'type' => 'string', 'description' => 'Webseite', 'group' => 'company'],
            ['key' => 'company_tax_id', 'value' => 'DE123456789', 'type' => 'string', 'description' => 'Steuernummer', 'group' => 'company'],
            ['key' => 'company_registration_number', 'value' => 'HRB 12345', 'type' => 'string', 'description' => 'Handelsregisternummer', 'group' => 'company'],
        ];

        // Email settings
        $emailSettings = [
            ['key' => 'email_from_address', 'value' => 'noreply@hundeschule-beispiel.de', 'type' => 'string', 'description' => 'Absender E-Mail-Adresse', 'group' => 'email'],
            ['key' => 'email_from_name', 'value' => 'Hundeschule Beispiel', 'type' => 'string', 'description' => 'Absender Name', 'group' => 'email'],
            ['key' => 'email_driver', 'value' => 'smtp', 'type' => 'string', 'description' => 'E-Mail Treiber (smtp, sendmail, log)', 'group' => 'email'],
            ['key' => 'smtp_host', 'value' => 'smtp.example.com', 'type' => 'string', 'description' => 'SMTP Server', 'group' => 'email'],
            ['key' => 'smtp_port', 'value' => '587', 'type' => 'integer', 'description' => 'SMTP Port', 'group' => 'email'],
            ['key' => 'smtp_username', 'value' => 'noreply@hundeschule-beispiel.de', 'type' => 'string', 'description' => 'SMTP Benutzername', 'group' => 'email'],
            ['key' => 'smtp_password', 'value' => '', 'type' => 'string', 'description' => 'SMTP Passwort', 'group' => 'email'],
            ['key' => 'smtp_encryption', 'value' => 'tls', 'type' => 'string', 'description' => 'SMTP Verschlüsselung (tls, ssl)', 'group' => 'email'],
        ];

        // Create all settings
        foreach (array_merge($companySettings, $emailSettings) as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
