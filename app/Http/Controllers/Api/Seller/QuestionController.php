<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\ProductQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->sellerProfile;

        $questions = ProductQuestion::whereHas('product', function ($q) use ($vendor) {
            $q->where('vendor_id', $vendor->id);
        })
            ->with('product', 'user')
            ->latest()
            ->get();

        return response()->json([
            'questions' => $questions->map(function ($q) {
                return [
                    'id' => $q->id,
                    'product_id' => $q->product_id,
                    'product_name' => $q->product->name,
                    'customer_name' => $q->user->name,
                    'question' => $q->question,
                    'created_at' => $q->created_at->toISOString(),
                    'has_answer' => $q->answers->isNotEmpty(),
                    'answers' => $q->answers->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'answer' => $a->answer,
                            'is_seller_answer' => (bool) $a->is_seller_answer,
                            'created_at' => $a->created_at->toISOString(),
                        ];
                    }),
                ];
            }),
        ]);
    }

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

        return response()->json([
            'message' => 'تم نشر ردك على السؤال بنجاح.',
        ]);
    }
}
