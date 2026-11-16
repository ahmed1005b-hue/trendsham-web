<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShowcaseSlot;
use Illuminate\Http\Request;

class ShowcaseSlotAdminController extends Controller
{
    public function index()
    {
        $slots = ShowcaseSlot::orderBy('position')->paginate(20);
        return view('admin.showcase-slots.index', compact('slots'));
    }

    public function create()
    {
        return view('admin.showcase-slots.create');
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'key'              => 'required|string|max:40|unique:showcase_slots,key',
            'name'             => 'required|string|max:100',
            'position'         => 'required|integer|min:0',
            'price_per_day'    => 'required|numeric|min:0',
            'min_days'         => 'required|integer|min:1',
            'max_days'         => 'required|integer|min:1|gte:min_days',
            'capacity'         => 'required|integer|min:1',
            'min_products'     => 'required|integer|min:1',
            'max_products'     => 'required|integer|min:1|gte:min_products',
            'is_active'        => 'boolean',
        ]);
        $data['is_active'] = $r->boolean('is_active');

        ShowcaseSlot::create($data);

        return redirect()->route('admin.showcase-slots.index')->with('success', 'تم إنشاء المساحة.');
    }

    public function edit(ShowcaseSlot $showcaseSlot)
    {
        return view('admin.showcase-slots.edit', compact('showcaseSlot'));
    }

    public function update(Request $r, ShowcaseSlot $showcaseSlot)
    {
        $data = $r->validate([
            'key'              => 'required|string|max:40|unique:showcase_slots,key,' . $showcaseSlot->id,
            'name'             => 'required|string|max:100',
            'position'         => 'required|integer|min:0',
            'price_per_day'    => 'required|numeric|min:0',
            'min_days'         => 'required|integer|min:1',
            'max_days'         => 'required|integer|min:1|gte:min_days',
            'capacity'         => 'required|integer|min:1',
            'min_products'     => 'required|integer|min:1',
            'max_products'     => 'required|integer|min:1|gte:min_products',
            'is_active'        => 'boolean',
        ]);
        $data['is_active'] = $r->boolean('is_active');

        $showcaseSlot->update($data);

        return redirect()->route('admin.showcase-slots.index')->with('success', 'تم تحديث المساحة.');
    }

    public function destroy(ShowcaseSlot $showcaseSlot)
    {
        // لا نسمح بالحذف إذا كانت هناك حجوزات - نعطل فقط
        if ($showcaseSlot->showcases()->exists()) {
            return back()->with('error', 'لا يمكن حذف هذه المساحة لوجود حجوزات مرتبطة بها. يمكنك تعطيلها فقط.');
        }

        $showcaseSlot->delete();

        return redirect()->route('admin.showcase-slots.index')->with('success', 'تم حذف المساحة.');
    }
}