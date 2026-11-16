<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $key = 'password-reset:'.strtolower($validated['email']);
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('status', 'لو البريد مسجّل عندنا، رح توصلك رسالة رابط الاستعادة قريباً.');
        }
        RateLimiter::hit($key, 300); // 5 دقائق بين كل طلب وطلب لنفس البريد

        $user = User::where('email', $validated['email'])->first();

        // نعرض نفس الرسالة سواء البريد موجود أو لأ - يمنع اكتشاف البريد المسجّل
        // بالنظام (user enumeration) من خلال تجربة استعادة كلمة سر.
        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);

            try {
                Mail::to($user)->locale(app()->getLocale())->send(new \App\Mail\PasswordResetMail($user, $resetUrl));
            } catch (\Throwable $e) {
                return back()->withErrors(['email' => 'تعذّر إرسال البريد. تحقق من إعدادات SMTP (MAIL_PASSWORD).']);
            }
        }

        return back()->with('status', 'لو البريد مسجّل عندنا، رح توصلك رسالة رابط الاستعادة قريباً.');
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'password.confirmed' => 'تأكيد كلمة السر غير مطابق.',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $validated['email'])->first();

        if (! $record || ! Hash::check($validated['token'], $record->token)) {
            return back()->withErrors(['email' => 'رابط الاستعادة غير صالح أو منتهي الصلاحية.']);
        }

        // Carbon 3: diffInMinutes يُرجع قيمة سالبة للماضي، فكان الشرط القديم لا يتحقق أبداً
        if (\Illuminate\Support\Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

            return back()->withErrors(['email' => 'رابط الاستعادة منتهي الصلاحية، اطلب رابطاً جديداً.']);
        }

        $user = User::where('email', $validated['email'])->first();
        abort_unless($user, 404);

        $user->update(['password' => $validated['password']]);

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return redirect()->route('login')->with('status', 'تم تغيير كلمة السر، سجّل دخولك الآن.');
    }

    public function showPhoneRequestForm()
    {
        return view('auth.forgot-password-phone');
    }

    public function sendPhoneResetCode(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
        ]);

        $phone = $validated['phone'];
        $user = User::phoneIs($phone)->first();

        // رد موحد (بدون كشف ما إذا كان الرقم مسجّلاً)
        $statusMessage = 'إذا كان الرقم مسجّلاً، تم إرسال رمز التحقق.';

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addMinutes(5);

            Cache::put('password_reset_phone_code:'.$phone, [
                'hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => $expiresAt->toDateTimeString(),
            ], $expiresAt);

            try {
                app(SmsService::class)->send($phone, "رمز إعادة تعيين كلمة المرور في تريندشام: {$code}");
            } catch (\Throwable $e) {
                // لا نكشف الفشل للمستخدم
            }
        }

        return redirect()->route('password.phone.verify')->with('phone', $phone)
            ->with('status', $statusMessage);
    }

    public function showPhoneVerifyForm(Request $request)
    {
        return view('auth.verify-phone', [
            'phone' => old('phone', $request->session()->get('phone', $request->query('phone'))),
        ]);
    }

    public function verifyPhoneResetCode(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $phone = $validated['phone'];
        $code = $validated['code'];

        $record = Cache::get('password_reset_phone_code:'.$phone);

        if (! $record || now()->gt($record['expires_at'])) {
            Cache::forget('password_reset_phone_code:'.$phone);

            return back()->withErrors(['code' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'])->withInput();
        }

        $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;

        if ($record['attempts'] > 5) {
            Cache::forget('password_reset_phone_code:'.$phone);

            return back()->withErrors(['code' => 'تم تجاوز عدد المحاولات. اطلب رمزاً جديداً.'])->withInput();
        }

        if (! Hash::check($code, $record['hash'])) {
            Cache::put('password_reset_phone_code:'.$phone, $record, \Carbon\Carbon::parse($record['expires_at']));

            return back()->withErrors(['code' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'])->withInput();
        }

        // نجاح: حذف رمز، إصدار توكن مؤقت (10 دقائق) لمرة واحدة
        Cache::forget('password_reset_phone_code:'.$phone);

        $token = Str::random(64);
        $expiresAt = now()->addMinutes(10);

        Cache::put('password_reset_phone_token:'.$token, [
            'phone' => $phone,
            'expires_at' => $expiresAt->toDateTimeString(),
        ], $expiresAt);

        return redirect()->route('password.phone.reset', ['token' => $token])
            ->with('status', 'تم التحقق. أدخل كلمة المرور الجديدة.');
    }

    public function showPhoneResetForm(Request $request)
    {
        $token = $request->query('token');

        if (! $token) {
            return redirect()->route('password.phone.request')
                ->withErrors(['phone' => 'توكن غير صالح. ابدأ من جديد.']);
        }

        $record = Cache::get('password_reset_phone_token:'.$token);

        if (! $record || now()->gt($record['expires_at'])) {
            Cache::forget('password_reset_phone_token:'.$token);

            return redirect()->route('password.phone.request')
                ->withErrors(['phone' => 'توكن غير صالح أو منتهي الصلاحية. ابدأ من جديد.']);
        }

        return view('auth.reset-password-phone', [
            'phone' => $record['phone'],
            'token' => $token,
        ]);
    }

    public function phoneReset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
        ]);

        $token = $validated['token'];
        $record = Cache::get('password_reset_phone_token:'.$token);

        if (! $record || now()->gt($record['expires_at'])) {
            Cache::forget('password_reset_phone_token:'.$token);

            return back()->withErrors(['password' => 'توكن غير صالح أو منتهي الصلاحية.'])->withInput();
        }

        $phone = $record['phone'];
        $user = User::phoneIs($phone)->first();
        abort_unless($user, 404);

        $user->update(['password' => $validated['password']]);

        // حذف التوكن بعد الاستخدام
        Cache::forget('password_reset_phone_token:'.$token);

        return redirect()->route('login')->with('status', 'تم تغيير كلمة السر، سجّل دخولك الآن.');
    }
}
