<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Otp;
use App\Models\User;
use App\Services\AccountClosureService;
use App\Services\InvitationService;
use App\Services\Sms\SmsService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class EmailAuthController extends Controller
{
    private const SESSION_KEY = 'pending_registration';

    public function __construct(private readonly SmsService $sms) {}

    public function showRegister()
    {
        return view('auth.register');
    }

    public function employeeRegistrationPending()
    {
        return view('auth.employee-pending');
    }

    /**
     * الخطوة 1 من التسجيل: التحقق من كل البيانات، وحفظها مؤقتاً بالجلسة،
     * وإرسال رمز تحقق للهاتف. الحساب ما ينشأ فعلياً إلا بعد تأكيد الرمز
     * بالخطوة 2 - هيك نتأكد إن رقم الهاتف حقيقي ومملوك لصاحب الطلب.
     */
    public function registerStart(Request $request)
    {
        // إنشاء الحساب العام للعملاء فقط؛ البائع والموظف برابط دعوة من الإدارة
        $invitation = $request->filled('invitation_token') ? Invitation::findByToken($request->input('invitation_token')) : null;
        if ($request->filled('invitation_token') && ! $invitation) {
            return response()->json(['message' => __('رابط الدعوة غير صحيح.')], 422);
        }
        if ($invitation && ($problem = $invitation->problem())) {
            return response()->json(['message' => $problem], 422);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/', 'unique:users,phone'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'store_name' => [$invitation?->role === 'seller' ? 'required' : 'nullable', 'string', 'max:255'],
            'store_description' => ['nullable', 'string'],
            'commercial_register' => ['nullable', 'string', 'max:255'],
        ], [
            'phone.regex' => 'رقم الهاتف غير صالح.',
            'password.confirmed' => 'تأكيد كلمة السر غير مطابق.',
            'store_name.required' => 'اسم المتجر مطلوب لحساب البائع.',
        ]);
        if ($invitation && ! $invitation->allows($validated['phone'], $validated['email'])) {
            return response()->json(['message' => __('هذه الدعوة مخصصة لشخص آخر (رقم هاتف أو بريد مختلف).')], 422);
        }
        $validated['invitation_id'] = $invitation?->id;

        $key = 'register-otp-send:'.$validated['phone'];
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'محاولات كثيرة جداً، حاول لاحقاً.'], 429);
        }
        RateLimiter::hit($key, 60);

        $request->session()->put(self::SESSION_KEY, [
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);

        $otp = Otp::generateFor($validated['phone']);
        $this->sms->send($validated['phone'], "رمز تفعيل حسابك في تريندشام: {$otp->code}");

        return response()->json(['message' => 'تم إرسال رمز التحقق إلى هاتفك.']);
    }

    /**
     * إعادة إرسال رمز التحقق لنفس الطلب المعلّق بالجلسة (بدون إعادة تعبئة الفورم).
     */
    public function resendRegisterOtp(Request $request)
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        abort_unless($pending, 400, 'ابدأ التسجيل من جديد.');

        $key = 'register-otp-send:'.$pending['phone'];
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'محاولات كثيرة جداً، حاول لاحقاً.'], 429);
        }
        RateLimiter::hit($key, 60);

        $otp = Otp::generateFor($pending['phone']);
        $this->sms->send($pending['phone'], "رمز تفعيل حسابك في تريندشام: {$otp->code}");

        return response()->json(['message' => 'تم إرسال رمز جديد.']);
    }

    /**
     * الخطوة 2: تأكيد الرمز وإنشاء الحساب فعلياً من البيانات المحفوظة بالجلسة.
     */
    public function registerVerify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $pending = $request->session()->get(self::SESSION_KEY);
        abort_unless($pending, 400, 'ابدأ التسجيل من جديد.');

        $otp = Otp::where('phone', $pending['phone'])->where('is_used', false)->latest()->first();

        if (! $otp) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'], 422);
        }

        if ($otp->isLockedOut()) {
            return response()->json(['message' => 'محاولات كثيرة جداً. اطلب رمزاً جديداً.'], 429);
        }

        if (! $otp->verify($request->code)) {
            return response()->json(['message' => 'رمز التحقق غير صحيح.'], 422);
        }

        // إعادة التحقق من عدم تكرار الهاتف/البريد بين لحظة تعبئة الفورم وهلق
        // (احتمال ضئيل بس ممكن يصير لو فتح المستخدم فورمين بنفس الوقت).
        if (User::phoneIs($pending['phone'])->exists() || User::where('email', $pending['email'])->exists()) {
            $request->session()->forget(self::SESSION_KEY);

            return response()->json(['message' => 'الحساب موجود مسبقاً، جرّب تسجيل الدخول.'], 422);
        }

        try {
            $user = DB::transaction(function () use ($pending) {
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

                // رابط دعوة: يصبح بائعاً أو موظفاً (والدعوة تُقفل وتُستعمل هنا مرة واحدة)
                if ($invitation = Invitation::find($pending['invitation_id'] ?? null)) {
                    app(InvitationService::class)->claim($invitation, $user, $pending['store_name'] ?? null);
                    $user->refresh();
                    $user->sellerProfile?->update(array_filter([
                        'store_description' => $pending['store_description'] ?? null,
                        'commercial_register' => $pending['commercial_register'] ?? null,
                    ]));
                }

                return $user;
            });
        } catch (\DomainException $e) {
            $request->session()->forget(self::SESSION_KEY);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $request->session()->forget(self::SESSION_KEY);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => route(match ($user->role) {
                'seller' => 'seller.dashboard',
                'employee' => 'employee.dashboard',
                default => 'customer.dashboard',
            }),
        ]);
    }

    /**
     * دخول موحّد: يقبل بريد إلكتروني أو رقم هاتف بنفس الحقل، مع كلمة السر.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($validated['identifier']);
        $isEmail = str_contains($identifier, '@');

        if ($isEmail) {
            $identifier = preg_replace('/\s+/', '', $identifier);
            $field = 'email';
        } else {
            $field = 'phone';
            if (! preg_match('/^\+?[0-9]{9,15}$/', $identifier)) {
                return back()->withErrors(['identifier' => 'أدخل بريد إلكتروني صحيح أو رقم هاتف صحيح.'])->onlyInput('identifier');
            }
        }

        // مفتاح يجمع المُعرّف + الـ IP - يمنع تجربة كلمات سر كثيرة على نفس
        // الحساب، وتجربة نفس الجهاز على حسابات كثيرة.
        $key = Str::lower($identifier).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'identifier' => "محاولات كثيرة جداً. حاول مرة أخرى بعد {$seconds} ثانية.",
            ])->onlyInput('identifier');
        }

        // الأرقام محفوظة بصيغة موحّدة (963..)، فنوحّد ما كتبه المستخدم (09..) قبل المطابقة
        $loginValue = $field === 'phone' ? (Phone::normalize($identifier) ?? $identifier) : $identifier;
        $credentials = [$field => $loginValue, 'password' => $validated['password']];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['identifier' => 'بيانات الدخول غير صحيحة.'])->onlyInput('identifier');
        }

        if (auth()->user()->status !== 'active') {
            $employeePending = auth()->user()->role === 'employee'
                && auth()->user()->employee_status === 'pending';
            Auth::logout();

            return back()->withErrors([
                'identifier' => $employeePending
                    ? 'طلب تسجيل الموظف بانتظار موافقة المدير.'
                    : 'هذا الحساب غير نشط.',
            ])->onlyInput('identifier');
        }

        RateLimiter::clear($key);

        // التحقق بخطوتين: لا جلسة قبل الرمز. نخرجه ونحفظ «من ينتظر» لخمس دقائق.
        if (auth()->user()->hasTwoFactor()) {
            $pendingId = auth()->id();
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put(TwoFactorChallengeController::SESSION_KEY, [
                'id' => $pendingId,
                'remember' => $request->boolean('remember'),
                'expires' => now()->addMinutes(5)->timestamp,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        // يعيد تفعيل الحساب الموقوف ويلغي طلب الحذف المعلّق
        if ($notice = app(AccountClosureService::class)->onLogin(auth()->user())) {
            $request->session()->flash('success', $notice);
        }

        return redirect()->route(match (auth()->user()->role) {
            'admin' => 'admin.dashboard',
            'seller' => 'seller.dashboard',
            'employee' => 'employee.dashboard',
            default => 'customer.dashboard',
        });
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
