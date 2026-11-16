<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('commission_rate')->default(10); // نسبة مئوية
            $table->timestamps();
        });

        DB::table('platform_settings')->insert(['commission_rate' => 10, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
