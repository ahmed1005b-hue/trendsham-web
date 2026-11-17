<?php

namespace App\Auth\TwoFactor;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mpdf\QrCode\Output\Svg;
use Mpdf\QrCode\QrCode;

/** تفعيل التحقق بخطوتين للمدير والتحقق من رموزه. */
class TwoFactorService
{
    public const RECOVERY_CODES = 8;

    /** هل يجب على هذا المستخدم إعداد التحقق بخطوتين قبل دخول لوحته؟ */
    public static function requiredFor(User $user): bool
    {
        return $user->role === 'admin' && config('security.admin_two_factor_required', true);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $uri = Totp::uri($secret, $user->email ?: (string) $user->phone, config('app.name', 'TrendSham'));

        return (new Svg)->output(new QrCode($uri), 220, 'white', 'black');
    }

    /**
     * يعتمد السرّ بعد أن يُدخل المدير رمزاً صحيحاً منه، ويعيد رموز الاسترداد (تُعرض مرة واحدة).
     *
     * @return array<int, string>|null
     */
    public function confirm(User $user, string $secret, string $code): ?array
    {
        $step = Totp::match($secret, $code);
        if ($step === null) {
            return null;
        }

        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();
        Cache::put($this->lastStepKey($user), $step, now()->addMinutes(5));

        return $codes;
    }

    /** رمز من التطبيق أو رمز استرداد (يُستهلك مرة واحدة). */
    public function verify(User $user, string $input): bool
    {
        if (! $user->hasTwoFactor()) {
            return false;
        }

        $step = Totp::match($user->two_factor_secret, $input);
        if ($step !== null) {
            // نفس الرمز لا يُقبل مرتين (من اعترضه لا يستطيع إعادة استعماله)
            $last = Cache::get($this->lastStepKey($user));
            if ($last !== null && $step <= (int) $last) {
                return false;
            }
            Cache::put($this->lastStepKey($user), $step, now()->addMinutes(5));

            return true;
        }

        return $this->useRecoveryCode($user, $input);
    }

    /** @return array<int, string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    private function useRecoveryCode(User $user, string $input): bool
    {
        $input = strtoupper(trim($input));
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $code) {
            if (hash_equals($code, $input)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->all();
    }

    private function lastStepKey(User $user): string
    {
        return 'two-factor:last-step:'.$user->id;
    }
}
