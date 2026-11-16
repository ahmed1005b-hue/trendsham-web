<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\SellerAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SellerController extends Controller
{
    public function employees()
    {
        return view('admin.employees.index', [
            'employees' => User::where('role', 'employee')
                ->where('employee_status', 'pending')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function approveEmployee(User $user)
    {
        abort_unless($user->role === 'employee' && $user->employee_status === 'pending', 404);
        $user->update(['employee_status' => 'approved', 'status' => 'active']);

        return back()->with('success', 'تمت الموافقة على حساب الموظف.');
    }

    public function rejectEmployee(User $user)
    {
        abort_unless($user->role === 'employee' && $user->employee_status === 'pending', 404);
        $user->update(['employee_status' => 'rejected', 'status' => 'suspended']);

        return back()->with('success', 'تم رفض طلب الموظف.');
    }

    public function index()
    {
        return view('admin.sellers.index', [
            'sellers' => SellerProfile::with('user')->whereIn('status', ['approved', 'suspended'])->orderByRaw("status = 'suspended' desc")->latest()->get(),
        ]);
    }

    public function updateCommission(Request $request, SellerProfile $sellerProfile)
    {
        $validated = $request->validate([
            'commission_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $sellerProfile->update(['commission_rate' => $validated['commission_rate'] ?? null]);

        return back()->with('success', 'تم تحديث نسبة عمولة البائع.');
    }

    public function identityDocument(Request $request, SellerProfile $sellerProfile)
    {
        $side = $request->query('side', 'front');
        $path = $side === 'back' ? $sellerProfile->identity_document_back_path : $sellerProfile->identity_document_path;
        $disk = Storage::disk('identity_documents');
        abort_unless(
            in_array($side, ['front', 'back'], true)
                && $path
                && str_starts_with($path, 'seller-identities/')
                && $disk->exists($path),
            404
        );
        $mimeType = (string) ($disk->mimeType($path) ?: 'application/octet-stream');
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true), 404);
        $ext = $mimeType === 'application/pdf' ? 'pdf' : (pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');

        $response = response()->file($disk->path($path));
        $response->setPrivate();
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', 'inline; filename="seller-identity-'.$side.'.'.$ext.'"');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function destroy(SellerProfile $sellerProfile)
    {
        try {
            app(SellerAccountService::class)->delete($sellerProfile);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.sellers.index')->with('success', 'تم حذف البائع.');
    }

    /** إيقاف المتجر: بديل آمن للحذف، تبقى المحفظة والطلبات والسجلات. */
    public function suspend(Request $request, SellerProfile $sellerProfile)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'اكتب سبب الإيقاف ليظهر للبائع.']);

        try {
            app(SellerAccountService::class)->suspend($sellerProfile, $validated['reason']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "تم إيقاف متجر {$sellerProfile->store_name}. منتجاته مخفية عن الزوار، وسجلاته محفوظة.");
    }

    public function unsuspend(SellerProfile $sellerProfile)
    {
        try {
            app(SellerAccountService::class)->unsuspend($sellerProfile);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "تمت إعادة تفعيل متجر {$sellerProfile->store_name}.");
    }
}