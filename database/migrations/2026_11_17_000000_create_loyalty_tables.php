<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** نقاط الولاء: رصيد العميل، سجل الحركات (الدفعات بصلاحية)، وتوزيع كل استهلاك على الدفعات */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->unsignedInteger('balance')->default(0);          // القابل للاستخدام الآن
            $t->unsignedInteger('debt')->default(0);             // مستحق من سحب نقاط مصروفة
            $t->unsignedInteger('lifetime_earned')->default(0);
            $t->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('type', ['earn', 'restore', 'admin_add', 'redeem_checkout', 'redeem_coupon',
                'reverse_earn', 'expire', 'admin_deduct', 'debt_settle']);
            $t->integer('points');                                 // موجب للإضافة، سالب للخصم
            $t->unsignedInteger('remaining')->nullable();          // للدفعات فقط
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('expiry_notified_at')->nullable();
            $t->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $t->foreignId('customer_coupon_id')->nullable()->constrained('customer_coupons')->nullOnDelete();
            $t->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            // "مرة واحدة لكل طلب" لحركات earn/reverse_earn/redeem_checkout (نوع:رقم الطلب)
            $t->string('once_key', 60)->nullable()->unique();
            $t->timestamp('created_at')->nullable();

            $t->index(['user_id', 'expires_at']);
            $t->index(['user_id', 'id']);
        });

        Schema::create('loyalty_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('debit_transaction_id')->constrained('loyalty_transactions')->cascadeOnDelete();
            $t->foreignId('lot_transaction_id')->constrained('loyalty_transactions')->cascadeOnDelete();
            $t->unsignedInteger('points');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_allocations');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
    }
};
