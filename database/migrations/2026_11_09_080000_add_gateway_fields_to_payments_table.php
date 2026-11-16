<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $t) {
                if (!Schema::hasColumn('payments', 'gateway')) {
                    $t->string('gateway', 30)->nullable()->after('order_id');
                }
                if (!Schema::hasColumn('payments', 'reference')) {
                    $t->uuid('reference')->nullable()->unique()->after('gateway');
                }
                if (!Schema::hasColumn('payments', 'currency')) {
                    $t->string('currency', 3)->default('SYP')->after('amount');
                }
                if (!Schema::hasColumn('payments', 'gateway_txn_id')) {
                    $t->string('gateway_txn_id')->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('payments', 'payload')) {
                    $t->json('payload')->nullable()->after('gateway_txn_id');
                }
            });
        }

        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'payment_status')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->string('payment_status', 20)->default('unpaid')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'payment_status')) {
            Schema::table('orders', function (Blueprint $t) { $t->dropColumn('payment_status'); });
        }
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $t) {
                foreach (['payload','gateway_txn_id','currency','reference','gateway'] as $c) {
                    if (Schema::hasColumn('payments', $c)) $t->dropColumn($c);
                }
            });
        }
    }
};
