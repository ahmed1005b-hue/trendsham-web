<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\SellerProfile;
use App\Models\SellerSupportGrant;
use App\Models\StaffActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * «وضع الدعم»: البائع يأذن لفريق الدعم بفتح لوحة متجره لحل مشكلة.
 *
 * - لا مدة للإذن: يبقى حتى يُنهيه البائع، وعندها يُعاد الموظف إلى حسابه مع طلبه التالي.
 * - الموظف يعمل بجلسة البائع (لأن كل صفحات اللوحة تأخذ المتجر من الحساب الداخل)،
 *   وجلسته تحمل علامة الدعم: GuardSupportAccess يفحص الإذن مع كل طلب، ويسمح بصفحات المتجر
 *   في ALLOWED_ROUTES فقط، ويسجّل كل تغيير باسم الموظف.
 * - ممنوع حتى مع الإذن: المحفظة والسحب وبيانات البنك، التحقق من الهوية، الحساب وكلمة السر،
 *   محادثات العملاء الخاصة، حذف المنتجات، وحجز مساحات العرض المدفوعة.
 */
class SellerSupportAccess
{
    public const SESSION_KEY = 'support_access';

    /**
     * صفحات لوحة البائع المتاحة لفريق الدعم، ووصف كل تغيير يُسجَّل فيها.
     * القيمة null: صفحة عرض لا تُسجَّل.
     *
     * @var array<string, string|null>
     */
    public const ALLOWED_ROUTES = [
        'seller.dashboard' => null,
        'seller.analytics' => null,
        'seller.products.index' => null,
        'seller.products.create' => null,
        'seller.products.edit' => null,
        'seller.products.store' => 'أضاف منتجاً',
        'seller.products.update' => 'عدّل المنتج',
        'seller.products.images.destroy' => 'حذف صورة من المنتج',
        'seller.products.images.setPrimary' => 'غيّر الصورة الرئيسية للمنتج',
        'seller.assistant.describe' => 'كتب وصف منتج بالمساعد الذكي',
        'seller.orders.index' => null,
        'seller.orders.show' => null,
        'seller.orders.label' => null,
        'seller.orders.updateStatus' => 'غيّر حالة الطلب',
        'seller.returns.index' => null,
        'seller.returns.show' => null,
        'seller.returns.approve' => 'وافق على الإرجاع',
        'seller.returns.reject' => 'رفض الإرجاع',
        'seller.returns.received' => 'أكّد استلام المرتجع',
        'seller.shipping.edit' => null,
        'seller.shipping.update' => 'عدّل إعدادات الشحن',
        'seller.store.edit' => null,
        'seller.store.update' => 'عدّل معلومات المتجر',
        'seller.coupons.index' => null,
        'seller.coupons.create' => null,
        'seller.coupons.show' => null,
        'seller.coupons.edit' => null,
        'seller.coupons.store' => 'أنشأ كوبوناً',
        'seller.coupons.update' => 'عدّل كوبوناً',
        'seller.coupons.toggle' => 'شغّل أو أوقف كوبوناً',
        'seller.coupons.destroy' => 'حذف كوبوناً',
        'seller.daily-deals.index' => null,
        'seller.daily-deals.create' => null,
        'seller.daily-deals.edit' => null,
        'seller.daily-deals.store' => 'أضاف عرضاً يومياً',
        'seller.daily-deals.update' => 'عدّل عرضاً يومياً',
        'seller.daily-deals.toggle' => 'شغّل أو أوقف عرضاً يومياً',
        'seller.daily-deals.destroy' => 'حذف عرضاً يومياً',
        'seller.stories.index' => null,
        'seller.stories.create' => null,
        'seller.stories.store' => 'نشر قصة',
        'seller.stories.destroy' => 'حذف قصة',
        'seller.questions.index' => null,
        'seller.questions.answer' => 'أجاب على سؤال عن منتج',
        'seller.showcases.index' => null,
        'seller.showcases.show' => null,
        'seller.showcases.create' => null,
        'seller.showcases.slots-availability' => null,
        'seller.showcases.preview-colors' => null,
    ];

    /**
     * صفحات بلا تسجيل دخول لكنها تقرأ بيانات الحساب الداخل الخاصة، فتُغلق في وضع الدعم.
     *
     * @var list<string>
     */
    public const PRIVATE_PUBLIC_ROUTES = ['assistant.'];

    public function __construct(private StaffActivityLog $log) {}

    public function activeGrant(SellerProfile $store): ?SellerSupportGrant
    {
        return SellerSupportGrant::query()->active()
            ->where('seller_profile_id', $store->id)
            ->latest('id')
            ->first();
    }

    /** البائع يفتح متجره لفريق الدعم (لا يتكرر إن كان مفتوحاً). */
    public function grant(SellerProfile $store, User $seller): SellerSupportGrant
    {
        $grant = DB::transaction(function () use ($store, $seller) {
            SellerProfile::query()->whereKey($store->id)->lockForUpdate()->first();

            return $this->activeGrant($store) ?? SellerSupportGrant::create([
                'seller_profile_id' => $store->id,
                'granted_by' => $seller->id,
            ]);
        });

        if ($grant->wasRecentlyCreated) {
            $this->notifyTeam($store);
        }

        return $grant;
    }

    /** البائع يُنهي الإذن: يُغلق عند الموظف مع طلبه التالي. */
    public function revoke(SellerProfile $store, User $seller): void
    {
        SellerSupportGrant::query()->active()
            ->where('seller_profile_id', $store->id)
            ->update(['revoked_at' => now(), 'revoked_by' => $seller->id]);
    }

    public function canEnter(User $staff, SellerProfile $store): bool
    {
        return $staff->isPlatformStaff() && $staff->canWorkOn('support')
            && $store->user !== null && $this->activeGrant($store) !== null;
    }

    /** الموظف يفتح لوحة المتجر: يدخل بجلسة البائع وتحمل الجلسة علامة الدعم. */
    public function enter(Request $request, User $staff, SellerProfile $store): void
    {
        $grant = $this->activeGrant($store);
        abort_unless($grant !== null && $this->canEnter($staff, $store), 403, __('البائع لم يسمح بفتح لوحة متجره.'));

        $this->log->record($staff, 'store.enter', $store, ['store' => $store->store_name]);

        Auth::guard('web')->login($store->user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'grant_id' => $grant->id,
            'staff_id' => $staff->id,
            'store_id' => $store->id,
        ]);
    }

    /**
     * علامة الدعم في الجلسة الحالية، أو null.
     *
     * @return array{grant_id: int, staff_id: int, store_id: int}|null
     */
    public function current(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $support = $request->session()->get(self::SESSION_KEY);

        return is_array($support) ? $support : null;
    }

    /** هل ما زال الإذن قائماً، والموظف ما زال في فريق الدعم، والجلسة على حساب صاحب المتجر؟ */
    public function stillAllowed(Request $request, array $support): bool
    {
        $grant = SellerSupportGrant::query()->with('store')->find($support['grant_id'] ?? 0);
        $staff = User::query()->find($support['staff_id'] ?? 0);

        return $grant !== null && $grant->isActive()
            && $grant->seller_profile_id === ($support['store_id'] ?? null)
            && $grant->store !== null
            && $request->user()?->id === $grant->store->user_id
            && $staff !== null && $staff->isPlatformStaff() && $staff->canWorkOn('support');
    }

    /**
     * الخروج من لوحة المتجر: يعود الموظف إلى حسابه (أو يُخرج تماماً إن لم يعد في الفريق).
     *
     * @return User|null الموظف بعد عودته إلى حسابه
     */
    public function leave(Request $request): ?User
    {
        $support = $this->current($request);
        $request->session()->forget(self::SESSION_KEY);

        $staff = User::query()->find($support['staff_id'] ?? 0);
        $store = SellerProfile::query()->find($support['store_id'] ?? 0);

        if ($staff !== null && $staff->isPlatformStaff()) {
            if ($store !== null) {
                $this->log->record($staff, 'store.leave', $store, ['store' => $store->store_name]);
            }
            Auth::guard('web')->login($staff);
            $request->session()->regenerate();

            return $staff;
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return null;
    }

    /** هل الصفحة متاحة لفريق الدعم داخل لوحة المتجر؟ الصفحات العامة (بلا تسجيل دخول) متاحة دائماً. */
    public function routeAllowed(?Route $route): bool
    {
        if ($route === null) {
            return false;
        }

        $name = $route->getName();
        if ($name !== null && array_key_exists($name, self::ALLOWED_ROUTES)) {
            return true;
        }

        // صفحات عامة تعمل باسم الحساب الداخل: المساعد الذكي يجيب البائع عن رصيده ويحفظ محادثاته الخاصة
        if ($name !== null && collect(self::PRIVATE_PUBLIC_ROUTES)->contains(fn (string $prefix) => str_starts_with($name, $prefix))) {
            return false;
        }

        $needsLogin = collect($route->gatherMiddleware())
            ->contains(fn ($middleware) => is_string($middleware)
                && ($middleware === 'auth' || str_starts_with($middleware, 'auth:') || str_starts_with($middleware, 'role:')));

        return ! $needsLogin;
    }

    /** يسجّل تغييراً قام به الموظف داخل لوحة المتجر، باسمه هو. */
    public function recordChange(Request $request, array $support): void
    {
        $what = self::ALLOWED_ROUTES[$request->route()?->getName()] ?? null;
        $staff = User::query()->find($support['staff_id'] ?? 0);
        $store = SellerProfile::query()->find($support['store_id'] ?? 0);

        if ($what === null || $staff === null || $store === null) {
            return;
        }

        $this->log->record($staff, 'store.change', $store, [
            'store' => $store->store_name,
            'what' => $what,
            'item' => $this->itemName($request) ?? '',
        ]);
    }

    /**
     * ما فعله فريق الدعم في المتجر، للبائع (بلا أسماء الموظفين: الدعم «فريق» عند البائع).
     *
     * @return Collection<int, StaffActivity>
     */
    public function recentActions(SellerProfile $store, int $limit = 30): Collection
    {
        return StaffActivity::query()
            ->where('subject_type', 'store')
            ->where('subject_id', $store->id)
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** وصف السطر للبائع بلغته، مثل: «عدّل المنتج «قميص»». */
    public function describeForSeller(StaffActivity $activity): string
    {
        $properties = $activity->properties ?? [];

        return match ($activity->action) {
            'store.enter' => __('فتح فريق الدعم لوحة متجرك'),
            'store.leave' => __('خرج فريق الدعم من لوحة متجرك'),
            default => trim(__((string) ($properties['what'] ?? '')).(($properties['item'] ?? '') !== '' ? ' «'.$properties['item'].'»' : '')),
        };
    }

    private function itemName(Request $request): ?string
    {
        foreach (['product', 'order', 'returnRequest', 'campaign'] as $parameter) {
            $value = $request->route($parameter);
            if (! $value instanceof Model) {
                continue;
            }

            $attributes = $value->getAttributes();
            $name = $attributes['name'] ?? $attributes['order_number'] ?? $attributes['number'] ?? $attributes['title'] ?? null;

            return is_scalar($name) ? mb_substr((string) $name, 0, 120) : null;
        }

        return null;
    }

    private function notifyTeam(SellerProfile $store): void
    {
        foreach (app(SellerSupportTeam::class)->members() as $member) {
            AppNotification::send(
                $member->id,
                'staff_message',
                __('متجر :store سمح لفريق الدعم بفتح لوحته', ['store' => $store->store_name]),
                __('افتح لوحة المتجر من صفحة دعم البائعين لحل المشكلة.'),
                route($member->isAdmin() ? 'admin.support.index' : 'employee.support.index')
            );
        }
    }
}
