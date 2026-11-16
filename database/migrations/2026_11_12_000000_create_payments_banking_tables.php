<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * البنوك والبطاقات وتحويل أرباح البائعين:
 *  - customer_cards: بطاقات العملاء المحفوظة (توكن من البنك فقط، لا رقم بطاقة ولا CVV).
 *  - payment_methods: نوع "حساب بنكي/IBAN" + تشفير رقم الحساب/البطاقة.
 *  - withdrawal_requests: مراحل التحويل (موافقة ← تم التحويل/فشل) ورقم الحوالة والإيصال.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('gateway', 30);
            $t->uuid('reference')->unique();            // مرجعنا لعملية الحفظ عند البنك
            $t->text('token')->nullable();              // توكن البنك (مشفّر)
            $t->string('brand', 30)->nullable();        // visa | mastercard | ...
            $t->string('last4', 4)->nullable();
            $t->unsignedTinyInteger('exp_month')->nullable();
            $t->unsignedSmallInteger('exp_year')->nullable();
            $t->string('holder_name')->nullable();
            $t->enum('status', ['pending', 'active', 'failed'])->default('pending');
            $t->boolean('is_default')->default(false);
            $t->timestamps();

            $t->index(['user_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->foreignId('customer_card_id')->nullable()->after('order_id')
                ->constrained('customer_cards')->nullOnDelete();
        });

        // ---- طرق استلام أرباح البائع ----
        DB::statement("ALTER TABLE payment_methods MODIFY type ENUM('bank_account','bank_card','electronic_wallet') NOT NULL");
        Schema::table('payment_methods', function (Blueprint $t) {
            $t->text('account_number')->change();
            $t->text('account_holder_name')->change();
            $t->string('last4', 4)->nullable()->after('account_number');
            $t->timestamp('verified_at')->nullable()->after('is_verified');
            $t->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
        });

        // تشفير البيانات المحفوظة سابقاً كنص صريح
        DB::table('payment_methods')->orderBy('id')->each(function ($row) {
            $number = $this->plain($row->account_number);
            $holder = $this->plain($row->account_holder_name);
            DB::table('payment_methods')->where('id', $row->id)->update([
                'account_number' => Crypt::encryptString($number),
                'account_holder_name' => Crypt::encryptString($holder),
                'last4' => substr(preg_replace('/\s+/', '', $number), -4),
            ]);
        });

        // ---- مراحل تحويل الأرباح ----
        DB::statement("ALTER TABLE withdrawal_requests MODIFY status ENUM('pending','approved','processing','paid','rejected','failed') NOT NULL DEFAULT 'pending'");
        Schema::table('withdrawal_requests', function (Blueprint $t) {
            $t->foreignId('payment_method_id')->nullable()->after('vendor_id')
                ->constrained('payment_methods')->nullOnDelete();
            $t->string('payout_gateway', 30)->nullable()->after('payout_account_holder_name');
            $t->string('transfer_reference')->nullable()->after('payout_gateway');
            $t->string('receipt_path')->nullable()->after('transfer_reference');
            $t->string('failure_reason')->nullable()->after('receipt_path');
            $t->json('gateway_payload')->nullable()->after('failure_reason');
            $t->foreignId('processed_by')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();
            $t->timestamp('paid_at')->nullable()->after('processed_by');
        });

        DB::statement("ALTER TABLE wallet_transactions MODIFY reason ENUM('order_earning','withdrawal','withdrawal_reversal','adjustment','story_fee') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE wallet_transactions MODIFY reason ENUM('order_earning','withdrawal','adjustment','story_fee') NOT NULL");

        Schema::table('withdrawal_requests', function (Blueprint $t) {
            $t->dropConstrainedForeignId('payment_method_id');
            $t->dropConstrainedForeignId('processed_by');
            $t->dropColumn(['payout_gateway', 'transfer_reference', 'receipt_path', 'failure_reason', 'gateway_payload', 'paid_at']);
        });
        DB::table('withdrawal_requests')->whereIn('status', ['processing', 'failed'])->update(['status' => 'approved']);
        DB::statement("ALTER TABLE withdrawal_requests MODIFY status ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending'");

        DB::table('payment_methods')->orderBy('id')->each(function ($row) {
            DB::table('payment_methods')->where('id', $row->id)->update([
                'account_number' => $this->plain($row->account_number),
                'account_holder_name' => $this->plain($row->account_holder_name),
            ]);
        });
        Schema::table('payment_methods', function (Blueprint $t) {
            $t->dropConstrainedForeignId('verified_by');
            $t->dropColumn(['last4', 'verified_at']);
        });
        DB::table('payment_methods')->where('type', 'bank_account')->update(['type' => 'bank_card']);
        DB::statement("ALTER TABLE payment_methods MODIFY type ENUM('bank_card','electronic_wallet') NOT NULL");

        Schema::table('payments', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_card_id'));
        Schema::dropIfExists('customer_cards');
    }

    /** يقبل قيمة مشفّرة أو صريحة ويعيد النص الصريح (يجعل الترحيل آمناً للتكرار). */
    private function plain(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
};
