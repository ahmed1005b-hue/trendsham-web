<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** خصم النقاط على الطلب، وإعدادات البرنامج في إعدادات المنصة */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedInteger('points_redeemed')->default(0)->after('discount_amount');
            $t->unsignedBigInteger('points_discount')->default(0)->after('points_redeemed');   // ×100، تتحمّله المنصة
        });

        Schema::table('platform_settings', function (Blueprint $t) {
            $t->boolean('loyalty_enabled')->default(true);
            $t->unsignedBigInteger('loyalty_spend_per_point')->default(1000000);   // 10,000 ل.س
            $t->unsignedBigInteger('loyalty_point_value')->default(20000);         // 200 ل.س
            $t->unsignedInteger('loyalty_min_redeem')->default(10);
            $t->unsignedInteger('loyalty_expiry_months')->default(12);
            $t->unsignedInteger('loyalty_coupon_valid_days')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn(['points_redeemed', 'points_discount']);
        });
        Schema::table('platform_settings', function (Blueprint $t) {
            $t->dropColumn(['loyalty_enabled', 'loyalty_spend_per_point', 'loyalty_point_value',
                'loyalty_min_redeem', 'loyalty_expiry_months', 'loyalty_coupon_valid_days']);
        });
    }
};
