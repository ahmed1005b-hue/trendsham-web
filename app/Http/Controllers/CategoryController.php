<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category)
    {
        $products = $category->products()
            ->publiclyVisible()
            ->with(['primaryImage', 'images', 'vendor', 'activeDailyDeal'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->priceBetween($request->get('min_price'), $request->get('max_price'))
            ->applySort($request->get('sort'))
            ->paginate(24)
            ->withQueryString();

        return view('categories.show', [
            'category' => $category,
            'products' => $products,
            'sort' => $request->get('sort', 'newest'),
            'minPrice' => $request->get('min_price'),
            'maxPrice' => $request->get('max_price'),
        ]);
    }
}
