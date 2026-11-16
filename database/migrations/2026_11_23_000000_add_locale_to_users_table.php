<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لغة المستخدم المفضّلة (ar/en/tr) لما يُرسل له في الخلفية: إشعارات الهاتف والرسائل والبريد.
 * فارغة = عربي. يحدّثها التطبيق والموقع تلقائياً من لغة الواجهة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
