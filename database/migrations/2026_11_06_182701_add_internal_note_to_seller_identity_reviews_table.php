<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_identity_reviews', function (Blueprint $table) {
            $table->string('internal_note', 500)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('seller_identity_reviews', function (Blueprint $table) {
            $table->dropColumn('internal_note');
        });
    }
};
