<?php

namespace App\Http\Controllers\Api\Admin;

use App\Accounting\Audit;
use App\Http\Controllers\Admin\Accounting\StatementController as WebStatementController;
use App\Models\SellerStatement;
use App\Models\User;
use App\Services\StatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * كشوف حساب البائع للتطبيق: الإنشاء يعيد بيانات الكشف، والتنزيل (download) موروث ويعيد ملف PDF.
 */
class StatementController extends WebStatementController
{
    public function store(Request $r, User $seller, StatementService $svc)
    {
        abort_unless($seller->role === 'seller', 404, 'المستخدم ليس بائعاً.');

        $data = $r->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $st = $svc->generate($seller, Carbon::parse($data['from']), Carbon::parse($data['to']), $r->user()->id);
        Audit::log($r, 'generate_statement', $seller->id, $st->id);

        return response()->json(['data' => self::present($st)], 201);
    }

    public static function present(SellerStatement $s): array
    {
        return [
            'id' => $s->id,
            'number' => $s->number,
            'period_from' => $s->period_from?->toDateString(),
            'period_to' => $s->period_to?->toDateString(),
            'opening_balance' => (float) $s->opening_balance,
            'closing_balance' => (float) $s->closing_balance,
            'created_at' => $s->created_at?->toISOString(),
        ];
    }
}
