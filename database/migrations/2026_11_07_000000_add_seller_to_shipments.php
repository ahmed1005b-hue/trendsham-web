<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('seller_profile_id')->nullable()->after('order_id')->constrained('seller_profiles')->nullOnDelete();
            $table->index(['order_id', 'seller_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'seller_profile_id']);
            $table->dropConstrainedForeignId('seller_profile_id');
        });
    }
};