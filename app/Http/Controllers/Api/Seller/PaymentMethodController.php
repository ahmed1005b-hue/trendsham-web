<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** طرق استلام أرباح البائع (التطبيق). الرقم الكامل لا يُعاد أبداً — فقط آخر 4 أرقام. */
class PaymentMethodController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    public function index(Request $request)
    {
        return response()->json([
            'payment_methods' => $request->user()->sellerProfile->paymentMethods()->latest()->get()
                ->map->toApiArray()->values(),
        ]);
    }

    /** الأنواع والجهات المتاحة لقائمة الاختيار + حدود السحب */
    public function options()
    {
        return [
            'types' => collect(PaymentMethod::TYPES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'providers' => config('payment.payout.providers'),
            'min_amount' => (int) config('payment.payout.min_amount'),
            'require_verified_method' => (bool) config('payment.payout.require_verified_method'),
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate(PayoutService::methodRules());
        $method = $this->payouts->addMethod($request->user()->sellerProfile, $validated);

        return response()->json([
            'message' => 'تمت إضافة طريقة الاستلام بنجاح.',
            'payment_method' => $method->toApiArray(),
        ], 201);
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod)
    {
        Gate::authorize('manage', $paymentMethod);
        $this->payouts->removeMethod($paymentMethod);

        return response()->json(['message' => 'تم حذف طريقة الاستلام.']);
    }

    public function setDefault(Request $request, PaymentMethod $paymentMethod)
    {
        Gate::authorize('manage', $paymentMethod);
        $this->payouts->setDefaultMethod($paymentMethod);

        return response()->json(['message' => 'تم تعيين طريقة الاستلام الافتراضية.']);
    }
}
