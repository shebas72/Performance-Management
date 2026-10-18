<?php

namespace App\Saas\Services;

use App\Models\User;
use App\Saas\Models\PlatformSetting;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the platform owner (super admins) that something needs attention: a bell notification in the
 * super admin panel and an email. Each channel can be switched off in Platform > Settings > Email.
 * A failure here is logged and never breaks the request that triggered it.
 */
class AdminNotifier
{
    public static function send(string $title, string $body, string $url, string $icon = 'heroicon-o-bell', string $color = 'primary'): void
    {
        $admins = User::where('is_super_admin', true)->where('is_active', true)->get();

        if (PlatformSetting::get('notify_web_enabled', '1') !== '0') {
            try {
                foreach ($admins as $admin) {
                    Notification::make()
                        ->title($title)
                        ->body($body)
                        ->icon($icon)
                        ->iconColor($color)
                        ->actions([Action::make('open')->label('Open')->button()->url($url)])
                        ->sendToDatabase($admin);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (PlatformSetting::get('notify_email_enabled', '1') !== '0') {
            try {
                $custom = array_values(array_filter(array_map('trim', explode(',', (string) PlatformSetting::get('notify_emails', '')))));
                $to = $custom ?: $admins->pluck('email')->all();

                if ($to) {
                    Mail::raw("{$title}\n\n{$body}\n\nOpen: {$url}", fn ($m) => $m->to($to)->subject($title));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}