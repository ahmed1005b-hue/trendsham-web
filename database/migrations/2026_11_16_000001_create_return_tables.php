<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** المرتجعات والنزاعات: الطلب، بنوده، سجل أحداثه، والاسترداد اليدوي. المبالغ بالوحدة الصغرى ×100 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('seller_profile_id')->constrained('seller_profiles')->restrictOnDelete();
            $t->string('status')->index();
            $t->string('reason');
            $t->text('description')->nullable();
            $t->unsignedBigInteger('refund_amount')->default(0);
            $t->unsignedBigInteger('shipping_refund')->default(0);
            $t->timestamp('respond_by')->nullable();
            $t->timestamp('dispute_by')->nullable();
            $t->timestamp('ship_back_by')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('decision_note')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();

            $t->index(['seller_profile_id', 'status']);
            $t->index(['user_id', 'status']);
        });

        Schema::create('return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('refund_amount')->default(0);
            $t->unsignedBigInteger('seller_reversal')->default(0);
            $t->unsignedBigInteger('commission_reversal')->default(0);
            $t->timestamp('settled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('return_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_role');
            $t->string('type');
            $t->text('note')->nullable();
            $t->json('photos')->nullable();
            $t->timestamps();
        });

        Schema::create('return_refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_request_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('amount');
            $t->string('method');
            $t->string('reference')->nullable();
            $t->text('note')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_refunds');
        Schema::dropIfExists('return_events');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_requests');
    }
};
