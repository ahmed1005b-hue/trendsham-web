<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingCompany;
use Illuminate\Http\Request;

class ShippingCompanyController extends Controller
{
    public function index()
    {
        return view('admin.shipping-companies.index', [
            'companies' => ShippingCompany::latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        ShippingCompany::create($validated);

        return back()->with('success', 'تمت إضافة شركة الشحن.');
    }

    public function toggle(ShippingCompany $shippingCompany)
    {
        $shippingCompany->update(['is_active' => ! $shippingCompany->is_active]);

        return back()->with('success', 'تم تحديث حالة الشركة.');
    }

    public function destroy(ShippingCompany $shippingCompany)
    {
        $shippingCompany->delete();

        return back()->with('success', 'تم حذف شركة الشحن.');
    }
}
