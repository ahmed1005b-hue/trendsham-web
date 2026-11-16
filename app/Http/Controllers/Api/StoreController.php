<?php
namespace App\Http\Controllers\Api;  



use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller {
    public function trackVisit(Request $request) {
        DB::table('store_visits')->insert([
            'url_visited' => $request->input('url', 'general_store'),
            'created_at' => now(),
        ]);
        return response()->json(['status' => 'success']);
    }
}