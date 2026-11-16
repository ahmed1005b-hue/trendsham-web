<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // أجوبة يكتبها المدير (الشحن، الإرجاع، الدفع...) — المساعد لا يخترع سياسات
        Schema::create('assistant_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('keywords');            // كلمات مفصولة بفواصل
            $table->text('answer');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->timestamp('escalated_at')->nullable()->after('escalated_to');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', fn (Blueprint $table) => $table->dropColumn('escalated_at'));
        Schema::dropIfExists('assistant_faqs');
    }
};
