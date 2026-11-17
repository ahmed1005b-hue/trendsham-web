<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف مؤقت لبيانات المتاجر والإدارة: يختفي العنصر من الموقع والتطبيق ويبقى في
 * القاعدة ليسترجعه المدير، وتبقى التقييمات والطلبات المرتبطة به سليمة.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'products',
        'categories',
        'coupons',
        'coupon_campaigns',
        'daily_deals',
        'showcase_slots',
        'shipping_companies',
        'product_questions',
        'user_addresses',
        'assistant_faqs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
