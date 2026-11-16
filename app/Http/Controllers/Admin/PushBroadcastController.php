<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushBroadcast;
use App\Notifications\Push\BroadcastService;
use DomainException;
use Illuminate\Http\Request;

/** إشعار جماعي من المدير لكل العملاء (مرة كل 7 أيام). */
class PushBroadcastController extends Controller
{
    public function __construct(private BroadcastService $service) {}

    public function index()
    {
        return view('admin.push-broadcasts.index', [
            'audience' => $this->service->audience(),
            'nextAllowedAt' => $this->service->nextAllowedAt(),
            'broadcasts' => PushBroadcast::with('creator')->latest('id')->limit(20)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:180'],
            'link' => ['nullable', 'string', 'max:255', 'starts_with:/', 'not_regex:#^//#'],
            'confirm' => ['accepted'],
        ], [
            'link.starts_with' => 'الرابط يجب أن يكون صفحة داخل الموقع ويبدأ بـ /',
            'link.not_regex' => 'الرابط يجب أن يكون صفحة داخل الموقع ويبدأ بـ /',
            'confirm.accepted' => 'أكّد أنك تريد الإرسال لكل العملاء.',
        ]);

        try {
            $broadcast = $this->service->create($data, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['title' => $e->getMessage()])->withInput();
        }

        if ($broadcast->scheduled_for->isFuture()) {
            return redirect()->route('admin.push-broadcasts.index')
                ->with('success', 'الوقت الآن ليل، سيُرسل الإشعار الساعة 9 صباحاً.');
        }

        // الإرسال بعد الرد: لا ينتظر المدير مرور الإرسال على كل العملاء.
        dispatch(fn () => app(BroadcastService::class)->sendDue())->afterResponse();

        return redirect()->route('admin.push-broadcasts.index')->with('success', 'سيُرسل الإشعار الآن لكل العملاء.');
    }
}
