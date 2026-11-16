<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_accounts', function (Blueprint $t) {
            $t->id();
            // الحساب مربوط بالمستخدم البائع نفسه (هوية حساب البائع هي user_id)
            $t->foreignId('seller_id')->unique()->constrained('users');
            $t->string('currency', 3)->default('SYP');
            $t->decimal('balance', 16, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_id')->constrained('users');
            $t->string('entry_type', 30);
            $t->decimal('debit', 16, 2)->default(0);
            $t->decimal('credit', 16, 2)->default(0);
            $t->decimal('balance_after', 16, 2);
            $t->string('currency', 3)->default('SYP');
            $t->string('description', 255);
            $t->string('source_type', 40)->nullable();   // order_item | withdrawal | showcase | bootstrap | manual
            $t->unsignedBigInteger('source_id')->nullable();
            $t->foreignId('reverses_entry_id')->nullable()->constrained('ledger_entries');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('posted_at')->useCurrent();
            $t->timestamps();

            $t->index(['seller_id', 'id']);
            $t->index(['seller_id', 'posted_at']);
            $t->index(['entry_type', 'posted_at']);
            // يمنع تسجيل نفس الحدث مرتين (idempotency). القيود اليدوية بدون مصدر لا تتأثر.
            $t->unique(['seller_id', 'entry_type', 'source_type', 'source_id'], 'ledger_idempotency');
        });

        Schema::create('seller_statements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_id')->constrained('users');
            $t->string('number', 40)->unique();          // ST-202611-15-001
            $t->date('period_from');
            $t->date('period_to');
            $t->decimal('opening_balance', 16, 2);
            $t->decimal('total_debit', 16, 2);
            $t->decimal('total_credit', 16, 2);
            $t->decimal('closing_balance', 16, 2);
            $t->json('totals_by_type');
            $t->unsignedInteger('entries_count');
            $t->string('checksum', 64);
            $t->string('file_path');
            $t->string('status', 12)->default('final'); // final | superseded
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['seller_id', 'period_from', 'period_to']);
        });

        Schema::create('accounting_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 40);   // view_ledger | export_csv | generate_statement | download_statement | adjustment
            $t->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedBigInteger('statement_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_logs');
        Schema::dropIfExists('seller_statements');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('seller_accounts');
    }
};
