<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\StoreShippingSetting;
use App\Shipping\Governorates;
use App\Shipping\SelfDelivery;
use App\Shipping\StoreShippingForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function update(Request $request, StoreShippingForm $form): RedirectResponse
    {
        $data = $request->validate(StoreShippingForm::rules(), StoreShippingForm::messages());
        $form->save($request->user()->sellerProfile, $data);

        return redirect()->route('seller.shipping.edit')->with('success', __('تم حفظ إعدادات الشحن.'));
    }
}
