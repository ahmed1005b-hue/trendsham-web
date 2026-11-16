<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuestionController extends Controller
{
    /**
     * طرح سؤال جديد على منتج (واجهة الويب).
     */
    public function store(Request $request, Product $product)
    {
        abort_unless($product->isLive(), 404);

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $question = $product->questions()->create([
            'user_id' => $request->user()->id,
            'question' => $validated['question'],
        ]);

        // إشعار البائع بسؤال جديد على أحد منتجاته.
        $sellerUserId = $product->vendor?->user_id;
        if ($sellerUserId && $sellerUserId !== $request->user()->id) {
            AppNotification::send(
                $sellerUserId,
                'product_question',
                "سؤال جديد على: {$product->name}",
                mb_substr($validated['question'], 0, 120),
                null,
            );
        }

        return back()->with('success', 'تم إرسال سؤالك بنجاح!');
    }

    /**
     * الإجابة على سؤال (يستطيع أي عميل الإجابة - تجربة Trendyol،
     * والبائع يُوسم تلقائياً).
     */
    public function answer(Request $request, ProductQuestion $question)
    {
        abort_unless($question->product->isLive(), 404);

        $validated = $request->validate([
            'answer' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $user = $request->user();
        $isSeller = $user->isSeller()
            && $user->sellerProfile?->id === $question->product->vendor_id;

        $question->answers()->create([
            'user_id' => $user->id,
            'answer' => $validated['answer'],
            'is_seller_answer' => $isSeller,
        ]);

        // إشعار صاحب السؤال بوجود رد (إن لم يكن هو نفسه المُجيب).
        if ($question->user_id !== $user->id) {
            $from = $isSeller ? 'البائع' : 'أحد العملاء';
            AppNotification::send(
                $question->user_id,
                'product_question_answer',
                "{$from} أجاب على سؤالك",
                mb_substr($validated['answer'], 0, 120),
                null,
            );
        }

        return back()->with('success', 'تم نشر إجابتك بنجاح!');
    }

    /**
     * حذف سؤال - لصاحبه فقط (الإدارة تحذف من لوحة الإدارة).
     */
    public function destroy(Request $request, ProductQuestion $question)
    {
        Gate::authorize('delete', $question);

        $question->delete();

        return back()->with('success', 'تم حذف السؤال.');
    }
}
