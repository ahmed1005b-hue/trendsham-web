<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دعوات الانضمام كبائع أو موظف: إنشاء الحساب العام للعملاء فقط،
 * والبائع والموظف ينضمان برابط خاص ترسله الإدارة (مرة واحدة، وله مدة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20); // seller | employee
            $table->char('token_hash', 64)->unique(); // للبحث عن الرابط
            $table->text('token'); // الرابط نفسه مشفّراً، لتنسخه الإدارة لاحقاً
            $table->string('name')->nullable(); // لمن الدعوة (للإدارة فقط)
            $table->string('phone', 20)->nullable(); // إن حُدد: لا يقبلها إلا صاحب هذا الرقم
            $table->string('email')->nullable(); // إن حُدد: لا يقبلها إلا صاحب هذا البريد
            $table->string('store_name')->nullable(); // اسم المتجر المقترح للبائع
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
