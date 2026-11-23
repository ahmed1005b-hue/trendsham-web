<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** روابط دعوة البائعين والموظفين: إنشاؤها ونسخها وإلغاؤها. */
class InvitationController extends Controller
{
    public function index(): View
    {
        return view('admin.invitations.index', [
            'invitations' => Invitation::with('acceptor')->latest()->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(Invitation::ROLES)],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ]{9,18}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'days' => ['required', 'integer', 'min:1', 'max:30'],
        ], ['phone.regex' => __('رقم الهاتف غير صالح.')]);

        $invitation = Invitation::issue($data, $request->user());

        return redirect()->route('admin.invitations.index')
            ->with('success', __('تم إنشاء رابط الدعوة. انسخه وأرسله لصاحبه.'))
            ->with('new_invitation', $invitation->id);
    }

    public function revoke(Invitation $invitation): RedirectResponse
    {
        if ($invitation->problem() === null) {
            $invitation->update(['revoked_at' => now()]);
        }

        return back()->with('success', __('تم إلغاء الدعوة، ولم يعد رابطها يعمل.'));
    }
}
