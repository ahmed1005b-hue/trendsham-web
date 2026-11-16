<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Showcase;
use App\Services\ShowcaseService;
use Illuminate\Http\Request;

class ShowcaseController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending_review');
        $query = Showcase::with(['slot', 'seller.user', 'products.primaryImage'])->latest();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        return view('admin.showcases.index', [
            'showcases' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'counts' => [
                'pending_review' => Showcase::where('status', Showcase::STATUS_PENDING)->count(),
                'approved' => Showcase::where('status', Showcase::STATUS_APPROVED)->count(),
                'rejected' => Showcase::where('status', Showcase::STATUS_REJECTED)->count(),
                'cancelled' => Showcase::where('status', Showcase::STATUS_CANCELLED)->count(),
                'expired' => Showcase::where('status', Showcase::STATUS_EXPIRED)->count(),
            ],
        ]);
    }

    public function approve(Request $request, Showcase $showcase, ShowcaseService $service)
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $service->approve($showcase, $request->user()->id, $validated['admin_note'] ?? null);

        return back()->with('success', 'تم اعتماد مساحة العرض.');
    }

    public function reject(Request $request, Showcase $showcase, ShowcaseService $service)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $service->reject($showcase, $request->user()->id, $validated['rejection_reason']);

        return back()->with('success', 'تم رفض مساحة العرض واسترداد المبلغ للبائع.');
    }

    public function destroy(Showcase $showcase, ShowcaseService $service)
    {
        if ($showcase->status === Showcase::STATUS_PENDING) {
            $service->reject($showcase, auth()->id(), 'تمت إزالة الطلب من قبل الإدارة.');
            return back()->with('success', 'تمت إزالة الطلب واسترداد المبلغ.');
        }

        $showcase->update(['status' => Showcase::STATUS_CANCELLED, 'cancelled_at' => now()]);
        $service->forgetCache();

        return back()->with('success', 'تم إلغاء/إزالة مساحة العرض.');
    }
}