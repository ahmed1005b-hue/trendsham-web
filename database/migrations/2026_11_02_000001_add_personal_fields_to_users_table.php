<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // رمز البلد الدولي مثل +90 أو +963
            $table->string('country_code', 8)->nullable()->after('phone');
            // الرقم الوطني بدون رمز البلد (الرقم الهاتف التفصيلي)
            $table->string('national_number', 32)->nullable()->after('country_code');
            $table->date('birth_date')->nullable()->after('national_number');
            $table->string('city')->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'national_number', 'birth_date', 'city']);
        });
    }
};
