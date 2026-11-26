<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل نشاط فريق المنصة (الموظفون والمدير): من فعل ماذا ومتى، في الطلبات والمنتجات والدعم والمرتجعات.
 * يُكتب فقط ولا يُعدَّل. اسم الفاعل يُحفظ مع السطر فيبقى السجل مقروءاً حتى لو حُذف حسابه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role', 20); // employee | admin
            $table->string('action', 60); // مثل order.status
            $table->string('subject_type', 40)->nullable(); // order | product | support | chat | return
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable(); // تفاصيل العرض: رقم الطلب، الحالة، الاسم...
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activities');
    }
};
