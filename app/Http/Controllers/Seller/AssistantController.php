<?php

namespace App\Http\Controllers\Seller;

use App\Assistant\Seller\DescriptionWriter;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

/** مولّد وصف المنتج — زر "اكتب الوصف تلقائياً" في نموذج المنتج (الموقع والتطبيق) */
class AssistantController extends Controller
{
    public function describe(Request $request, DescriptionWriter $writer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'colors' => ['nullable', 'array', 'max:20'],
            'colors.*' => ['nullable', 'string', 'max:40'],
            'sizes' => ['nullable', 'array', 'max:20'],
            'sizes.*' => ['nullable', 'string', 'max:40'],
        ], ['name.required' => 'اكتب اسم المنتج أولاً.']);

        $category = $data['category'] ?? (isset($data['category_id']) ? Category::find($data['category_id'])?->name : null);

        return response()->json([
            'description' => $writer->write(
                $data['name'],
                $category,
                $data['colors'] ?? [],
                $data['sizes'] ?? [],
                $request->user()->sellerProfile?->store_name,
            ),
        ]);
    }
}
