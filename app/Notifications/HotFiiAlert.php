<?php

namespace App\Notifications;

use App\Notifications\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HotFiiAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $actionUrl = null,
        public readonly string $category = 'account',
        public readonly ?string $organizationId = null,
        public readonly array $mobileData = [],
        public readonly bool $sendMail = true,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return array_values(array_filter([
            'database',
            $this->sendMail ? 'mail' : null,
            FirebaseChannel::class,
        ]));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->line($this->message);

        return $this->actionUrl ? $mail->action('Open HotFii', $this->actionUrl) : $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->actionUrl,
            'category' => $this->category,
            'organization_id' => $this->organizationId,
            ...$this->mobileData,
        ];
    }

    public function toFirebase(object $notifiable): array
    {
        $data = collect([
            'category' => $this->category,
            'organization_id' => $this->organizationId,
            'url' => $this->actionUrl,
            ...$this->mobileData,
        ])->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)
            ->all();

        return [
            'notification' => ['title' => $this->title, 'body' => $this->message],
            'data' => $data,
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'hotfii_'.$this->category,
                    'sound' => 'default',
                ],
            ],
        ];
    }
}
