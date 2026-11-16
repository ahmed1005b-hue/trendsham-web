<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** لون خط الإعلان يختاره البائع بحرية (text_color يبقى light|dark للتطبيقات القديمة) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showcases', function (Blueprint $t) {
            $t->string('text_hex', 7)->nullable()->after('text_color');
        });
    }

    public function down(): void
    {
        Schema::table('showcases', function (Blueprint $t) {
            $t->dropColumn('text_hex');
        });
    }
};
