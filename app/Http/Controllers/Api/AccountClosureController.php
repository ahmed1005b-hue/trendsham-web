<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountClosureService;
use Illuminate\Http\Request;

/** إيقاف الحساب وحذفه من التطبيق. */
class AccountClosureController extends Controller
{
    public function __construct(private AccountClosureService $closure) {}

    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json(['data' => [
            'role' => $user->role,
            'can_self_delete' => $this->closure->canSelfDelete($user),
            'blockers' => $this->closure->canSelfDelete($user) ? $this->closure->deletionBlockers($user) : [],
            'has_password' => $user->password !== null,
            'grace_days' => AccountClosureService::GRACE_DAYS,
            'deletion_requested_at' => $user->deletion_requested_at?->toIso8601String(),
        ]]);
    }

    public function deactivate(Request $request)
    {
        $data = $request->validate(['password' => ['nullable', 'string']]);
        $this->closure->confirmIdentity($request->user(), $data['password'] ?? null);
        $this->closure->deactivate($request->user());

        return response()->json(['message' => 'تم إيقاف حسابك. سجّل الدخول متى شئت لإعادة تفعيله.']);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'password' => ['nullable', 'string'],
            'confirm' => AccountClosureService::confirmRule(),
        ], ['confirm.required' => 'اكتب كلمة «حذف» للتأكيد.']);

        $user = $request->user();
        $this->closure->confirmIdentity($user, $data['password'] ?? null);

        try {
            $result = $this->closure->requestDeletion($user);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'result' => $result,
            'message' => $result === 'scheduled'
                ? __('أُغلق حسابك، وستُحذف بياناتك نهائياً بعد :days يوماً. لإلغاء الحذف سجّل الدخول قبل ذلك.', ['days' => AccountClosureService::GRACE_DAYS])
                : 'وصل طلب حذف حسابك إلى الإدارة، وسنتواصل معك لإتمامه بعد تسوية رصيد المتجر وطلباته.',
        ]);
    }
}
