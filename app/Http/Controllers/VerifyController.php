<?php

namespace App\Http\Controllers;

use App\Documents\DocumentRegistry;
use App\Documents\Models\IssuedDocument;
use App\Models\Order;
use Illuminate\Http\Request;

/** صفحة التحقق العامة من المستندات (رمز QR / كتابة الرمز / رفع الملف). */
class VerifyController extends Controller
{
    public function __construct(private readonly DocumentRegistry $registry) {}

    public function index(Request $request)
    {
        if ($request->filled('code')) {
            $code = DocumentRegistry::normalize($request->input('code'));
            if ($code === null) {
                return back()->withErrors(['code' => 'الرمز يتكون من 12 حرفاً ورقماً، مثل TS-7K2M-9QXA-4BCD.'])->withInput();
            }

            return redirect()->route('verify.show', $code);
        }

        return view('verify.index');
    }

    public function show(Request $request, string $code)
    {
        $normalized = DocumentRegistry::normalize($code);
        $doc = $normalized ? IssuedDocument::where('code', $normalized)->first() : null;

        if (! $doc) {
            return response()->view('verify.show', ['doc' => null, 'code' => $code], 404);
        }

        return view('verify.show', [
            'doc' => $doc,
            'status' => $this->registry->status($doc),
            'showDetails' => $doc->canSeeDetails($request->user()),
            'sourceUrl' => $this->sourceUrl($doc, $request),
        ]);
    }

    public function checkFile(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']], [
            'file.required' => 'اختر ملف PDF لفحصه.',
            'file.max' => 'حجم الملف أكبر من 10 ميغابايت.',
        ]);

        $doc = $this->registry->findByFile((string) file_get_contents($request->file('file')->getRealPath()));

        return view('verify.index', ['fileResult' => $doc ? 'match' : 'no_match', 'matched' => $doc]);
    }

    /** رابط الصفحة الأصلية لصاحب المستند أو المدير. */
    private function sourceUrl(IssuedDocument $doc, Request $request): ?string
    {
        $user = $request->user();
        if (! $doc->canSeeDetails($user)) {
            return null;
        }

        return match ($doc->type) {
            'invoice' => ($order = Order::find($doc->subject_id))
                ? ($user->role === 'admin' ? route('admin.orders.show', $order) : route('customer.orders.show', $order))
                : null,
            default => null,
        };
    }
}
