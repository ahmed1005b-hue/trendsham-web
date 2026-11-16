<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SellerProfile;

class PageController extends Controller
{
    public function about()
    {
        return view('pages.about', [
            'sellersCount' => SellerProfile::where('status', 'approved')->count(),
            'productsCount' => Product::publiclyVisible()->count(),
        ]);
    }

    public function helpCenter()
    {
        return view('pages.help-center');
    }
}
