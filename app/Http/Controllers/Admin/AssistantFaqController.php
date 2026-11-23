<?php

namespace App\Http\Controllers\Admin;

use App\Assistant\Models\AssistantFaq;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** أجوبة المساعد للأسئلة الشائعة (الشحن، الإرجاع، الدفع...) */
class AssistantFaqController extends Controller
{
    public function index()
    {
        return view('admin.assistant.faqs.index', [
            'faqs' => AssistantFaq::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.assistant.faqs.form', ['faq' => new AssistantFaq(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        AssistantFaq::create(self::validated($request));

        return redirect()->route('admin.assistant-faqs.index')->with('success', 'تمت إضافة السؤال.');
    }

    public function edit(AssistantFaq $assistantFaq)
    {
        return view('admin.assistant.faqs.form', ['faq' => $assistantFaq]);
    }

    public function update(Request $request, AssistantFaq $assistantFaq)
    {
        $assistantFaq->update(self::validated($request));

        return redirect()->route('admin.assistant-faqs.index')->with('success', 'تم حفظ التعديل.');
    }

    public function destroy(AssistantFaq $assistantFaq)
    {
        $assistantFaq->delete();

        return back()->with('success', 'تم حذف السؤال.');
    }

    /**
     * بيانات سؤال شائع مُتحقق منها (مشتركة مع التطبيق).
     *
     * @return array{question: string, keywords: string, answer: string, sort_order: int, is_active: bool}
     */
    public static function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'keywords' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:3000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'question.required' => __('اكتب السؤال.'),
            'keywords.required' => __('اكتب كلمة واحدة على الأقل يعرف بها المساعد السؤال.'),
            'answer.required' => __('اكتب الجواب.'),
        ]);

        $data['sort_order'] ??= 0;

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
