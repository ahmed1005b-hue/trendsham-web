<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محفظة الكوبونات: حملة ← نسخة لكل عميل ← سجل استخدام بالطلب.
 * من أنشأ الكوبون يتحمّل خصمه (funder). جدول coupons القديم (بالرموز) يبقى فارغاً حتى المرحلة 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_campaigns', function (Blueprint $t) {
            $t->id();
            $t->enum('funder', ['platform', 'seller']);
            $t->foreignId('seller_profile_id')->nullable()->constrained('seller_profiles')->cascadeOnDelete();
            $t->string('title', 120);
            $t->enum('type', ['percentage', 'fixed', 'free_shipping', 'gift']);
            $t->unsignedBigInteger('value')->default(0);            // نسبة (1..100) أو مبلغ بالوحدة الصغرى
            $t->unsignedBigInteger('max_discount')->nullable();      // حد أعلى لخصم النسبة
            $t->unsignedBigInteger('min_order_amount')->default(0);
            $t->enum('scope', ['all', 'products', 'categories', 'stores'])->default('all');
            $t->enum('audience', ['new_customers', 'all_customers', 'specific', 'collectable', 'followers', 'automatic']);
            $t->string('trigger', 40)->nullable();                   // للتلقائية (المرحلة 3)
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('valid_days')->nullable();           // صلاحية نسخة المحفظة من يوم الإصدار
            $t->unsignedInteger('per_customer_limit')->default(1);
            $t->unsignedInteger('total_limit')->nullable();
            $t->unsignedInteger('issued_count')->default(0);
            $t->unsignedInteger('redeemed_count')->default(0);
            $t->enum('status', ['active', 'paused'])->default('active');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'audience']);
        });

        Schema::create('coupon_campaign_targets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('coupon_campaigns')->cascadeOnDelete();
            $t->enum('target_type', ['product', 'category', 'store', 'user']);
            $t->unsignedBigInteger('target_id');
            $t->unique(['campaign_id', 'target_type', 'target_id']);
        });

        Schema::create('customer_coupons', function (Blueprint $t) {
            $t->id();
            // restrict: حملة استُخدمت لا تُحذف (تبقى السجلات المالية)
            $t->foreignId('campaign_id')->constrained('coupon_campaigns')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['available', 'reserved', 'used', 'expired', 'revoked'])->default('available');
            $t->timestamp('expires_at')->nullable();
            $t->string('source', 40);
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
            $t->index(['campaign_id', 'user_id']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_coupon_id')->constrained('customer_coupons')->restrictOnDelete();
            $t->foreignId('campaign_id')->constrained('coupon_campaigns')->restrictOnDelete();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('funder', ['platform', 'seller']);
            $t->foreignId('seller_profile_id')->nullable()->constrained('seller_profiles')->nullOnDelete();
            $t->unsignedBigInteger('discount_amount')->default(0);
            $t->unsignedBigInteger('shipping_discount')->default(0);
            $t->enum('status', ['applied', 'reversed'])->default('applied');
            $t->timestamps();
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('customer_coupon_id')->nullable()->after('coupon_id')->constrained('customer_coupons')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $t) {
            $t->enum('discount_funder', ['platform', 'seller'])->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('discount_funder'));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('customer_coupon_id');
        });
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('customer_coupons');
        Schema::dropIfExists('coupon_campaign_targets');
        Schema::dropIfExists('coupon_campaigns');
    }
};
