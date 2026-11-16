<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class OtpAuthController extends Controller
{
    public function __construct(private readonly SmsService $sms)
    {
    }

    /**
     * إرسال رمز التحقق (OTP) إلى رقم الهاتف.
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
        ]);

        $key = 'otp-send:'.$request->phone;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'message' => 'محاولات كثيرة جداً، حاول لاحقاً.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $otp = Otp::generateFor($request->phone);

        $this->sms->send($request->phone, "رمز التحقق الخاص بك في تريندشام: {$otp->code}");

        return response()->json([
            'message' => 'تم إرسال رمز التحقق.',
        ]);
    }

    /**
     * التحقق من الرمز وتسجيل الدخول أو إنشاء حساب جديد.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'name' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'in:customer,seller'],
            'store_name' => ['required_if:account_type,seller', 'nullable', 'string', 'max:255'],
        ]);

        $otp = Otp::where('phone', $request->phone)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (! $otp) {
            return response()->json([
                'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ], 422);
        }

        if ($otp->isLockedOut()) {
            return response()->json([
                'message' => 'محاولات كثيرة جداً بهذا الرمز. اطلب رمزاً جديداً.',
            ], 429);
        }

        if (! $otp->verify($request->code)) {
            return response()->json([
                'message' => 'رمز التحقق غير صحيح.',
            ], 422);
        }

        $existing = User::phoneIs($request->phone)->first();
        $isNewPhone = ! $existing;

        $user = DB::transaction(function () use ($request, $isNewPhone, $existing) {
            $accountType = $isNewPhone ? ($request->account_type ?? 'customer') : 'customer';

            $user = User::firstOrCreate(
                ['phone' => $existing?->phone ?? \App\Support\Phone::normalize($request->phone) ?? $request->phone],
                [
                    'first_name' => $request->name ?? $request->phone,
                    'role' => $accountType,
                    'phone_verified_at' => now(),
                ]
            );

            if ($isNewPhone && $accountType === 'seller' && $request->store_name) {
                SellerProfile::create([
                    'user_id' => $user->id,
                    'store_name' => $request->store_name,
                    'status' => 'pending',
                ]);
            }

            return $user;
        });

        if (is_null($user->phone_verified_at)) {
            $user->update(['phone_verified_at' => now()]);
        }

        Auth::login($user, remember: true);

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح.',
            'user' => $user,
            'redirect' => match ($user->role) {
                'admin' => route('admin.dashboard'),
                'seller' => route('seller.dashboard'),
                default => route('customer.dashboard'),
            },
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
