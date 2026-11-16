<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // يسجّل من صوت "مفيد" على سؤال - مستخدم واحد = صوت واحد لكل سؤال.
        Schema::create('product_question_helpful_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_question_id', 'user_id'], 'pqhv_question_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_question_helpful_votes');
    }
};
