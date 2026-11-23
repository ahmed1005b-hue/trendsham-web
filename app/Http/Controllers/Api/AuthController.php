<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\PasswordResetMail;
use App\Models\Invitation;
use App\Models\Otp;
use App\Models\User;
use App\Referrals\ReferralCode;
use App\Referrals\ReferralException;
use App\Referrals\ReferralService;
use App\Services\AccountClosureService;
use App\Services\InvitationService;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly SmsService $sms) {}

    /**
     * الخطوة 1: التحقق من البيانات وإرسال رمز OTP. ما بننشئ الحساب إلا
     * بعد تأكيد الرمز بـ verifyRegistration - نفس منطق الويب بالضبط،
     * بس هون البيانات المؤقتة تتخزّن بجدول otps نفسه (عبر حقل مؤقت
     * بالكاش) لأن الـ API بلا جلسة (stateless).
     */
    public function register(Request $request)
    {
        // إنشاء الحساب للعملاء فقط؛ البائع والموظف برمز/رابط دعوة من الإدارة (invitation_token)
        $invitation = $request->filled('invitation_token') ? Invitation::findByToken($request->input('invitation_token')) : null;
        if ($request->filled('invitation_token') && ! $invitation) {
            return response()->json(['message' => __('رابط الدعوة غير صحيح.'), 'errors' => ['invitation_token' => [__('رابط الدعوة غير صحيح.')]]], 422);
        }
        if ($invitation && ($problem = $invitation->problem())) {
            return response()->json(['message' => $problem, 'errors' => ['invitation_token' => [$problem]]], 422);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/', 'unique:users,phone'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'store_name' => [$invitation?->role === 'seller' ? 'required' : 'nullable', 'string', 'max:255'],
            // كود «دعوة صديق» (للعملاء فقط): يُفحص الآن ليُصحَّح قبل إرسال رمز التحقق
            'referral_code' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== null && ReferralCode::decode((string) $value) === null) {
                    $fail(__('كود الدعوة غير صحيح.'));
                }
            }],
        ]);

        if ($invitation && ! $invitation->allows($validated['phone'], $validated['email'])) {
            return response()->json(['message' => __('هذه الدعوة مخصصة لشخص آخر (رقم هاتف أو بريد مختلف).')], 422);
        }
        $validated['invitation_id'] = $invitation?->id;

        $key = 'api-register-otp:'.$validated['phone'];
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'محاولات كثيرة جداً، حاول لاحقاً.'], 429);
        }
        RateLimiter::hit($key, 60);

        cache()->put('pending_registration:'.$validated['phone'], [
            ...$validated,
            'password' => Hash::make($validated['password']),
        ], now()->addMinutes(15));

        $otp = Otp::generateFor($validated['phone']);
        $this->sms->send($validated['phone'], "رمز تفعيل حسابك في تريندشام: {$otp->code}");

        return response()->json(['message' => 'تم إرسال رمز التحقق.']);
    }

    public function verifyRegistration(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $pending = cache()->get('pending_registration:'.$validated['phone']);
        if (! $pending) {
            return response()->json(['message' => 'انتهت صلاحية الطلب، ابدأ التسجيل من جديد.'], 400);
        }

        $otp = Otp::where('phone', $validated['phone'])->where('is_used', false)->latest()->first();
        if (! $otp || $otp->isLockedOut()) {
            return response()->json(['message' => 'رمز التحقق غير صالح أو منتهي.'], 422);
        }
        if (! $otp->verify($request->code)) {
            return response()->json(['message' => 'رمز التحقق غير صحيح.'], 422);
        }

        // وثيقة الهوية اختيارية للبائع المدعو (يمكن رفعها لاحقاً لشارة «متجر موثّق»)
        $identityDocumentPath = null;
        if (($pending['invitation_id'] ?? null) && $request->hasFile('identity_document')) {
            $request->validate(['identity_document' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
            $identityDocumentPath = $request->file('identity_document')->store('seller-identities', 'identity_documents');
        }

        try {
            $user = DB::transaction(function () use ($pending, $identityDocumentPath) {
                $user = User::create([
                    'first_name' => $pending['first_name'],
                    'last_name' => $pending['last_name'],
                    'phone' => $pending['phone'],
                    'address' => $pending['address'] ?? null,
                    'email' => $pending['email'],
                    'password' => $pending['password'],
                    'role' => 'customer',
                    'status' => 'active',
                    'phone_verified_at' => now(),
                ]);

                // دعوة: يصبح بائعاً أو موظفاً (الدعوة تُقفل وتُستعمل هنا مرة واحدة)
                if ($invitation = Invitation::find($pending['invitation_id'] ?? null)) {
                    app(InvitationService::class)->claim($invitation, $user, $pending['store_name'] ?? null);
                    $user->refresh();
                    if ($identityDocumentPath) {
                        $user->sellerProfile?->update([
                            'identity_document_path' => $identityDocumentPath,
                            'identity_document_submitted_at' => now(),
                        ]);
                    }
                }

                return $user;
            });
        } catch (\DomainException $e) {
            if ($identityDocumentPath) {
                Storage::disk('identity_documents')->delete($identityDocumentPath);
            }
            cache()->forget('pending_registration:'.$validated['phone']);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $exception) {
            if ($identityDocumentPath) {
                Storage::disk('identity_documents')->delete($identityDocumentPath);
            }
            throw $exception;
        }

        cache()->forget('pending_registration:'.$validated['phone']);

        if ($user->role === 'customer' && ! empty($pending['referral_code'])) {
            try {
                app(ReferralService::class)->apply($user, (string) $pending['referral_code']);
            } catch (ReferralException) {
                // كود لم يعد صالحاً لا يمنع إنشاء الحساب
            }
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($validated['identifier']);
        if (str_contains($identifier, '@')) {
            $identifier = preg_replace('/\s+/', '', $identifier);
        }
        $field = str_contains($identifier, '@') ? 'email' : 'phone';

        $key = 'api-login:'.Str::lower($identifier).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'محاولات كثيرة جداً، حاول لاحقاً.'], 429);
        }

        $user = ($field === 'phone' ? User::phoneIs($identifier) : User::where('email', $identifier))->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            return response()->json(['message' => 'بيانات الدخول غير صحيحة.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => $user->role === 'employee' && $user->employee_status === 'pending'
                    ? 'طلب تسجيل الموظف بانتظار موافقة المدير.'
                    : 'هذا الحساب غير نشط.',
            ], 403);
        }

        RateLimiter::clear($key);

        // التحقق بخطوتين: لا رمز دخول قبل رمز تطبيق المصادقة
        if ($user->hasTwoFactor()) {
            return response()->json([
                'two_factor_required' => true,
                'challenge' => TwoFactorController::challengeFor($user),
                'message' => __('أدخل الرمز من تطبيق المصادقة.'),
            ]);
        }

        // يعيد تفعيل الحساب الموقوف ويلغي طلب الحذف المعلّق
        $notice = app(AccountClosureService::class)->onLogin($user);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
            'notice' => $notice,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function requestReset(Request $request)
    {
        return $this->forgotPassword($request);
    }

    public function reset(Request $request)
    {
        return $this->resetPassword($request);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string'],
            'email' => ['nullable', 'string', 'email'],
            'phone' => ['nullable', 'string'],
        ]);

        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? $validated['phone'] ?? ''));
        if ($identifier === '') {
            return response()->json(['message' => 'المعرف مطلوب.'], 422);
        }

        $field = str_contains($identifier, '@') ? 'email' : 'phone';

        $user = ($field === 'phone' ? User::phoneIs($identifier) : User::where('email', $identifier))->first();
        if (! $user && $field === 'phone') {
            $user = User::where('email', $identifier)->first();
        }
        if (! $user && $field === 'email') {
            $user = User::phoneIs($identifier)->first();
        }

        if ($user) {
            $key = 'api-password-reset:'.Str::lower($identifier);
            if (! RateLimiter::tooManyAttempts($key, 5)) {
                RateLimiter::hit($key, 60);

                if (preg_match('/^\+?[0-9]{9,15}$/', (string) $user->phone)) {
                    $otp = Otp::generateFor($user->phone);
                    try {
                        $this->sms->send($user->phone, "رمز إعادة تعيين كلمة المرور في تريندشام: {$otp->code}");
                    } catch (Throwable $e) {
                    }
                }

                // رابط البريد فقط لمن لديه بريد (حساب الهاتف فقط كان يسبب خطأ 500 لأن العمود email إجباري)
                if ($user->email) {
                    $token = Str::random(64);
                    DB::table('password_reset_tokens')->updateOrInsert(
                        ['email' => $user->email],
                        ['token' => Hash::make($token), 'created_at' => now()]
                    );
                }

                try {
                    if ($user->email) {
                        Mail::to($user)->locale(app()->getLocale())->send(new PasswordResetMail(
                            $user,
                            route('password.reset', ['token' => $token, 'email' => $user->email])
                        ));
                    }
                } catch (Throwable $e) {
                }

                // لا نُرجع الـ token في الرد أبداً: يصل فقط عبر البريد/الـ SMS،
                // وإلا استطاع أي شخص يعرف بريد/رقم مستخدم تغيير كلمة مروره.
            }
        }

        // رد موحد سواء وُجد الحساب أم لا (لا نكشف الحسابات المسجّلة).

        return response()->json([
            'message' => 'إذا كان الحساب موجوداً، تم إرسال تعليمات إعادة التعيين.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string'],
            'email' => ['nullable', 'string', 'email'],
            'phone' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
            'token' => ['nullable', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? $validated['phone'] ?? ''));
        $user = null;
        if ($identifier !== '') {
            $field = str_contains($identifier, '@') ? 'email' : 'phone';
            $user = ($field === 'phone' ? User::phoneIs($identifier) : User::where('email', $identifier))->first();
            if (! $user && $field === 'phone') {
                $user = User::where('email', $identifier)->first();
            }
            if (! $user && $field === 'email') {
                $user = User::phoneIs($identifier)->first();
            }
        }

        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }

        $code = $validated['code'] ?? null;
        $token = $validated['token'] ?? null;

        if ($code) {
            $otp = Otp::where('phone', $user->phone)->where('is_used', false)->latest()->first();
            if (! $otp || $otp->isLockedOut() || ! $otp->verify($code)) {
                return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'], 422);
            }
        } elseif ($token) {
            $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();
            if (! $record || ! Hash::check($token, $record->token)) {
                return response()->json(['message' => 'رابط إعادة التعيين غير صحيح أو منتهي الصلاحية.'], 422);
            }
            // Carbon 3: diffInMinutes يُرجع قيمة سالبة للماضي، فكان الشرط القديم لا يتحقق أبداً
            if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();

                return response()->json(['message' => 'انتهت صلاحية الرابط، اطلب رابطاً جديداً.'], 422);
            }
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        } else {
            return response()->json(['message' => 'رمز أو رمز تحقق مطلوب.'], 422);
        }

        $user->update(['password' => $validated['password']]);
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        try {
            $user->tokens()->delete();
        } catch (Throwable $e) {
        }

        // من فعّل التحقق بخطوتين يدخل بنفسه بعد التغيير (كلمة السر + الرمز)، لا رمز دخول هنا
        if ($user->hasTwoFactor()) {
            return response()->json([
                'message' => 'تم تغيير كلمة المرور بنجاح. سجّل دخولك الآن.',
                'login_required' => true,
            ]);
        }

        $newToken = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح.',
            'token' => $newToken,
            'user' => new UserResource($user),
        ]);
    }
}
