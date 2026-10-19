<?php

namespace App\Notifications;

use App\Services\NotificationPrefs;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    public function __construct(
        public string $category,
        public string $title,          // English source text, may contain :placeholders
        public string $body,
        public ?string $url = null,    // path in the React app, for example /kpis/12
        public string $level = 'info', // info | success | warning | danger
        public ?string $key = null,    // used to avoid sending the same reminder twice
        public array $params = [],
    ) {}

    public function via(object $notifiable): array
    {
        return NotificationPrefs::channels($notifiable, $this->category);
    }

    // Laravel runs toDatabase() and toMail() in the recipient's preferred locale, so __() picks their language.
    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => __($this->title, $this->params),
            'body' => __($this->body, $this->params),
            'url' => $this->url,
            'level' => $this->level,
            'key' => $this->key,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(__($this->title, $this->params))->line(__($this->body, $this->params));

        if ($this->url) {
            $mail->action(__('Open'), rtrim(config('app.frontend_url', 'http://localhost:5173'), '/').$this->url);
        }

        return $mail;
    }
}