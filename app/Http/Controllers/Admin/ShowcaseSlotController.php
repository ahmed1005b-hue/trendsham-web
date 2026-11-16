<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShowcaseSlot;
use Illuminate\Http\Request;

class ShowcaseSlotController extends Controller
{
    public function index()
    {
        return view('admin.showcase-slots.index', [
            'slots' => ShowcaseSlot::orderBy('id')->get(),
        ]);
    }

    public function edit(ShowcaseSlot $showcaseSlot)
    {
        return view('admin.showcase-slots.edit', [
            'slot' => $showcaseSlot,
        ]);
    }

    public function update(Request $request, ShowcaseSlot $showcaseSlot)
    {
        $validated = $request->validate([
            'label_ar' => ['required', 'string', 'max:255'],
            'width_px' => ['required', 'integer', 'min:100'],
            'height_px' => ['required', 'integer', 'min:100'],
            'base_price' => ['required', 'integer', 'min:0'],
            'price_per_product' => ['required', 'integer', 'min:0'],
            'price_per_day' => ['required', 'integer', 'min:0'],
            'min_products' => ['required', 'integer', 'min:1'],
            'max_products' => ['required', 'integer', 'gte:min_products'],
            'min_days' => ['required', 'integer', 'min:1'],
            'max_days' => ['required', 'integer', 'gte:min_days'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $showcaseSlot->update($validated);

        return redirect()->route('admin.showcase-slots.index')->with('success', 'تم تحديث مساحة العرض.');
    }
}