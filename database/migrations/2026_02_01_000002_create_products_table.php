<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->unsignedBigInteger('price'); // بالقرش/الوحدة الصغرى لتفادي أخطاء الفاصلة العشرية
            $table->unsignedBigInteger('compare_at_price')->nullable(); // السعر قبل الخصم
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->fullText(['name', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
