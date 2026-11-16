<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductQuestionAnswerResource;
use App\Http\Resources\ProductQuestionResource;
use App\Models\AppNotification;
use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductQuestionController extends Controller
{
    /**
     * قائمة أسئلة المنتج مع أجوبتها - عامة لأي زائر (زي التقييمات).
     */
    public function index(Product $product)
    {
        abort_unless($product->isLive(), 404);

        $questions = $product->questions()
            ->with(['user', 'answers.user'])
            ->withCount('answers')
            ->paginate(20);

        return ProductQuestionResource::collection($questions);
    }

    /**
     * طرح سؤال جديد على المنتج - يتطلب حساب مسجّل.
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

        return (new ProductQuestionResource($question->load(['user', 'answers'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * الإجابة على سؤال - يسمح للبائع صاحب المنتج أو لعميل آخر (تجربة Trendyol
     * حيث يستطيع المشترون المساعدة). جواب البائع يُوسم تلقائياً.
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

        $answer = DB::transaction(function () use ($question, $user, $validated, $isSeller) {
            $answer = $question->answers()->create([
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

            return $answer;
        });

        return (new ProductQuestionAnswerResource($answer->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * حذف سؤال - لصاحبه أو للإدارة فقط.
     */
    public function destroy(Request $request, ProductQuestion $question)
    {
        Gate::authorize('deleteAsAskerOrAdmin', $question);

        $question->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * تبديل تصويت "مفيد" على سؤال.
     */
    public function toggleHelpful(Request $request, ProductQuestion $question)
    {
        $user = $request->user();

        $helpful = DB::transaction(function () use ($question, $user) {
            $question = ProductQuestion::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();

            $existing = $question->helpfulVotes()->where('user_id', $user->id)->first();

            if ($existing) {
                $existing->delete();
                $question->decrement('helpful_count');
            } else {
                $question->helpfulVotes()->create(['user_id' => $user->id]);
                $question->increment('helpful_count');
            }

            return $existing === null;
        });

        return response()->json([
            'is_helpful' => $helpful,
            'helpful_count' => $question->fresh()->helpful_count,
        ]);
    }
}
