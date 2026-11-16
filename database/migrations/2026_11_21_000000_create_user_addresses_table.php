<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عناوين الشحن المحفوظة للمستخدم (حتى 3، يفرضها UserAddressService):
 * نوع (بيت/عمل/آخر) + مدينة + تفاصيل + إحداثيات اختيارية من "موقعي الحالي"،
 * وعنوان واحد معتمد للشحن. الطلبات تبقى تحفظ نص العنوان لحظة الشراء.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 10); // home | work | other
            $table->string('city', 120);
            $table->string('details', 500);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
