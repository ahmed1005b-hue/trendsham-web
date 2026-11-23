<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * دعوات البائعين والموظفين في التطبيق: معاينة الرمز قبل التسجيل، وقبوله بحساب عميل موجود،
 * وإدارة الدعوات من حساب المدير (نفس صفحة الإدارة في الموقع).
 */
class InvitationController extends Controller
{
    /** معاينة دعوة من رمزها أو رابطها (بلا تسجيل دخول). */
    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::findByToken($token);
        if (! $invitation) {
            return response()->json(['message' => __('رابط الدعوة غير صحيح.')], 404);
        }

        return response()->json(['data' => [
            'role' => $invitation->role,
            'role_label' => $invitation->roleLabel(),
            'store_name' => $invitation->store_name,
            'phone' => $invitation->phone,
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'usable' => $invitation->problem() === null,
            'problem' => $invitation->problem(),
        ]]);
    }

    /** قبول الدعوة بحساب العميل الحالي. */
    public function accept(Request $request, string $token, InvitationService $service): JsonResponse
    {
        $invitation = Invitation::findByToken($token);
        if (! $invitation) {
            return response()->json(['message' => __('رابط الدعوة غير صحيح.')], 404);
        }
        $data = $request->validate([
            'store_name' => [$invitation->role === 'seller' ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        try {
            $service->claim($invitation, $request->user(), $data['store_name'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new UserResource($request->user()->fresh())]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Invitation::latest()->limit(100)->get()->map(fn (Invitation $i) => $this->present($i)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(Invitation::ROLES)],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ]{9,18}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        return response()->json(['data' => $this->present(Invitation::issue($data, $request->user()))], 201);
    }

    public function revoke(Invitation $invitation): JsonResponse
    {
        if ($invitation->problem() === null) {
            $invitation->update(['revoked_at' => now()]);
        }

        return response()->json(['data' => $this->present($invitation->fresh())]);
    }

    /** @return array<string, mixed> */
    private function present(Invitation $i): array
    {
        $usable = $i->problem() === null;

        return [
            'id' => $i->id,
            'role' => $i->role,
            'role_label' => $i->roleLabel(),
            'name' => $i->name,
            'store_name' => $i->store_name,
            'phone' => $i->phone,
            'email' => $i->email,
            'status_label' => $i->statusLabel(),
            'usable' => $usable,
            'url' => $usable ? $i->url() : null,
            'expires_at' => $i->expires_at->toIso8601String(),
        ];
    }
}
