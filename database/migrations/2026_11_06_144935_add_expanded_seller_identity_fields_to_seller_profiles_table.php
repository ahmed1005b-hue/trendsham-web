<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->string('identity_type', 32)->nullable()->after('commercial_register');
            $table->string('identity_number', 64)->nullable()->after('identity_type');
            $table->string('identity_document_back_path')->nullable()->after('identity_document_path');
            $table->timestamp('identity_verified_at')->nullable()->after('identity_document_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'identity_type',
                'identity_number',
                'identity_document_back_path',
                'identity_verified_at',
            ]);
        });
    }
};
