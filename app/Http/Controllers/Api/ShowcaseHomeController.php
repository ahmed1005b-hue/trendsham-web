<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShowcaseService;

class ShowcaseHomeController extends Controller
{
    public function index(ShowcaseService $service)
    {
        return response()->json([
            'data' => $service->homePayload(),
        ]);
    }
}