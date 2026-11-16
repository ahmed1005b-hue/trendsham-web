<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\AppCache;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return CategoryResource::collection(AppCache::categoryTree());
    }

    public function show(Request $request, Category $category)
    {
        $categoryIds = collect([$category->id]);
        if ($category->parent_id === null) {
            $categoryIds = $categoryIds->merge(
                $category->children()->active()->pluck('id')
            );
        }

        $products = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->publiclyVisible()
            ->with(['primaryImage', 'images', 'activeDailyDeal'])
            ->priceBetween($request->get('min_price'), $request->get('max_price'))
            ->applySort($request->get('sort'))
            ->paginate($request->get('per_page', 20));

        return ProductResource::collection($products)->additional([
            'category' => new CategoryResource($category),
        ]);
    }
}
