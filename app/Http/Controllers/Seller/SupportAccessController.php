<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SellerSupportAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** البائع يسمح لفريق الدعم بفتح لوحة متجره، أو يُنهي الإذن. */
class SupportAccessController extends Controller
{
    public function __construct(private SellerSupportAccess $access) {}

    public function store(Request $request): RedirectResponse
    {
        $this->access->grant($request->user()->sellerProfile, $request->user());

        return redirect()->route('seller.support.index')
            ->with('success', __('سمحت لفريق الدعم بفتح لوحة متجرك. يبقى الإذن حتى تُنهيه.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->access->revoke($request->user()->sellerProfile, $request->user());

        return redirect()->route('seller.support.index')
            ->with('success', __('أنهيت الإذن. لم يعد فريق الدعم يستطيع فتح لوحة متجرك.'));
    }
}
