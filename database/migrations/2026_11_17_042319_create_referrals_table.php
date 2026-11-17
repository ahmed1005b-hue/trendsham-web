<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** «دعوة صديق»: من دعا من، وحالة مكافأة النقاط لكليهما. */
return new class extends Migration
{
    private const LOYALTY_TYPES = ['earn', 'restore', 'admin_add', 'redeem_checkout', 'redeem_coupon',
        'reverse_earn', 'expire', 'admin_deduct', 'debt_settle'];

    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referee_id')->unique()->constrained('users')->cascadeOnDelete(); // لكل حساب داعٍ واحد فقط
            $table->enum('status', ['pending', 'rewarded', 'reversed'])->default('pending');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete(); // الطلب الذي استحق المكافأة
            $table->unsignedInteger('referrer_points')->default(0);
            $table->unsignedInteger('referee_points')->default(0);
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
        });

        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->enum('type', [...self::LOYALTY_TYPES, 'referral', 'referral_reverse'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->enum('type', self::LOYALTY_TYPES)->change();
        });

        Schema::dropIfExists('referrals');
    }
};
