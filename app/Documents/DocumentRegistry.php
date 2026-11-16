<?php

namespace App\Documents;

use App\Documents\Models\IssuedDocument;
use App\Models\Order;
use App\Models\SellerStatement;
use Illuminate\Support\Facades\Storage;

/**
 * سجل المستندات الصادرة: يعطي كل PDF رمز تحقق، يحفظ ملفه وبصمته،
 * ويجيب صفحة التحقق عن صحته وحالته الحالية.
 */
class DocumentRegistry
{
    /** بلا أحرف ملتبسة (0/O، 1/I/L) لتسهيل الكتابة اليدوية. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 12;

    public const DISK = 'documents';

    public function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (IssuedDocument::where('code', $code)->exists());

        return $code;
    }

    /** "ts-abcd efgh-jkmn" ← "ABCDEFGHJKMN"؛ null إن لم يكن رمزاً صالح الشكل. */
    public static function normalize(?string $input): ?string
    {
        $s = strtoupper(preg_replace('/[\s\-_]+/', '', (string) $input));
        if (str_starts_with($s, 'TS') && strlen($s) === self::LENGTH + 2) {
            $s = substr($s, 2);
        }

        return preg_match('/^['.self::ALPHABET.']{'.self::LENGTH.'}$/', $s) ? $s : null;
    }

    /** بيانات ختم التحقق التي يضعها قالب الـ PDF. */
    public static function stamp(string $code): array
    {
        // عنوان الموقع الرسمي (APP_URL) لا عنوان الطلب الحالي: الرمز يُمسح من أي هاتف لاحقاً
        return ['code' => $code, 'url' => rtrim((string) config('app.url'), '/').'/verify/'.$code];
    }

    public static function fingerprint(string $type, int $subjectId, array $summary, ?array $details): string
    {
        return hash('sha256', json_encode([$type, $subjectId, $summary, $details], JSON_UNESCAPED_UNICODE));
    }

    /**
     * يُصدر PDF مسجّلاً. نفس المستند ببيانات لم تتغير ← نفس الرمز ونفس الملف.
     * $render(array $stamp): string يعيد بايتات الـ PDF وفيها الختم.
     */
    public function issue(string $type, int $subjectId, ?int $ownerId, array $summary, ?array $details, callable $render, ?int $issuedBy = null): IssuedPdf
    {
        $fingerprint = self::fingerprint($type, $subjectId, $summary, $details);

        $existing = IssuedDocument::where('type', $type)->where('subject_id', $subjectId)
            ->where('fingerprint', $fingerprint)->latest('id')->first();
        if ($existing && Storage::disk(self::DISK)->exists($existing->file_path)) {
            return new IssuedPdf($existing, Storage::disk(self::DISK)->get($existing->file_path));
        }

        $code = $this->newCode();
        $bytes = $render(self::stamp($code));

        return new IssuedPdf($this->store($code, $type, $subjectId, $ownerId, $summary, $details, $bytes, $issuedBy, $fingerprint), $bytes);
    }

    /** لمستند له ملفه الخاص (كشف الحساب): الرمز وُلّد مسبقاً ووُضع في الملف. */
    public function register(string $code, string $type, int $subjectId, ?int $ownerId, array $summary, ?array $details, string $bytes, ?int $issuedBy = null): IssuedDocument
    {
        return $this->store($code, $type, $subjectId, $ownerId, $summary, $details, $bytes, $issuedBy,
            self::fingerprint($type, $subjectId, $summary, $details));
    }

    public function findByFile(string $bytes): ?IssuedDocument
    {
        return IssuedDocument::where('file_hash', hash('sha256', $bytes))->latest('id')->first();
    }

    /** valid | order_cancelled | outdated */
    public function status(IssuedDocument $doc): string
    {
        if ($doc->type === 'invoice') {
            $order = Order::with('items.vendor', 'payment')->find($doc->subject_id);
            if ($order && $order->status === 'cancelled') {
                return 'order_cancelled';
            }
            if ($order) {
                [$summary, $details] = DocumentSnapshots::invoice($order);
                if (self::fingerprint('invoice', $order->id, $summary, $details) !== $doc->fingerprint) {
                    return 'outdated';
                }
            }
        }

        if ($doc->type === 'seller_statement'
            && SellerStatement::whereKey($doc->subject_id)->value('status') === 'superseded') {
            return 'outdated';
        }

        return 'valid';
    }

    private function store(string $code, string $type, int $subjectId, ?int $ownerId, array $summary, ?array $details, string $bytes, ?int $issuedBy, string $fingerprint): IssuedDocument
    {
        $path = "{$code}.pdf";
        Storage::disk(self::DISK)->put($path, $bytes);

        return IssuedDocument::create([
            'code' => $code, 'type' => $type, 'subject_id' => $subjectId, 'fingerprint' => $fingerprint,
            'owner_user_id' => $ownerId, 'issued_by' => $issuedBy ?? auth()->id(),
            'summary' => $summary, 'details' => $details,
            'file_hash' => hash('sha256', $bytes), 'file_path' => $path,
        ]);
    }
}
