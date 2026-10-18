<?php

namespace App\Saas\Services;

use App\Saas\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/** Applies the SMTP settings saved in Platform > Settings over the .env mail config. Does nothing while they are switched off. */
class MailSettings
{
    public static function apply(): void
    {
        if (PlatformSetting::get('smtp_enabled') !== '1' || ! PlatformSetting::get('smtp_host')) return;

        $password = null;
        if ($saved = PlatformSetting::get('smtp_password')) {
            try {
                $password = Crypt::decryptString($saved);
            } catch (\Throwable) {
                $password = null; // unreadable after an APP_KEY change: enter it again in settings
            }
        }

        $encryption = PlatformSetting::get('smtp_encryption', 'tls');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => PlatformSetting::get('smtp_host'),
            'mail.mailers.smtp.port' => (int) PlatformSetting::get('smtp_port', 587),
            'mail.mailers.smtp.username' => PlatformSetting::get('smtp_username'),
            'mail.mailers.smtp.password' => $password,
            // Older Laravel reads "encryption", newer reads "scheme"; both are set.
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
        ]);

        if ($from = PlatformSetting::get('smtp_from_address')) {
            config(['mail.from.address' => $from, 'mail.from.name' => PlatformSetting::get('smtp_from_name', config('app.name'))]);
        }

        Mail::purge();
    }
}