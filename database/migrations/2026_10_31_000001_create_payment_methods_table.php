<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->enum('type', ['bank_card', 'electronic_wallet']);
            $table->string('provider'); // e.g. 'syriatel_cash', 'amt', 'bloom', 'bank_name'
            $table->string('account_number'); // card number or wallet number
            $table->string('account_holder_name'); // owner name
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['vendor_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
