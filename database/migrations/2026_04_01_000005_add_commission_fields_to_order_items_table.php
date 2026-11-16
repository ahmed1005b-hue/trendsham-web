<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->bigInteger('commission_amount')->nullable()->after('subtotal');
            $table->bigInteger('vendor_earning')->nullable()->after('commission_amount');
            $table->timestamp('commission_settled_at')->nullable()->after('vendor_earning');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'vendor_earning', 'commission_settled_at']);
        });
    }
};
