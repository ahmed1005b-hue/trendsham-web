<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\StoreShippingRate;
use App\Models\StoreShippingSetting;
use App\Shipping\Governorates;
use App\Shipping\SelfDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** إعدادات شحن المتجر: من يوصل طروده، وبكم لكل محافظة، والشحن المجاني، والاستلام من المحل. */
class ShippingSettingsController extends Controller
{
    public function edit(Request $request, SelfDelivery $selfDelivery): View
    {
        $seller = $request->user()->sellerProfile->load('shippingSetting', 'shippingRates', 'wallet');

        return view('seller.shipping.edit', [
            'setting' => $seller->shippingSetting ?? new StoreShippingSetting(['mode' => 'platform', 'prep_days' => 1, 'delivery_days' => 2]),
            'rates' => $seller->shippingRates->pluck('fee', 'governorate'),
            'governorates' => Governorates::ALL,
            'status' => $selfDelivery->status($seller),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(StoreShippingSetting::MODES)],
            'rates' => ['nullable', 'array'],
            'rates.*.enabled' => ['nullable', 'boolean'],
            'rates.*.fee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'free_over' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'prep_days' => ['required', 'integer', 'min:0', 'max:14'],
            'delivery_days' => ['required', 'integer', 'min:0', 'max:14'],
            'allow_pickup' => ['nullable', 'boolean'],
            'pickup_address' => ['nullable', 'required_if_accepted:allow_pickup', 'string', 'max:500'],
        ], [
            'pickup_address.required_if_accepted' => __('اكتب عنوان محلك حتى يعرف الزبون أين يستلم طلبه.'),
        ]);

        $rates = collect($data['rates'] ?? [])
            ->only(Governorates::ALL)
            ->filter(fn ($row) => ! empty($row['enabled']))
            ->map(fn ($row) => (int) round(((float) ($row['fee'] ?? 0)) * 100));

        if ($data['mode'] === 'self' && $rates->isEmpty()) {
            return back()->withInput()->withErrors(['rates' => __('اختر محافظة واحدة على الأقل توصل إليها بنفسك، واكتب سعرها.')]);
        }

        $seller = $request->user()->sellerProfile;
        DB::transaction(function () use ($seller, $data, $rates) {
            StoreShippingSetting::updateOrCreate(['seller_profile_id' => $seller->id], [
                'mode' => $data['mode'],
                'free_over' => isset($data['free_over']) && (float) $data['free_over'] > 0 ? (int) round($data['free_over'] * 100) : null,
                'prep_days' => (int) $data['prep_days'],
                'delivery_days' => (int) $data['delivery_days'],
                'allow_pickup' => (bool) ($data['allow_pickup'] ?? false),
                'pickup_address' => ($data['allow_pickup'] ?? false) ? trim((string) $data['pickup_address']) : null,
            ]);

            StoreShippingRate::where('seller_profile_id', $seller->id)->whereNotIn('governorate', $rates->keys())->delete();
            foreach ($rates as $governorate => $fee) {
                StoreShippingRate::updateOrCreate(['seller_profile_id' => $seller->id, 'governorate' => $governorate], ['fee' => $fee]);
            }
        });

        return redirect()->route('seller.shipping.edit')->with('success', __('تم حفظ إعدادات الشحن.'));
    }
}
