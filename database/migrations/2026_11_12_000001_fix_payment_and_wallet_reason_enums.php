<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إصلاح قيم ENUM ناقصة كانت تُفشل الحفظ في MySQL (strict mode):
 *  - payments.status: الكود يستخدم 'cancelled' (محاولة ملغاة/أُغلقت بعد دفع ناجح آخر)
 *    و 'duplicate' (دفعة ثانية لطلب مدفوع أصلاً — تحتاج استرجاعاً يدوياً).
 *  - wallet_transactions.reason: حجز/استرجاع رسوم مساحات العرض (showcase_booking / showcase_refund).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','paid','failed','cancelled','duplicate','refunded') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE wallet_transactions MODIFY reason ENUM('order_earning','withdrawal','withdrawal_reversal','adjustment','story_fee','showcase_booking','showcase_refund') NOT NULL");
    }

    public function down(): void
    {
        DB::table('payments')->whereIn('status', ['cancelled', 'duplicate'])->update(['status' => 'failed']);
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending'");
        DB::table('wallet_transactions')->whereIn('reason', ['showcase_booking', 'showcase_refund'])->update(['reason' => 'adjustment']);
        DB::statement("ALTER TABLE wallet_transactions MODIFY reason ENUM('order_earning','withdrawal','withdrawal_reversal','adjustment','story_fee') NOT NULL");
    }
};
