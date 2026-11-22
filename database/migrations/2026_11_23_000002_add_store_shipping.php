<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شحن مستقل لكل متجر: المتجر يختار التوصيل بنفسه بأسعاره لكل محافظة (والباقي عبر شركة المنصة)،
 * أو يسمح بالاستلام من محله. المبالغ بالوحدة الصغرى ×100.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_shipping_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->unique()->constrained('seller_profiles')->cascadeOnDelete();
            $table->string('mode', 10)->default('platform'); // platform | self
            $table->unsignedBigInteger('free_over')->nullable(); // شحن مجاني إن بلغت مشتريات الزبون من المتجر هذا المبلغ
            $table->unsignedTinyInteger('prep_days')->default(1);
            $table->unsignedTinyInteger('delivery_days')->default(2);
            $table->boolean('allow_pickup')->default(false);
            $table->string('pickup_address', 500)->nullable();
            $table->timestamps();
        });

        // المحافظات التي يوصل إليها المتجر بنفسه وأجرة كل منها؛ غيرها يُشحن عبر شركة المنصة
        Schema::create('store_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->string('governorate', 40);
            $table->unsignedBigInteger('fee')->default(0);
            $table->timestamps();
            $table->unique(['seller_profile_id', 'governorate']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->string('delivery_mode', 10)->default('platform')->after('shipping_company_id'); // platform | self | pickup
            $table->unsignedBigInteger('cod_amount')->default(0)->after('shipping_cost'); // ما يُحصَّل نقداً عند التسليم
            $table->timestamp('cash_settled_at')->nullable()->after('refused_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_governorate', 40)->nullable()->after('shipping_address');
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_debt_limit')->default(10000000); // 100,000 ل.س
            $table->unsignedSmallInteger('self_delivery_min_orders')->default(5);
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['seller_debt_limit', 'self_delivery_min_orders']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_governorate');
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['delivery_mode', 'cod_amount', 'cash_settled_at']);
        });
        Schema::dropIfExists('store_shipping_rates');
        Schema::dropIfExists('store_shipping_settings');
    }
};
