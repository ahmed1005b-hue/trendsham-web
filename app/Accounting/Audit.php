<?php

namespace App\Accounting;

use App\Models\AccountingAuditLog;
use Illuminate\Http\Request;

class Audit
{
    public static function log(Request $r, string $action, ?int $sellerId = null, ?int $statementId = null): void
    {
        AccountingAuditLog::create([
            'user_id' => $r->user()?->id,
            'action' => $action,
            'seller_id' => $sellerId,
            'statement_id' => $statementId,
            'ip' => $r->ip(),
        ]);
    }
}
