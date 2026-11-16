<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إيقاف الحساب مؤقتاً وطلب حذفه نهائياً (مهلة 30 يوماً للتراجع).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('status');
            $table->timestamp('deletion_requested_at')->nullable()->after('deactivated_at');
            $table->index('deletion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deletion_requested_at']);
            $table->dropColumn(['deactivated_at', 'deletion_requested_at']);
        });
    }
};
