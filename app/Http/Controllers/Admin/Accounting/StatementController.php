<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Accounting\Audit;
use App\Http\Controllers\Controller;
use App\Models\SellerStatement;
use App\Models\User;
use App\Services\StatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StatementController extends Controller
{
    /** يولّد كشفاً لبائع واحد ويُنزّل ملف PDF مباشرة. */
    public function store(Request $r, User $seller, StatementService $svc)
    {
        abort_unless($seller->role === 'seller', 404, 'المستخدم ليس بائعاً.');

        $data = $r->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $st = $svc->generate($seller, Carbon::parse($data['from']), Carbon::parse($data['to']), $r->user()->id);
        Audit::log($r, 'generate_statement', $seller->id, $st->id);

        return Storage::disk('local')->download($st->file_path, $st->number . '.pdf');
    }

    /** إعادة تنزيل كشف محفوظ (نسخة ثابتة لا تتغير). */
    public function download(Request $r, SellerStatement $statement)
    {
        abort_unless(Storage::disk('local')->exists($statement->file_path), 404, 'ملف الكشف غير موجود.');

        Audit::log($r, 'download_statement', $statement->seller_id, $statement->id);

        return Storage::disk('local')->download($statement->file_path, $statement->number . '.pdf');
    }
}
