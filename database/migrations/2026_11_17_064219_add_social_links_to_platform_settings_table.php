<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** روابط حسابات المتجر على مواقع التواصل؛ يكتبها المدير وتظهر أيقوناتها أسفل الموقع. */
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('social_instagram')->nullable();
            $table->string('social_facebook')->nullable();
            $table->string('social_tiktok')->nullable();
            $table->string('social_x')->nullable();
            $table->string('social_whatsapp', 20)->nullable(); // رقم فقط، والرابط يُبنى منه
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['social_instagram', 'social_facebook', 'social_tiktok', 'social_x', 'social_whatsapp']);
        });
    }
};
