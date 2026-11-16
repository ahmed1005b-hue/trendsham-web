<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Showcase;
use App\Services\ShowcaseService;
use Illuminate\Http\Request;

class ShowcaseAdminController extends Controller
{
    public function __construct(private ShowcaseService $svc) {}

    public function index(Request $r)
    {
        $showcases = Showcase::with(['slot', 'seller.user', 'products'])
            ->when($r->filled('status'), fn ($q) => $q->where('status', $r->status))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.showcases.index', compact('showcases'));
    }

    public function approve(Request $r, Showcase $showcase)
    {
        $this->svc->approve($showcase, $r->user()->id);

        return back()->with('success', 'تمت الموافقة على العرض.');
    }

    public function reject(Request $r, Showcase $showcase)
    {
        $data = $r->validate(['reason' => 'required|string|max:200']);
        $this->svc->reject($showcase, $r->user()->id, $data['reason']);

        return back()->with('success', 'تم رفض العرض واسترجاع المبلغ للبائع.');
    }

    public function remove(Request $r, Showcase $showcase)
    {
        $data = $r->validate(['reason' => 'required|string|max:200']);
        $this->svc->removeByAdmin($showcase, $r->user()->id, $data['reason']);

        return back()->with('success', 'تمت إزالة العرض واسترجاع المبلغ المتبقي.');
    }
}