<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One generic, DISCREET email (decided 2026-09-30: subject lines and previews stay
 * neutral; never message text, notes or anything clinical). All wording is decided in
 * App\Services\Notifier — this class only lays it out.
 *
 * Named "Notice" to stay clear of App\Models\Notification from the original design.
 */
class MindwellNotice extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $lines
     */
    public function __construct(
        public string $subject,
        public string $greeting,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        // Receipts (2026-10-02) always go out, so they swap the "turn these off" line
        // for their own footer. Everything else keeps the default.
        public ?string $footer = 'You can turn these emails off in Settings in Mindwell.',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting($this->greeting);

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        if ($this->actionText && $this->actionUrl) {
            $mail->action($this->actionText, $this->actionUrl);
        }

        if ($this->footer) {
            $mail->line($this->footer);
        }

        return $mail->salutation('Take care, the Mindwell team');
    }
}
