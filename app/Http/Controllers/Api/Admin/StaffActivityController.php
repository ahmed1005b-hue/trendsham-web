<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\StaffActivityController as WebStaffActivityController;
use App\Http\Controllers\Controller;
use App\Models\StaffActivity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** سجل نشاط الفريق في التطبيق: نفس صفحة الموقع ونفس التصفية. */
class StaffActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = StaffActivity::query()
            ->filtered($request->only(['user_id', 'group', 'from', 'to']))
            ->paginate(30);

        return response()->json([
            'data' => $page->getCollection()->map(fn (StaffActivity $activity) => [
                'id' => $activity->id,
                'actor_id' => $activity->user_id,
                'actor_name' => $activity->actor_name,
                'actor_role' => $activity->actor_role,
                'action' => $activity->action,
                'group' => explode('.', $activity->action)[0],
                'description' => $activity->description(),
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'created_at' => $activity->created_at?->toIso8601String(),
            ])->values(),
            'staff' => WebStaffActivityController::staffMembers()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ])->values(),
            'groups' => collect(StaffActivity::GROUPS)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => __($label)])
                ->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
