<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    protected $fillable = ['vendor_id', 'balance'];

    public function vendor()
    {
        return $this->belongsTo(SellerProfile::class, 'vendor_id');
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance / 100).' ل.س';
    }

    /**
     * إيداع أو سحب من المحفظة ضمن معاملة محمية بقفل صفوف، لتفادي تضارب
     * لو صارت عدة عمليات على نفس المحفظة بنفس اللحظة.
     */
    /**
     * خصم فقط إن كان الرصيد يكفي، والفحص والخصم تحت نفس القفل: طلبان متزامنان
     * لا يمرّان معاً من فحص الرصيد ثم يُنزلانه تحت الصفر. يعيد null إن لم يكفِ.
     */
    public function debitIfAvailable(int $amount, string $reason, array $extra = []): ?WalletTransaction
    {
        return DB::transaction(function () use ($amount, $reason, $extra) {
            $balance = static::whereKey($this->id)->lockForUpdate()->value('balance');
            if ($balance === null || $balance < $amount) {
                return null;
            }

            return $this->applyTransaction('debit', $amount, $reason, $extra);
        });
    }

    public function applyTransaction(string $type, int $amount, string $reason, array $extra = []): WalletTransaction
    {
        return DB::transaction(function () use ($type, $amount, $reason, $extra) {
            $wallet = static::whereKey($this->id)->lockForUpdate()->first();

            $newBalance = $type === 'credit'
                ? $wallet->balance + $amount
                : $wallet->balance - $amount;

            $wallet->update(['balance' => $newBalance]);

            return $wallet->transactions()->create([
                ...$extra,
                'type' => $type,
                'reason' => $reason,
                'amount' => $amount,
                'balance_after' => $newBalance,
            ]);
        });
    }
}
