<?php

use Database\Seeders\ShippingCenterSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الشحن مع شركة كرم: أجرة لكل طرد (طرد لكل بائع) حسب الوزن، تسليم للبيت أو استلام من مركز،
 * ودين على الزبون الذي يرفض استلام طلبه مع منعه من الدفع عند الاستلام لشهر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_centers', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 10)->nullable(); // رقم المركز عند الشركة
            $table->string('name');
            $table->string('governorate', 40)->index();
            $table->string('address', 500);
            $table->string('phone', 40);
            $table->string('hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight_grams')->nullable()->after('stock_quantity');
        });

        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->foreignId('dropoff_center_id')->nullable()->constrained('shipping_centers')->nullOnDelete();
            $table->boolean('courier_pickup')->default(false); // المندوب يستلم من البائع بدل أن يسلّم للمركز
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 10)->default('home')->after('shipping_address');
            $table->foreignId('pickup_center_id')->nullable()->after('delivery_method')->constrained('shipping_centers')->nullOnDelete();
            $table->unsignedBigInteger('shipping_cost')->default(0)->after('shipping_fee'); // ما تأخذه الشركة قبل أي خصم
            $table->unsignedBigInteger('shipping_debt_charged')->default(0)->after('shipping_cost'); // دين رفض سابق أُضيف لهذا الطلب
            $table->timestamp('refused_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_debt')->default(0);
            $table->timestamp('cod_blocked_until')->nullable();
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_parcel_fee')->default(15000);
            $table->unsignedBigInteger('shipping_heavy_parcel_fee')->default(30000);
            $table->unsignedBigInteger('shipping_home_extra')->default(0);
        });

        (new ShippingCenterSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['shipping_parcel_fee', 'shipping_heavy_parcel_fee', 'shipping_home_extra']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['shipping_debt', 'cod_blocked_until']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_center_id');
            $table->dropColumn(['delivery_method', 'shipping_cost', 'shipping_debt_charged', 'refused_at']);
        });
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dropoff_center_id');
            $table->dropColumn('courier_pickup');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('weight_grams');
        });
        Schema::dropIfExists('shipping_centers');
    }
};
