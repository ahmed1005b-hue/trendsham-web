<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->string('store_contact_phone', 32)->nullable();
            $table->string('store_whatsapp', 2048)->nullable();
            $table->string('store_instagram', 2048)->nullable();
            $table->text('business_hours')->nullable();
            $table->text('shipping_policy')->nullable();
            $table->text('return_policy')->nullable();
        });

        Schema::create('storefront_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_hash', 64);
            $table->date('visited_on');
            $table->timestamps();

            $table->unique(['seller_profile_id', 'visitor_hash', 'visited_on'], 'storefront_visits_daily_unique');
            $table->index(['seller_profile_id', 'visited_on']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('storefront_visit_id')->nullable()->after('product_id')
                ->constrained('storefront_visits')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('storefront_visit_id')->nullable()->after('vendor_id')
                ->constrained('storefront_visits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_visit_id');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_visit_id');
        });
        Schema::dropIfExists('storefront_visits');
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'store_contact_phone',
                'store_whatsapp',
                'store_instagram',
                'business_hours',
                'shipping_policy',
                'return_policy',
            ]);
        });
    }
};
