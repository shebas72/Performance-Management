<?php

namespace App\Notifications;

use App\Services\NotificationPrefs;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    public function __construct(
        public string $category,
        public string $title,
        public string $body,
        public ?string $url = null,   // path in the React app, for example /kpis/12
        public string $level = 'info', // info | success | warning | danger
        public ?string $key = null,    // used to avoid sending the same reminder twice
    ) {}

    public function via(object $notifiable): array
    {
        return NotificationPrefs::channels($notifiable, $this->category);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'level' => $this->level,
            'key' => $this->key,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->line($this->body);

        if ($this->url) {
            $mail->action('Open', rtrim(config('app.frontend_url', 'http://localhost:5173'), '/').$this->url);
        }

        return $mail;
    }
}