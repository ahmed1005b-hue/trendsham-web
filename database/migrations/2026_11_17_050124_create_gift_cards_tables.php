<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بطاقات الهدايا: يشتريها الزبون بالبطاقة البنكية، ويُدخل صديقه كودها فيُضاف المبلغ
 * إلى «رصيد الهدايا» الذي يُدفع به عند الشراء. المبالغ بالوحدة الصغرى ×100.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchaser_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['pending_payment', 'active', 'redeemed', 'failed'])->default('pending_payment');
            $table->string('reference', 64)->unique();          // مرجع الدفع لدى البنك
            $table->string('gateway', 30);
            $table->string('gateway_txn_id')->nullable();
            $table->string('code_hash', 64)->nullable()->unique(); // للبحث عند الإدخال
            $table->text('code')->nullable();                     // مشفّر: يراه المشتري ليرسله
            $table->string('recipient_name', 60)->nullable();
            $table->string('message', 200)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();          // آخر موعد لإدخال الكود
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index(['purchaser_id', 'status']);
        });

        Schema::create('gift_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['redeem', 'spend', 'refund']);
            $table->bigInteger('amount');                          // موجب للإضافة، سالب للخصم
            $table->unsignedBigInteger('balance_after');
            $table->foreignId('gift_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('once_key', 60)->nullable()->unique(); // يمنع تكرار نفس الحركة
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('gift_balance_used')->default(0)->after('points_discount'); // ×100
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('gift_balance_used');
        });
        Schema::dropIfExists('gift_balance_transactions');
        Schema::dropIfExists('gift_cards');
    }
};
