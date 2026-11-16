<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            // زبون مسجل (اختياري - ممكن يكون null لزائر غير مسجل)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // معرف الجلسة للزوار غير المسجلين (session id أو UUID من التطبيق)
            $table->string('guest_session_id')->nullable()->index();
            $table->enum('status', ['open', 'closed', 'escalated'])->default('open');
            // إذا صار في تصعيد لموظف بشري
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
