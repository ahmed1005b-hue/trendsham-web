<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إذن البائع لفريق الدعم بفتح لوحة متجره. يبقى مفتوحاً حتى يُنهيه البائع (revoked_at).
     */
    public function up(): void
    {
        Schema::create('seller_support_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['seller_profile_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_support_grants');
    }
};
