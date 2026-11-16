<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->string('payout_method_type')->nullable()->after('amount');
            $table->string('payout_provider')->nullable()->after('payout_method_type');
            $table->text('payout_account_number')->nullable()->after('payout_provider');
            $table->text('payout_account_holder_name')->nullable()->after('payout_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'payout_method_type',
                'payout_provider',
                'payout_account_number',
                'payout_account_holder_name',
            ]);
        });
    }
};
