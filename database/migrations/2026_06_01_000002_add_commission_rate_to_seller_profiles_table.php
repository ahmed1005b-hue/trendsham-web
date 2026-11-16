<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            // null = يعتمد على نسبة العمولة العامة بـ platform_settings
            // قيمة محدّدة = اتفاق خاص متفاوَض عليه مع هذا البائع تحديداً
            $table->unsignedTinyInteger('commission_rate')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
