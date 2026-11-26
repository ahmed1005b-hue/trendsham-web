<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أقسام لوحة الموظف التي يعمل فيها كل موظف (الطلبات، المنتجات، دعم البائعين، محادثات العملاء، المرتجعات).
 * null = كل الأقسام، فيبقى الموظفون الحاليون كما هم حتى يحدد المدير غير ذلك.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('employee_permissions')->nullable()->after('employee_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employee_permissions');
        });
    }
};
