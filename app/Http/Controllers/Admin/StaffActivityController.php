<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** سجل نشاط الفريق للمدير: من فعل ماذا ومتى. */
class StaffActivityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['user_id', 'group', 'from', 'to']);

        return view('admin.staff-activity.index', [
            'activities' => StaffActivity::query()->filtered($filters)->paginate(30)->withQueryString(),
            'filters' => $filters,
            'staff' => self::staffMembers(),
            'groups' => StaffActivity::GROUPS,
        ]);
    }

    /**
     * الموظفون المعتمدون والمدراء لقائمة التصفية.
     *
     * @return Collection<int, User>
     */
    public static function staffMembers()
    {
        return User::query()
            ->where(fn ($query) => $query->where('role', 'admin')
                ->orWhere(fn ($employees) => $employees->where('role', 'employee')->where('employee_status', 'approved')))
            ->orderBy('role', 'desc')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'role']);
    }
}
