<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** مُهَل المرتجعات (يعدّلها المدير). null = القيمة الافتراضية في ReturnSettings */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $t) {
            $t->unsignedInteger('returns_window_days')->nullable();
            $t->unsignedInteger('returns_seller_response_days')->nullable();
            $t->unsignedInteger('returns_dispute_days')->nullable();
            $t->unsignedInteger('returns_ship_back_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $t) {
            $t->dropColumn(['returns_window_days', 'returns_seller_response_days', 'returns_dispute_days', 'returns_ship_back_days']);
        });
    }
};
