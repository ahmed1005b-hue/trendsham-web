<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\ProductQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuestionController extends Controller
{
    /**
     * قائمة الأسئلة على منتجات البائع التي تحتاج رد.
     */
    public function index(Request $request)
    {
        $vendor = $request->user()->sellerProfile;

        $questions = ProductQuestion::whereHas('product', function ($q) use ($vendor) {
            $q->where('vendor_id', $vendor->id);
        })
            ->with('product', 'user')
            ->latest()
            ->paginate(15);

        return view('seller.questions.index', compact('questions', 'vendor'));
    }

    /**
     * الرد على سؤال منتج كبائع.
     */
    public function answer(Request $request, ProductQuestion $question)
    {
        Gate::authorize('answerAsSeller', $question);

        $validated = $request->validate([
            'answer' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $question->answers()->create([
            'user_id' => $request->user()->id,
            'answer' => $validated['answer'],
            'is_seller_answer' => true,
        ]);

        AppNotification::send(
            $question->user_id,
            'product_question_answer',
            'البائع أجاب على سؤالك',
            mb_substr($validated['answer'], 0, 120),
            null,
        );

        return back()->with('success', 'تم نشر ردك على السؤال بنجاح!');
    }
}
