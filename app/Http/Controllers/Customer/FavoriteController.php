<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = $request->user()->favorites()->with('product.primaryImage')->latest()->get();

        return view('customer.favorites.index', ['favorites' => $favorites]);
    }

    public function toggle(Request $request, Product $product)
    {
        $favorite = $request->user()->favorites()->where('product_id', $product->id)->first();

        if ($favorite) {
            $favorite->delete();
            $message = 'تمت الإزالة من المفضلة.';
        } else {
            $request->user()->favorites()->create(['product_id' => $product->id]);
            $message = 'تمت الإضافة للمفضلة.';
        }

        return back()->with('success', $message);
    }
}
