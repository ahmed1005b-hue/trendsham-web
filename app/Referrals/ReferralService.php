<?php

namespace App\Referrals;

use App\Loyalty\LoyaltyCalculator;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\LoyaltySettings;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\User;
use App\Referrals\Models\Referral;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * «دعوة صديق»: الصديق الجديد يُدخل كود الداعي قبل أول طلب، وعند تسليم أول طلب
 * مؤهل له يأخذ كلاهما نقاط ولاء. إلغاء ذلك الطلب بعد التسليم يسحب النقاط.
 */
class ReferralService
{
    public function __construct(
        private readonly LoyaltyLedger $ledger,
        private readonly LoyaltyCalculator $calc,
        private readonly LoyaltySettings $settings,
    ) {}

    public static function rewardPoints(): int
    {
        return max(0, (int) config('referral.reward_points'));
    }

    public static function minOrder(): int
    {
        return max(0, (int) config('referral.min_order'));
    }

    /** هل ما زال الحساب جديداً بما يكفي لإدخال كود دعوة؟ */
    public function canApply(User $user): bool
    {
        return $user->role === 'customer'
            && ! Referral::where('referee_id', $user->id)->exists()
            && ! $user->orders()->exists()
            && $user->created_at?->gte(now()->subDays((int) config('referral.max_account_age_days')));
    }

    /** @throws ReferralException */
    public function apply(User $referee, string $code): Referral
    {
        $referrerId = ReferralCode::decode($code);
        $referrer = $referrerId ? User::find($referrerId) : null;
        if (! $referrer || $referrer->role !== 'customer' || $referrer->status !== 'active') {
            throw new ReferralException('كود الدعوة غير صحيح.');
        }
        if ($referrer->id === $referee->id) {
            throw new ReferralException('لا يمكنك استخدام كود دعوتك.');
        }
        if (Referral::where('referee_id', $referee->id)->exists()) {
            throw new ReferralException('سبق أن أدخلت كود دعوة.');
        }
        if (! $this->canApply($referee)) {
            throw new ReferralException('كود الدعوة للحسابات الجديدة فقط، قبل أول طلب.');
        }

        try {
            $referral = Referral::create(['referrer_id' => $referrer->id, 'referee_id' => $referee->id]);
        } catch (UniqueConstraintViolationException) {
            throw new ReferralException('سبق أن أدخلت كود دعوة.');
        }

        $this->notify($referrer->id, 'referral_joined', 'انضم صديق عبر دعوتك',
            'ستحصلان معاً على '.LoyaltyCalculator::pointsLabel(self::rewardPoints()).' بعد أن يصله أول طلب.');

        return $referral;
    }

    /** يُستدعى عند أول تسليم لطلب. */
    public function rewardForDeliveredOrder(Order $order): void
    {
        $points = self::rewardPoints();
        if ($points <= 0 || ! $this->settings->enabled() || $this->calc->paidBase($order) < self::minOrder()) {
            return;
        }

        $rewarded = DB::transaction(function () use ($order, $points) {
            $referral = Referral::where('referee_id', $order->user_id)->where('status', Referral::PENDING)
                ->lockForUpdate()->first();
            if (! $referral) {
                return null;
            }

            $referrerPoints = $this->referrerUnderCap($referral->referrer_id) ? $points : 0;
            $reason = 'مكافأة دعوة صديق';
            $this->ledger->credit($referral->referee_id, Tx::REFERRAL, $points, reason: $reason,
                onceKey: 'referral:'.$referral->id.':referee');
            if ($referrerPoints > 0) {
                $this->ledger->credit($referral->referrer_id, Tx::REFERRAL, $referrerPoints, reason: $reason,
                    onceKey: 'referral:'.$referral->id.':referrer');
            }

            $referral->update([
                'status' => Referral::REWARDED, 'order_id' => $order->id, 'rewarded_at' => now(),
                'referee_points' => $points, 'referrer_points' => $referrerPoints,
            ]);

            return $referral;
        });

        if ($rewarded) {
            $label = LoyaltyCalculator::pointsLabel($points);
            $this->notify($rewarded->referee_id, 'referral_rewarded', 'حصلت على '.$label.' مكافأة دعوة', 'شكراً لانضمامك. النقاط في رصيدك الآن.');
            if ($rewarded->referrer_points > 0) {
                $this->notify($rewarded->referrer_id, 'referral_rewarded', 'حصلت على '.$label.' مكافأة دعوة', 'وصل أول طلب لصديقك الذي دعوته.');
            }
        }
    }

    /** إلغاء الطلب الذي استحق المكافأة (بعد تسليمه) يسحب نقاط الاثنين. */
    public function reverseForCancelledOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $referral = Referral::where('order_id', $order->id)->where('status', Referral::REWARDED)
                ->lockForUpdate()->first();
            if (! $referral) {
                return;
            }

            foreach (['referee' => $referral->referee_points, 'referrer' => $referral->referrer_points] as $side => $points) {
                if ($points > 0) {
                    $this->ledger->debit($referral->{$side.'_id'}, Tx::REFERRAL_REVERSE, $points,
                        reason: 'سحب مكافأة دعوة بسبب إرجاع الطلب',
                        onceKey: 'referral_reverse:'.$referral->id.':'.$side, allowDebt: true);
                }
            }

            $referral->update(['status' => Referral::REVERSED]);
        });
    }

    /**
     * ما تعرضه صفحة «دعوة صديق» (الموقع والتطبيق). المبالغ بالوحدة الصغرى.
     *
     * @return array{code: string, link: string, share_text: string, reward_points: int, min_order: int, can_apply: bool, referred_by: ?array{name: string, status: string, status_label: string}, stats: array{joined: int, rewarded: int, points_earned: int}, invited: list<array{name: string, status: string, status_label: string, joined_at: string}>}
     */
    public function summary(User $user): array
    {
        $code = ReferralCode::for($user->id);
        $link = route('referrals.invite', $code);
        $all = Referral::where('referrer_id', $user->id);
        $referredBy = Referral::where('referee_id', $user->id)->with('referrer:id,first_name')->first();

        return [
            'code' => $code,
            'link' => $link,
            'share_text' => 'تسوّق معي على TrendSham! سجّل بكود الدعوة '.$code.' واحصل على '
                .LoyaltyCalculator::pointsLabel(self::rewardPoints()).' بعد أول طلب: '.$link,
            'reward_points' => self::rewardPoints(),
            'min_order' => self::minOrder(),
            'can_apply' => $this->canApply($user),
            'referred_by' => $referredBy ? [
                'name' => (string) $referredBy->referrer?->first_name,
                'status' => $referredBy->status,
                'status_label' => $referredBy->statusLabel(),
            ] : null,
            'stats' => [
                'joined' => (clone $all)->count(),
                'rewarded' => (clone $all)->where('status', Referral::REWARDED)->count(),
                'points_earned' => (int) (clone $all)->where('status', Referral::REWARDED)->sum('referrer_points'),
            ],
            'invited' => (clone $all)->with('referee:id,first_name,last_name')->latest('id')->limit(50)->get()
                ->map(fn (Referral $r) => [
                    'name' => trim($r->referee?->first_name.' '.mb_substr((string) $r->referee?->last_name, 0, 1)),
                    'status' => $r->status,
                    'status_label' => $r->statusLabel(),
                    'joined_at' => $r->created_at->toDateString(),
                ])->all(),
        ];
    }

    private function referrerUnderCap(int $referrerId): bool
    {
        return Referral::where('referrer_id', $referrerId)->where('referrer_points', '>', 0)
            ->where('rewarded_at', '>=', now()->subMonth())
            ->count() < (int) config('referral.referrer_monthly_cap');
    }

    private function notify(int $userId, string $type, string $title, string $body): void
    {
        AppNotification::send($userId, $type, $title, $body,
            Route::has('customer.referrals.index') ? route('customer.referrals.index') : null);
    }
}
