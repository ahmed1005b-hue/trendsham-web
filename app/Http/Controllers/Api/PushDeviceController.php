<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use Illuminate\Http\Request;

/** تسجيل هاتف المستخدم لاستقبال الإشعارات، وإلغاؤه عند الخروج. */
class PushDeviceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:android,ios'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        // التوكن فريد: إن كان لحساب آخر ينتقل لمن سجّل الدخول الآن.
        PushDevice::updateOrCreate(['token' => $data['token']], [
            'user_id' => $request->user()->id,
            'platform' => $data['platform'],
            'app_version' => $data['app_version'] ?? null,
            'last_seen_at' => now(),
        ]);

        return response()->json(['registered' => true]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        PushDevice::where('token', $data['token'])->where('user_id', $request->user()->id)->delete();

        return response()->noContent();
    }
}
