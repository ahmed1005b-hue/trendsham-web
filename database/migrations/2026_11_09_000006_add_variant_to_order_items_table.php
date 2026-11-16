<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('variant_label')->nullable()->after('product_name');
            if (!Schema::hasColumn('order_items', 'discount_amount')) {
                $table->unsignedBigInteger('discount_amount')->default(0)->after('subtotal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            if (Schema::hasColumn('order_items', 'discount_amount')) { try { $table->dropColumn('discount_amount'); } catch (\Throwable $e) {} }
            $table->dropColumn(['variant_id', 'variant_label']);
        });
    }
};
