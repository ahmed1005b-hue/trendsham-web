<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * رسالة رابط إعادة تعيين كلمة المرور (HTML بتصميم تريندشام + نسخة نصية احتياطية).
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $url,
        public int $minutes = 60,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('إعادة تعيين كلمة المرور').' - '.__('تريندشام'));
    }

    public function content(): Content
    {
        $name = trim(($this->user->first_name ?? '').' '.($this->user->last_name ?? ''));

        return new Content(
            view: 'emails.password-reset',
            text: 'emails.password-reset-text',
            with: [
                'name' => $name !== '' ? $name : __('عزيزنا العميل'),
                'url' => $this->url,
                'minutes' => $this->minutes,
            ],
        );
    }
}
