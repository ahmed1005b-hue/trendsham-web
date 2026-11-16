<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->get('q', ''));

        $products = Product::publiclyVisible()->with(['primaryImage', 'images', 'vendor', 'category', 'activeDailyDeal'])->withCount('reviews')->withAvg('reviews', 'rating');

        if ($query !== '') {
            $products->where(function ($q) use ($query) {
                $q->whereFullText(['name', 'description'], $query)
                    ->orWhere('name', 'like', "%{$query}%");
            });
        }

        $products->priceBetween($request->get('min_price'), $request->get('max_price'))
            ->applySort($request->get('sort'));

        return view('search.index', [
            'query' => $query,
            'products' => $products->paginate(24)->withQueryString(),
            'sort' => $request->get('sort', 'newest'),
            'minPrice' => $request->get('min_price'),
            'maxPrice' => $request->get('max_price'),
        ]);
    }
}
