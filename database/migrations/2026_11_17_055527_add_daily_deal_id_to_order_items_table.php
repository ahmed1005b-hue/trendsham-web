<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** البند بيع بسعر «عرض اليوم» هذا، فإلغاء الطلب يعيد الكمية إلى العرض. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('daily_deal_id')->nullable()->after('variant_id')->constrained('daily_deals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('daily_deal_id');
        });
    }
};
