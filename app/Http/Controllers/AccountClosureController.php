<?php

namespace App\Http\Controllers;

use App\Services\AccountClosureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** إيقاف الحساب وحذفه من الموقع + صفحة عامة تشرح طريقة الحذف (مطلوبة لمتجر Google Play). */
class AccountClosureController extends Controller
{
    public function __construct(private AccountClosureService $closure) {}

    public function info()
    {
        return view('legal.account-deletion', ['graceDays' => AccountClosureService::GRACE_DAYS]);
    }

    public function deactivate(Request $request)
    {
        $data = $request->validateWithBag('closure', ['password' => ['nullable', 'string']]);
        $user = $request->user();
        $this->confirm($user, $data['password'] ?? null);

        $this->closure->deactivate($user);
        $this->logout($request);

        return redirect()->route('home')->with('success', 'تم إيقاف حسابك. سجّل الدخول متى شئت لإعادة تفعيله.');
    }

    public function destroy(Request $request)
    {
        $data = $request->validateWithBag('closure', [
            'password' => ['nullable', 'string'],
            'confirm' => AccountClosureService::confirmRule(),
        ], ['confirm.required' => 'اكتب كلمة «حذف» للتأكيد.']);

        $user = $request->user();
        $this->confirm($user, $data['password'] ?? null);

        try {
            $result = $this->closure->requestDeletion($user);
        } catch (\DomainException $e) {
            return redirect()->to(route('profile.edit').'#account-closure')
                ->withErrors(['account' => $e->getMessage()], 'closure');
        }

        if ($result === 'requested') {
            return redirect()->to(route('profile.edit').'#account-closure')
                ->with('success', 'وصل طلب حذف حسابك إلى الإدارة، وسنتواصل معك لإتمامه بعد تسوية رصيد المتجر وطلباته.');
        }

        $this->logout($request);

        return redirect()->route('home')->with('success',
            __('أُغلق حسابك، وستُحذف بياناتك نهائياً بعد :days يوماً. لإلغاء الحذف سجّل الدخول قبل ذلك.', ['days' => AccountClosureService::GRACE_DAYS]));
    }

    private function confirm($user, ?string $password): void
    {
        try {
            $this->closure->confirmIdentity($user, $password);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $e->errorBag = 'closure';
            $e->redirectTo(route('profile.edit').'#account-closure');
            throw $e;
        }
    }

    private function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
