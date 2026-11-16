<?php

namespace App\Notifications\Push;

use App\Models\AppNotification;
use App\Models\User;

/**
 * هل يُرسل هذا الإشعار إلى الهاتف؟ يعيد SEND أو سبب المنع (يُحفظ في push_status).
 * الإشعار يبقى في الجرس دائماً مهما كان القرار.
 */
class PushPolicy
{
    public const SEND = 'send';

    public function __construct(private PushPreferences $prefs) {}

    public function decide(AppNotification $n, User $user): string
    {
        $group = $n->group ?: NotificationGroups::groupOf($n->type, (string) $user->role);

        // الحرجة (أمان ومال) وغير المصنفة تُرسل دائماً.
        if ($group === NotificationGroups::CRITICAL_GROUP || $group === NotificationGroups::GENERAL) {
            return self::SEND;
        }

        // العروض: لا تُرسل إلا بموافقة صريحة؛ وغيرها مفعّلة ما لم تُطفأ.
        $enabled = $this->prefs->isEnabled($user, $group);
        if ($enabled !== true) {
            return 'skipped_disabled';
        }

        // عروض المتاجر: لا شيء ليلاً، وثلاثة يومياً كحد أقصى (الإرسال الجماعي له حده الأسبوعي الخاص).
        if ($group === NotificationGroups::OFFERS && $n->type !== 'broadcast') {
            $now = now()->timezone(config('app.timezone'));
            if (self::isQuietHour($now->hour)) {
                return 'skipped_quiet';
            }

            $sentToday = AppNotification::where('user_id', $user->id)
                ->where('group', NotificationGroups::OFFERS)
                ->where('type', '!=', 'broadcast')
                ->where('push_status', 'sent')
                ->where('pushed_at', '>=', $now->copy()->startOfDay())
                ->count();
            if ($sentToday >= self::OFFERS_PER_DAY) {
                return 'skipped_limit';
            }
        }

        return self::SEND;
    }

    public const OFFERS_PER_DAY = 3;

    public const QUIET_FROM = 22;

    public const QUIET_UNTIL = 9;

    /** 22:00 حتى 08:59 بتوقيت التطبيق. */
    public static function isQuietHour(int $hour): bool
    {
        return $hour >= self::QUIET_FROM || $hour < self::QUIET_UNTIL;
    }
}
