<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('color')->nullable(); // كود هيكس '#RRGGBB' أو اسم اللون
            $table->string('color_name')->nullable(); // اسم عرضي مثل "أحمر"
            $table->string('size')->nullable(); // م، ك، L، XL، إلخ
            $table->string('sku')->nullable()->unique(); // رمز فرعي للمتغير
            $table->unsignedBigInteger('price_adjustment')->default(0); // تعديل على السعر الأساسي بالقرش
            $table->unsignedInteger('stock_quantity')->default(0); // مخزون هذا المتغير
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'color', 'size']);
            $table->unique(['product_id', 'color', 'size'], 'unique_product_color_size');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
