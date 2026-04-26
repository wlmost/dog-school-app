<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * MailConfigService
 *
 * Applies email settings stored in the database to the Laravel mailer at runtime,
 * so that admin-configured SMTP credentials are actually used when sending emails.
 */
class MailConfigService
{
    /**
     * Apply email/SMTP settings from the database to the running mail config.
     *
     * Call this before sending any email that should use the DB-configured transport.
     */
    public function applyFromSettings(): void
    {
        $keys = [
            'email_driver',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'email_from_address',
            'email_from_name',
        ];

        $settings = Cache::remember('mail_config_settings', 3600, function () use ($keys) {
            return Setting::whereIn('key', $keys)
                ->pluck('value', 'key')
                ->toArray();
        });

        $driver = $settings['email_driver'] ?? config('mail.default', 'smtp');

        $encryption = $settings['smtp_encryption'] ?? config('mail.mailers.smtp.encryption', 'tls');
        if ($encryption === 'null') {
            $encryption = null;
        }

        config([
            'mail.default' => $driver,
            'mail.mailers.smtp.host' => $settings['smtp_host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => isset($settings['smtp_port'])
                ? (int) $settings['smtp_port']
                : config('mail.mailers.smtp.port', 587),
            'mail.mailers.smtp.username' => $settings['smtp_username'] ?? config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $settings['smtp_password'] ?? config('mail.mailers.smtp.password'),
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address' => $settings['email_from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['email_from_name'] ?? config('mail.from.name'),
        ]);

        // Purge the cached transport so the new config is used immediately
        app('mail.manager')->purge($driver);
    }
}
