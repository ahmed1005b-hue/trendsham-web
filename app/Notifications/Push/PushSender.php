<?php

namespace App\Notifications\Push;

use App\Support\MessageTranslator;
use App\Models\AppNotification;
use App\Models\PushDevice;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يرسل إشعاراً محفوظاً إلى هواتف صاحبه. يُستدعى بعد الرد فلا يؤخر أي عملية،
 * ولا يرمي استثناءات أبداً. كل إشعار يُرسل مرة واحدة فقط (حجز شرطي قبل الإرسال).
 */
class PushSender
{
    public function __construct(private FcmClient $fcm, private PushPolicy $policy) {}

    public function send(int $notificationId): void
    {
        // الحجز: صف ما زال pending ولم يبدأ إرساله. أي تشغيل ثانٍ لا يجد ما يحجزه.
        $claimed = AppNotification::whereKey($notificationId)
            ->where('push_status', 'pending')->whereNull('pushed_at')
            ->update(['pushed_at' => now()]);
        if ($claimed !== 1) {
            return;
        }

        try {
            $status = $this->deliver(AppNotification::with('user')->findOrFail($notificationId));
        } catch (Throwable $e) {
            Log::warning("Push for notification {$notificationId} failed: ".$e->getMessage());
            $status = 'failed';
        }

        AppNotification::whereKey($notificationId)->update(['push_status' => $status, 'pushed_at' => now()]);
    }

    private function deliver(AppNotification $n): string
    {
        if (! $n->user) {
            return 'no_device';
        }

        $decision = $this->policy->decide($n, $n->user);
        if ($decision !== PushPolicy::SEND) {
            return $decision;
        }

        if (! $this->fcm->isConfigured()) {
            return 'not_configured';
        }

        $devices = PushDevice::where('user_id', $n->user_id)->get();
        if ($devices->isEmpty()) {
            return 'no_device';
        }

        $data = self::payloadData($n);
        // بلغة المستلم المحفوظة، لا لغة من تسبّب بالإشعار
        $locale = $n->user->preferredLocale();
        $title = MessageTranslator::translate($n->title, $locale);
        $body = MessageTranslator::translate($n->body, $locale);
        $anySent = false;
        foreach ($devices as $device) {
            $result = $this->fcm->send($device->token, $title, $body, $data);
            if ($result->ok) {
                $anySent = true;
            } elseif ($result->invalidToken) {
                $device->delete();
            }
        }

        return $anySent ? 'sent' : 'failed';
    }

    /** ما يستقبله التطبيق في data (كلها نصوص عند الإرسال). */
    public static function payloadData(AppNotification $n): array
    {
        $target = $n->target ?: ['screen' => 'notifications', 'id' => null];

        return [
            'notification_id' => $n->id,
            'type' => $n->type,
            'screen' => $target['screen'] ?? 'notifications',
            'id' => $target['id'] ?? null,
        ];
    }
}
