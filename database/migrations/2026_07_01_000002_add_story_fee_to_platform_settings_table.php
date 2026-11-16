<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            // بالوحدة الصغرى (قرش) - رسم نشر ستوري واحدة للبائع، قابل للتعديل من لوحة المدير
            $table->unsignedBigInteger('story_fee')->default(500000)->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('story_fee');
        });
    }
};
