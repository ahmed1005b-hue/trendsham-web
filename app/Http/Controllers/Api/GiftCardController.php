<?php

namespace App\Http\Controllers\Api;

use App\GiftCards\GiftCardException;
use App\GiftCards\GiftCardService;
use App\GiftCards\Models\GiftBalanceTransaction;
use App\GiftCards\Models\GiftCard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** بطاقات الهدايا للتطبيق. المبالغ بالليرة. */
class GiftCardController extends Controller
{
    public function index(Request $request, GiftCardService $gifts): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'balance' => $gifts->balance($user->id) / 100,
            'online_enabled' => $gifts->onlineEnabled(),
            'presets' => array_map(fn (int $minor) => $minor / 100, GiftCardService::PRESETS),
            'min_amount' => GiftCardService::MIN_AMOUNT / 100,
            'max_amount' => GiftCardService::MAX_AMOUNT / 100,
            'cards' => GiftCard::where('purchaser_id', $user->id)->whereIn('status', [GiftCard::ACTIVE, GiftCard::REDEEMED])
                ->with('redeemer:id,first_name')->latest('id')->limit(50)->get()
                ->map(fn (GiftCard $c) => $this->card($c))->values(),
            'transactions' => GiftBalanceTransaction::where('user_id', $user->id)->with('order:id,order_number')
                ->latest('id')->limit(30)->get()
                ->map(fn (GiftBalanceTransaction $t) => [
                    'id' => $t->id,
                    'label' => $t->label(),
                    'amount' => $t->amount / 100,
                    'created_at' => $t->created_at?->toDateString(),
                ])->values(),
        ]);
    }

    public function show(Request $request, GiftCard $giftCard): JsonResponse
    {
        abort_unless($giftCard->purchaser_id === $request->user()->id, 404);

        return response()->json($this->card($giftCard));
    }

    public function purchase(Request $request, GiftCardService $gifts): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'recipient_name' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $result = $gifts->purchase($request->user(), (int) $data['amount'] * 100, $data['recipient_name'] ?? null, $data['message'] ?? null);
        } catch (GiftCardException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $result['card']->id, 'url' => $result['url']], 201);
    }

    public function redeem(Request $request, GiftCardService $gifts): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30']]);

        try {
            $card = $gifts->redeem($request->user(), $data['code']);
        } catch (GiftCardException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => __('أُضيف :amount إلى رصيد الهدايا.', ['amount' => GiftCardService::money($card->amount)]),
            'balance' => $gifts->balance($request->user()->id) / 100,
        ]);
    }

    /** @return array<string, mixed> */
    private function card(GiftCard $c): array
    {
        return [
            'id' => $c->id,
            'amount' => $c->amount / 100,
            'status' => $c->status,
            'status_label' => $c->statusLabel(),
            // الكود يظهر للمشتري فقط، وقبل استخدامه
            'code' => $c->status === GiftCard::ACTIVE && $c->code ? GiftCardService::format($c->code) : null,
            'recipient_name' => $c->recipient_name,
            'message' => $c->message,
            'expires_at' => $c->expires_at?->toDateString(),
            'redeemed_by' => $c->redeemer?->first_name,
            'redeemed_at' => $c->redeemed_at?->toDateString(),
        ];
    }
}
