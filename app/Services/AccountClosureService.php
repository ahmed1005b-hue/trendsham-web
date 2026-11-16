<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * إيقاف الحساب مؤقتاً، وحذفه نهائياً بعد مهلة.
 *
 * - الإيقاف: خروج من كل الأجهزة، ويعود الحساب بمجرد تسجيل الدخول مجدداً.
 * - الحذف (للعملاء): يُغلق الحساب فوراً، وتُمسح بياناته الشخصية بعد GRACE_DAYS يوماً؛
 *   تسجيل الدخول خلال المهلة يلغي الطلب.
 * - البائعون والموظفون: الطلب يصل للإدارة فقط (للمتجر أرصدة وطلبات وسجلات).
 *
 * الطلبات والحركات المالية والتقييمات لا تُحذف: هي سجلات تخص البائع والمحاسبة،
 * وتظهر بعد المسح باسم "مستخدم محذوف".
 */
class AccountClosureService
{
    public const GRACE_DAYS = 30;

    /** طلبات لم تنتهِ بعد: لا يُحذف حساب صاحبها حتى تُسلَّم أو تُلغى. */
    public const OPEN_ORDER_STATUSES = ['pending', 'paid', 'processing', 'shipped'];

    /** رقم الهاتف بعد المسح (الهاتف إلزامي وفريد، فنحرّره لإعادة التسجيل به). */
    public const PURGED_PHONE_PREFIX = 'deleted-';

    /** كلمة التأكيد كما تظهر بلغة الواجهة: حذف / Delete / Sil. */
    public const CONFIRM_WORDS = ['حذف', 'delete', 'sil'];

    /** قاعدة تحقق لحقل كلمة التأكيد (الموقع والتطبيق). */
    public static function confirmRule(): array
    {
        return ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
            if (! in_array(mb_strtolower(trim((string) $value)), self::CONFIRM_WORDS, true)) {
                $fail('اكتب كلمة «حذف» للتأكيد.');
            }
        }];
    }

    public function canSelfDelete(User $user): bool
    {
        return $user->role === 'customer';
    }

    public function isPurged(User $user): bool
    {
        return str_starts_with((string) $user->phone, self::PURGED_PHONE_PREFIX);
    }

    /** أسباب تمنع حذف حساب العميل الآن (فارغة = مسموح). */
    public function deletionBlockers(User $user): array
    {
        $open = $user->orders()->whereIn('status', self::OPEN_ORDER_STATUSES)->count();

        return $open > 0
            ? [__($open === 1 ? 'لديك :n طلب قيد التنفيذ. انتظر تسليمها أو ألغِها أولاً.' : 'لديك :n طلبات قيد التنفيذ. انتظر تسليمها أو ألغِها أولاً.', ['n' => $open])]
            : [];
    }

    /** التحقق من هوية صاحب الحساب قبل الإيقاف أو الحذف. */
    public function confirmIdentity(User $user, ?string $password): void
    {
        // حسابات الدخول برمز SMS بلا كلمة سر: يكفي التأكيد الصريح في النموذج
        if ($user->password === null) {
            return;
        }
        if ($password === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'كلمة السر غير صحيحة.']);
        }
    }

    public function deactivate(User $user): void
    {
        $user->forceFill(['deactivated_at' => now()])->save();
        $this->signOutEverywhere($user);
    }

    /**
     * @return string 'scheduled' للعميل (يُحذف بعد المهلة) أو 'requested' للبائع/الموظف (تراجعه الإدارة)
     */
    public function requestDeletion(User $user): string
    {
        if (! $this->canSelfDelete($user)) {
            if ($user->role === 'admin') {
                throw new \DomainException('لا يمكن حذف حساب مدير من هنا.');
            }
            $user->forceFill(['deletion_requested_at' => $user->deletion_requested_at ?? now()])->save();

            return 'requested';
        }

        if ($blockers = $this->deletionBlockers($user)) {
            throw new \DomainException($blockers[0]);
        }

        $user->forceFill(['deletion_requested_at' => now(), 'deactivated_at' => now()])->save();
        $this->signOutEverywhere($user);

        return 'scheduled';
    }

    public function purgeDate(User $user): ?\Illuminate\Support\Carbon
    {
        return $user->deletion_requested_at?->copy()->addDays(self::GRACE_DAYS);
    }

    /**
     * عند كل تسجيل دخول ناجح: يعيد تفعيل الحساب الموقوف ويلغي طلب الحذف المعلّق.
     * يعيد رسالة لعرضها للمستخدم، أو null.
     */
    public function onLogin(User $user): ?string
    {
        if ($this->canSelfDelete($user) && $user->deletion_requested_at !== null) {
            $user->forceFill(['deletion_requested_at' => null, 'deactivated_at' => null])->save();

            return 'أهلاً بعودتك! أُلغي طلب حذف حسابك وعاد حسابك يعمل كالمعتاد.';
        }

        if ($user->deactivated_at !== null) {
            $user->forceFill(['deactivated_at' => null])->save();

            return 'أهلاً بعودتك! أُعيد تفعيل حسابك.';
        }

        return null;
    }

    /** خروج من كل الأجهزة: التوكنات والجلسات وإشعارات الهاتف وكوكي "تذكرني". */
    public function signOutEverywhere(User $user): void
    {
        $user->tokens()->delete();
        $user->pushDevices()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        // بدونها يعيد كوكي "تذكرني" الدخول بصمت دون المرور بتسجيل الدخول
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    /** يمسح حسابات العملاء التي انتهت مهلتها. يعيد عدد الحسابات الممسوحة. */
    public function purgeDue(): int
    {
        $count = 0;
        User::query()
            ->where('role', 'customer')
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', now()->subDays(self::GRACE_DAYS))
            ->where('phone', 'not like', self::PURGED_PHONE_PREFIX.'%')
            ->each(function (User $user) use (&$count) {
                // طلب فُتح خلال المهلة (نادر: عبر الإدارة) يؤجل المسح
                if ($this->deletionBlockers($user)) {
                    return;
                }
                $this->purge($user);
                $count++;
            });

        return $count;
    }

    /** مسح البيانات الشخصية مع الإبقاء على السجلات المالية والطلبات. */
    public function purge(User $user): void
    {
        $avatar = $user->avatar;

        DB::transaction(function () use ($user) {
            $id = $user->id;
            $user->addresses()->delete();
            $user->cartItems()->delete();
            $user->favorites()->delete();
            $user->followedStores()->detach();
            $user->customerCards()->delete();
            DB::table('notifications')->where('user_id', $id)->delete();
            $this->signOutEverywhere($user);

            // كتابة مباشرة: محوّل الهاتف في النموذج يحوّل "deleted-<id>" إلى null
            DB::table('users')->where('id', $id)->update([
                'updated_at' => now(),
                'first_name' => 'مستخدم',
                'last_name' => 'محذوف',
                'phone' => self::PURGED_PHONE_PREFIX.$id,
                'country_code' => null,
                'national_number' => null,
                'email' => null,
                'password' => null,
                'birth_date' => null,
                'city' => null,
                'gender' => null,
                'address' => null,
                'avatar' => null,
                'phone_verified_at' => null,
                'notification_prefs' => null,
                'status' => 'suspended',
                'deactivated_at' => $user->deactivated_at ?? now(),
                'remember_token' => Str::random(60),
            ]);
        });
        $user->refresh();

        if ($avatar) {
            Storage::disk('public')->delete($avatar);
        }
    }
}
