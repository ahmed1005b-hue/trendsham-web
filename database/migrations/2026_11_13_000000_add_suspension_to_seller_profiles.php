<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إيقاف المتجر: بديل آمن لحذف البائع (تبقى المحفظة والطلبات والقيود المحاسبية).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE seller_profiles MODIFY status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending'");

        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->string('suspension_reason', 500)->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        // المتاجر الموقوفة تعود "معتمدة" قبل حذف القيمة من القائمة
        DB::table('seller_profiles')->where('status', 'suspended')->update(['status' => 'approved']);

        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });

        DB::statement("ALTER TABLE seller_profiles MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
};
