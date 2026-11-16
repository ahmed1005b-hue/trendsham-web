<?php

namespace App\Services;

use App\Accounting\LedgerType;
use App\Documents\DocumentRegistry;
use App\Documents\DocumentSnapshots;
use App\Models\LedgerEntry;
use App\Models\SellerAccount;
use App\Models\SellerStatement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class StatementService
{
    private const MAX_ENTRIES = 20000;

    /** ينشئ كشف حساب نهائي لبائع واحد ولفترة محددة، ويحفظ ملف PDF خاصاً. */
    public function generate(User $seller, Carbon $from, Carbon $to, int $adminId): SellerStatement
    {
        $from = $from->copy()->startOfDay();
        $to   = $to->copy()->endOfDay();

        abort_if($from->gt($to), 422, 'تاريخ البداية بعد تاريخ النهاية.');
        abort_if($from->diffInDays($to) > 366, 422, 'الحد الأقصى لفترة الكشف سنة واحدة.');

        return DB::transaction(function () use ($seller, $from, $to, $adminId) {
            SellerAccount::firstOrCreate(['seller_id' => $seller->id]);
            // قفل حساب البائع: يثبّت اللقطة ويمنع تضارب ترقيم الكشوف
            SellerAccount::where('seller_id', $seller->id)->lockForUpdate()->firstOrFail();

            $base = LedgerEntry::forSeller($seller->id); // كل ما يلي مقيّد بهذا البائع فقط

            $opening = (float) ((clone $base)->where('posted_at', '<', $from)->orderByDesc('id')->value('balance_after') ?? 0);

            $count = (clone $base)->whereBetween('posted_at', [$from, $to])->count();
            abort_if($count > self::MAX_ENTRIES, 422, 'عدد القيود كبير جداً. اختر فترة أقصر.');

            $entries = (clone $base)->whereBetween('posted_at', [$from, $to])->orderBy('id')->get();

            $totalDebit  = round((float) $entries->sum('debit'), 2);
            $totalCredit = round((float) $entries->sum('credit'), 2);
            $closing     = $entries->isEmpty() ? $opening : (float) $entries->last()->balance_after;

            // فحص سلامة: الافتتاحي + الدائن - المدين = الختامي
            if (abs(round($opening + $totalCredit - $totalDebit, 2) - round($closing, 2)) > 0.009) {
                throw new \RuntimeException('عدم اتساق في الدفتر لهذا البائع. شغّل ledger:reconcile.');
            }

            $totals = $entries->groupBy('entry_type')->map(fn($g) => [
                'debit' => round((float) $g->sum('debit'), 2),
                'credit' => round((float) $g->sum('credit'), 2),
                'count' => $g->count(),
            ])->all();

            $checksum = hash('sha256', json_encode([
                $seller->id,
                $from->toDateString(),
                $to->toDateString(),
                $opening,
                $entries->map(fn($e) => [$e->id, (string) $e->debit, (string) $e->credit, (string) $e->balance_after])->all(),
            ]));

            $seq = SellerStatement::where('seller_id', $seller->id)->whereDate('period_from', $from)->count() + 1;
            $number = sprintf('ST-%s-%d-%03d', $from->format('Ym'), $seller->id, $seq);

            // الكشوف السابقة لنفس الفترة تصبح "مستبدلة"
            SellerStatement::where('seller_id', $seller->id)
                ->whereDate('period_from', $from)->whereDate('period_to', $to)
                ->update(['status' => 'superseded']);

            $data = [
                'number' => $number,
                'seller' => $seller,
                'storeName' => $seller->sellerProfile?->store_name ?? $seller->first_name . ' ' . $seller->last_name,
                'from' => $from,
                'to' => $to,
                'opening' => $opening,
                'closing' => $closing,
                'totalDebit' => $totalDebit,
                'totalCredit' => $totalCredit,
                'totals' => $totals,
                'entries' => $entries,
                'checksum' => $checksum,
                'issuedAt' => now(),
                'labels' => LedgerType::labels(),
            ];

            // رمز تحقق QR يُطبع في الكشف ويُسجَّل مع بصمة الملف
            $registry = app(DocumentRegistry::class);
            $code = $registry->newCode();
            $data['verify'] = DocumentRegistry::stamp($code);

            $pdf  = $this->renderPdf($data);
            $path = "statements/{$seller->id}/{$number}.pdf"; // مسار خاص بكل بائع
            Storage::disk('local')->put($path, $pdf);         // قرص خاص غير عام

            $statement = SellerStatement::create([
                'seller_id' => $seller->id,
                'number' => $number,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'opening_balance' => $opening,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'closing_balance' => $closing,
                'totals_by_type' => $totals,
                'entries_count' => $entries->count(),
                'checksum' => $checksum,
                'file_path' => $path,
                'status' => 'final',
                'generated_by' => $adminId,
            ]);

            [$summary, $details] = DocumentSnapshots::statement($data);
            $registry->register($code, 'seller_statement', $statement->id, $seller->id, $summary, $details, $pdf, $adminId);

            return $statement;
        });
    }

    private function renderPdf(array $data): string
    {
        $tmp = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tmp);

        $html = view('accounting.statement', $data)->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => 'dejavusans',
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_left' => 12,
            'margin_right' => 12,
            'tempDir' => $tmp,
        ]);

        $mpdf->SetTitle('كشف حساب ' . $data['number']);
        $mpdf->SetAuthor('تريندشام');
        $mpdf->SetHTMLFooter(
            '<div style="text-align:center;font-size:9px;color:#777;">'
                . $data['number'] . ' &nbsp;|&nbsp; صفحة {PAGENO} من {nbpg}</div>'
        );
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }
}
