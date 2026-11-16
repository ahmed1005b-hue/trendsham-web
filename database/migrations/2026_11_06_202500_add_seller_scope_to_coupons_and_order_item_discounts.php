<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'seller_profile_id')) {
                $table->foreignId('seller_profile_id')->nullable()->after('id')->constrained('seller_profiles')->nullOnDelete();
            }
            if (!Schema::hasColumn('coupons', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('seller_profile_id')->constrained('products')->nullOnDelete();
            }
        });
        try { Schema::table('coupons', function (Blueprint $table) { $table->index(['seller_profile_id', 'is_active']); }); } catch (\Throwable $e) {}

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'discount_amount')) {
                $table->unsignedBigInteger('discount_amount')->default(0)->after('subtotal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['seller_profile_id', 'is_active']);
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('seller_profile_id');
        });
    }
};