<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = $request->user()->favorites()->with('product.primaryImage')->latest()->get();

        return ProductResource::collection($favorites->pluck('product'));
    }

    public function toggle(Request $request, Product $product)
    {
        $favorite = $request->user()->favorites()->where('product_id', $product->id)->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json(['favorited' => false]);
        }

        $request->user()->favorites()->create(['product_id' => $product->id]);

        return response()->json(['favorited' => true]);
    }
}
