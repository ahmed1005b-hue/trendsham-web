<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\SellerProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $profile = $request->user()->sellerProfile()->with('user')->first();
        abort_unless($profile, 404, 'لا يوجد ملف متجر لهذا المستخدم.');

        return new SellerProfileResource($profile);
    }
}
