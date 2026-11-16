<?php

namespace App\Notifications\Push;

use App\Models\AppNotification;
use App\Models\PushBroadcast;
use App\Models\PushDevice;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;

/**
 * إشعار جماعي من المدير لكل العملاء: مرة كل 7 أيام، يؤجَّل ليلاً إلى 09:00.
 * يصل الجرس لكل عميل، والهاتف فقط لمن فعّل إشعارات العروض.
 */
class BroadcastService
{
    public const EVERY_DAYS = 7;

    private const CHUNK = 500;

    public function __construct(private FcmClient $fcm, private PushPreferences $prefs) {}

    /** موعد الإرسال المتاح التالي، أو null إن كان متاحاً الآن. */
    public function nextAllowedAt(): ?Carbon
    {
        $last = PushBroadcast::latest('created_at')->value('created_at');
        if (! $last) {
            return null;
        }

        $next = Carbon::parse($last)->addDays(self::EVERY_DAYS);

        return $next->isFuture() ? $next : null;
    }

    public function create(array $data, User $admin): PushBroadcast
    {
        if ($next = $this->nextAllowedAt()) {
            throw new DomainException('أُرسل إشعار جماعي خلال آخر 7 أيام. الإرسال التالي متاح '
                .$next->timezone(config('app.timezone'))->translatedFormat('l j F، الساعة H:i').'.');
        }

        return PushBroadcast::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'link' => $data['link'] ?? null,
            'created_by' => $admin->id,
            'scheduled_for' => self::sendTimeFor(now()),
        ]);
    }

    /** الآن، أو 09:00 القادمة إن كان الوقت ليلاً. */
    public static function sendTimeFor(Carbon $at): Carbon
    {
        $local = $at->copy()->timezone(config('app.timezone'));
        if (! PushPolicy::isQuietHour($local->hour)) {
            return $at->copy();
        }

        $nine = $local->copy()->setTime(PushPolicy::QUIET_UNTIL, 0);
        if ($local->hour >= PushPolicy::QUIET_FROM) {
            $nine->addDay();
        }

        return $nine;
    }

    /** يرسل كل إشعار جماعي حان موعده. كل واحد يُرسل مرة واحدة فقط. يعيد عدد ما أُرسل. */
    public function sendDue(): int
    {
        $sent = 0;
        $due = PushBroadcast::whereNull('sent_at')->where('scheduled_for', '<=', now())->orderBy('id')->get();

        foreach ($due as $broadcast) {
            // حجز شرطي: تشغيلان متزامنان لا يرسلان نفس الإشعار مرتين.
            if (PushBroadcast::whereKey($broadcast->id)->whereNull('sent_at')->update(['sent_at' => now()]) !== 1) {
                continue;
            }
            $this->deliver($broadcast->fresh());
            $sent++;
        }

        return $sent;
    }

    /** عدد العملاء، ومن سيصلهم على الهاتف (فعّلوا العروض ولهم هاتف مسجّل). */
    public function audience(): array
    {
        $total = User::where('role', 'customer')->count();
        $reachable = User::where('role', 'customer')->whereHas('pushDevices')->get(['id', 'role', 'notification_prefs'])
            ->filter(fn (User $u) => $this->prefs->isEnabled($u, NotificationGroups::OFFERS) === true)->count();

        return ['total' => $total, 'reachable' => $reachable];
    }

    private function deliver(PushBroadcast $b): void
    {
        $target = PushTarget::fromLink($b->link);
        $configured = $this->fcm->isConfigured();
        $recipients = 0;
        $pushed = 0;

        User::where('role', 'customer')->select(['id', 'role', 'notification_prefs'])
            ->chunkById(self::CHUNK, function ($users) use ($b, $target, $configured, &$recipients, &$pushed) {
                $devices = PushDevice::whereIn('user_id', $users->pluck('id'))->get()->groupBy('user_id');
                $now = now();
                $rows = [];
                $toPush = [];

                foreach ($users as $user) {
                    $wantsOffers = $this->prefs->isEnabled($user, NotificationGroups::OFFERS) === true;
                    $status = match (true) {
                        ! $wantsOffers => 'skipped_disabled',
                        ! $devices->has($user->id) => 'no_device',
                        ! $configured => 'not_configured',
                        default => 'pending',
                    };
                    if ($status === 'pending') {
                        $toPush[] = $user->id;
                    }
                    $rows[] = [
                        'user_id' => $user->id, 'type' => 'broadcast', 'group' => NotificationGroups::OFFERS,
                        'title' => $b->title, 'body' => $b->body, 'link' => $b->link,
                        'target' => $target ? json_encode($target) : null,
                        'push_status' => $status, 'pushed_at' => $status === 'pending' ? $now : null,
                        'broadcast_id' => $b->id, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }

                AppNotification::insert($rows);
                $recipients += count($rows);
                if (! $toPush) {
                    return;
                }

                $ids = AppNotification::where('broadcast_id', $b->id)->whereIn('user_id', $toPush)->pluck('id', 'user_id');
                $ok = [];
                $failed = [];
                foreach ($toPush as $userId) {
                    $data = ['notification_id' => $ids[$userId], 'type' => 'broadcast',
                        'screen' => $target['screen'] ?? 'notifications', 'id' => $target['id'] ?? null];
                    $reached = false;
                    foreach ($devices[$userId] as $device) {
                        $result = $this->fcm->send($device->token, $b->title, $b->body, $data);
                        if ($result->ok) {
                            $reached = true;
                        } elseif ($result->invalidToken) {
                            $device->delete();
                        }
                    }
                    $reached ? $ok[] = $ids[$userId] : $failed[] = $ids[$userId];
                }

                if ($ok) {
                    AppNotification::whereIn('id', $ok)->update(['push_status' => 'sent', 'pushed_at' => now()]);
                }
                if ($failed) {
                    AppNotification::whereIn('id', $failed)->update(['push_status' => 'failed', 'pushed_at' => now()]);
                }
                $pushed += count($ok);
            });

        $b->update(['recipients_count' => $recipients, 'pushed_count' => $pushed]);
    }
}
