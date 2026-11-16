<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showcase_slots', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();            // home_top | home_mid | home_bottom
            $t->string('name');
            $t->unsignedInteger('position')->default(0); // ترتيب العرض في الشاشة الرئيسية
            $t->decimal('price_per_day', 12, 2)->default(0);
            $t->unsignedSmallInteger('min_days')->default(1);
            $t->unsignedSmallInteger('max_days')->default(30);
            $t->unsignedSmallInteger('capacity')->default(1);       // عدد العروض المتزامنة
            $t->unsignedSmallInteger('min_products')->default(5);
            $t->unsignedSmallInteger('max_products')->default(10);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('showcases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('slot_id')->constrained('showcase_slots');
            $t->foreignId('seller_id')->constrained('seller_profiles');       // البائع (SellerProfile)
            $t->string('title', 60);
            $t->string('subtitle', 100)->nullable();
            $t->json('gradient_colors');                            // ["#0F3D2E","#D4A537"]
            $t->string('gradient_direction', 12)->default('diagonal'); // vertical|horizontal|diagonal
            $t->string('text_color', 5)->default('light');          // light|dark (يحسبه السيرفر)
            $t->date('start_date');
            $t->date('end_date');
            $t->decimal('amount', 12, 2);
            $t->string('status', 20)->default('pending_review');    // pending_review|approved|rejected|cancelled|expired
            $t->string('rejection_reason')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->timestamps();

            $t->index(['status', 'start_date', 'end_date']);
        });

        Schema::create('showcase_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('showcase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->unique(['showcase_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_products');
        Schema::dropIfExists('showcases');
        Schema::dropIfExists('showcase_slots');
    }
};