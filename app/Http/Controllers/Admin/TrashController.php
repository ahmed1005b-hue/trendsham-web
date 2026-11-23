<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TrashBin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** المحذوفات: كل ما حُذف حذفاً مؤقتاً، مع زر استرجاع. */
class TrashController extends Controller
{
    public function __construct(private readonly TrashBin $trash) {}

    public function index(Request $request): View
    {
        $counts = $this->trash->counts();
        $current = $this->trash->current($request->query('type'), $counts);

        return view('admin.trash.index', [
            'types' => $this->trash->labels(),
            'counts' => $counts,
            'current' => $current,
            'items' => $this->trash->page($current)->withQueryString(),
        ]);
    }

    public function restore(string $type, int $id): RedirectResponse
    {
        try {
            $this->trash->restore($type, $id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم الاسترجاع.');
    }
}
