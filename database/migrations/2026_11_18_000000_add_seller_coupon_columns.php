<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * كوبونات البائع (المرحلة 2): حالات مراجعة المدير، الهدية، الإرسال للمتابعين، وبند الهدية في الطلب.
 * إضافية فقط؛ الحالة الافتراضية active تُبقي كوبونات المنصة كما هي.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE coupon_campaigns MODIFY status ENUM('pending_review','active','paused','rejected','suspended') NOT NULL DEFAULT 'active'");
        }

        Schema::table('coupon_campaigns', function (Blueprint $t) {
            $t->boolean('auto_followers')->default(false)->after('audience');
            $t->foreignId('buy_product_id')->nullable()->after('min_order_amount')->constrained('products')->nullOnDelete();
            $t->foreignId('gift_product_id')->nullable()->after('buy_product_id')->constrained('products')->nullOnDelete();
            $t->string('review_note', 500)->nullable()->after('status');
            $t->foreignId('reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $t->timestamp('followers_issued_at')->nullable()->after('reviewed_at');
            $t->index(['seller_profile_id', 'status']);
        });

        Schema::table('order_items', function (Blueprint $t) {
            $t->boolean('is_gift')->default(false)->after('discount_funder');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('is_gift'));

        Schema::table('coupon_campaigns', function (Blueprint $t) {
            $t->dropIndex(['seller_profile_id', 'status']);
            $t->dropConstrainedForeignId('buy_product_id');
            $t->dropConstrainedForeignId('gift_product_id');
            $t->dropConstrainedForeignId('reviewed_by');
            $t->dropColumn(['auto_followers', 'review_note', 'reviewed_at', 'followers_issued_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE coupon_campaigns SET status = 'paused' WHERE status IN ('pending_review','rejected','suspended')");
            DB::statement("ALTER TABLE coupon_campaigns MODIFY status ENUM('active','paused') NOT NULL DEFAULT 'active'");
        }
    }
};
