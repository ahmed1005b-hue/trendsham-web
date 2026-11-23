<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * رابط الدعوة: زائر ← نموذج إنشاء حساب بائع/موظف، عميل مسجّل ← قبول الدعوة بحسابه.
 */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = Invitation::findByToken($token);
        $problem = $invitation ? $invitation->problem() : __('رابط الدعوة غير صحيح.');

        if ($problem === null && ! $request->user()) {
            return view('auth.register', ['invitation' => $invitation, 'invitationToken' => $token]);
        }

        return view('auth.invitation', [
            'invitation' => $invitation,
            'token' => $token,
            'problem' => $problem,
        ]);
    }

    public function accept(Request $request, string $token, InvitationService $service): RedirectResponse
    {
        $invitation = Invitation::findByToken($token);
        abort_unless($invitation, 404);
        $data = $request->validate([
            'store_name' => [$invitation->role === 'seller' ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        try {
            $service->claim($invitation, $request->user(), $data['store_name'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $invitation->role === 'seller'
            ? redirect()->route('seller.dashboard')->with('success', __('مرحباً بك بائعاً في تريندشام! متجرك يعمل الآن.'))
            : redirect()->route('employee.dashboard')->with('success', __('مرحباً بك في فريق تريندشام.'));
    }
}
