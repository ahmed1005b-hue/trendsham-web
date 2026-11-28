<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Services\SellerSupportAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** فريق الدعم (المدير والموظف في قسم الدعم) يفتح لوحة متجر سمح بائعه بذلك، ثم يخرج منها. */
class SupportAccessController extends Controller
{
    public function __construct(private SellerSupportAccess $access) {}

    public function enter(Request $request, SellerProfile $store): RedirectResponse
    {
        $this->access->enter($request, $request->user(), $store);

        return redirect()->route('seller.dashboard');
    }

    /** يصل إليه الموظف خارج وضع الدعم فقط (داخله يعالجه GuardSupportAccess)، فلا شيء يُغلق. */
    public function leave(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()?->isAdmin() ? 'admin.support.index' : 'employee.support.index');
    }
}
