<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class PhonePasswordResetController extends Controller
{
    public function __construct(private OtpService $otp) {}

    private function findUser(string $phone): ?User
    {
        return User::where('phone', $phone)->whereNotNull('phone_verified_at')->first();
    }

    public function requestOtp(Request $r)
    {
        $data  = $r->validate(['phone' => 'required|string|max:25']);
        $phone = Phone::normalize($data['phone']);
        $same  = ['message' => 'إن كان الرقم مسجلاً فسيصلك رمز التحقق خلال لحظات.'];

        if (! $phone) {
            return response()->json($same);
        }

        if (! $this->otp->cooldown($phone)) {
            return response()->json(['message' => 'انتظر قليلاً قبل طلب رمز جديد.'], 429);
        }

        if ($user = $this->findUser($phone)) {
            if (! $this->otp->issue($phone)) {
                Log::error('Password reset OTP send failed', ['user_id' => $user->id]);
            }
        }

        return response()->json($same);
    }

    public function verifyOtp(Request $r)
    {
        $data  = $r->validate(['phone' => 'required|string|max:25', 'code' => 'required|digits:6']);
        $phone = Phone::normalize($data['phone']);
        $user  = $phone ? $this->findUser($phone) : null;

        if (! $user) {
            return response()->json(['message' => 'الرمز غير صحيح أو منتهي.'], 422);
        }

        $result = $this->otp->verify($phone, $data['code']);

        if ($result === 'locked') {
            return response()->json(['message' => 'محاولات كثيرة. اطلب رمزاً جديداً.'], 429);
        }
        if ($result !== 'ok') {
            return response()->json(['message' => 'الرمز غير صحيح أو منتهي.'], 422);
        }

        return response()->json(['reset_token' => $this->otp->makeResetToken($user->id)]);
    }

    public function reset(Request $r)
    {
        $data = $r->validate([
            'reset_token' => 'required|string',
            'password'    => 'required|string|min:8|confirmed',
        ]);

        $userId = $this->otp->consumeResetToken($data['reset_token']);
        if (! $userId) {
            return response()->json(['message' => 'انتهت صلاحية الطلب. ابدأ من جديد.'], 422);
        }

        $user = User::findOrFail($userId);
        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'تم تغيير كلمة المرور. سجّل الدخول من جديد.']);
    }

    public function requestEmail(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));

        return response()->json(['message' => 'إن كان البريد مسجلاً فسيصلك رابط الاستعادة.']);
    }
}