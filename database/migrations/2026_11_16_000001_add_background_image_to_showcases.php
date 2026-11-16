<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** صورة خلفية اختيارية يرفعها البائع للإعلان (فوق التدرج) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showcases', function (Blueprint $t) {
            $t->string('background_image')->nullable()->after('text_hex');
        });
    }

    public function down(): void
    {
        Schema::table('showcases', function (Blueprint $t) {
            $t->dropColumn('background_image');
        });
    }
};
