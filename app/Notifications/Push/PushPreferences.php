<?php

namespace App\Notifications\Push;

use App\Models\User;

/**
 * إعدادات الإشعارات محفوظة في الحساب (users.notification_prefs = {group: bool}).
 * الغائب = مفعّل، عدا "العروض" للعميل: الغائب = لم يُحدَّد بعد (لا يُرسل حتى يوافق).
 */
class PushPreferences
{
    public function isEnabled(User $user, string $group): ?bool
    {
        $prefs = $user->notification_prefs ?? [];
        if (array_key_exists($group, $prefs)) {
            return (bool) $prefs[$group];
        }

        return $group === NotificationGroups::OFFERS ? null : true;
    }

    /** الشكل الذي يعيده الـ API (contracts/api.md). */
    public function for(User $user): array
    {
        return [
            'role' => $user->role,
            'groups' => collect(NotificationGroups::groupsFor((string) $user->role))
                ->map(fn (array $g) => $g + ['enabled' => $this->isEnabled($user, $g['key']), 'locked' => false])
                ->values()->all(),
            'always_on_note' => NotificationGroups::ALWAYS_ON_NOTE,
            'offers_prompted' => $user->offers_prompted_at !== null,
        ];
    }

    /**
     * عند أول متابعة لمتجر: هل نسأل العميل عن إشعارات العروض؟ مرة واحدة فقط،
     * ويُسجَّل أنه سُئل فوراً (فلا يتكرر السؤال حتى لو تجاهله).
     */
    public function askOffersOnFollow(User $user): bool
    {
        if (! $user->isCustomer() || $user->offers_prompted_at !== null
            || array_key_exists(NotificationGroups::OFFERS, $user->notification_prefs ?? [])) {
            return false;
        }

        $user->forceFill(['offers_prompted_at' => now()])->save();

        return true;
    }

    /** $groups: مفاتيح من مجموعات دور المستخدم فقط (يتحقق منها المتحكم). */
    public function update(User $user, array $groups): void
    {
        $prefs = $user->notification_prefs ?? [];
        foreach ($groups as $key => $enabled) {
            $prefs[$key] = (bool) $enabled;
        }

        $user->notification_prefs = $prefs;
        if (array_key_exists(NotificationGroups::OFFERS, $groups)) {
            $user->offers_prompted_at = now();
        }
        $user->save();
    }
}
