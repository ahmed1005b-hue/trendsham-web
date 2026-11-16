<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** سجل كل مستند PDF يصدره الموقع، للتحقق منه برمز QR. إضافي فقط. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('type', 30);
            $table->unsignedBigInteger('subject_id');
            $table->char('fingerprint', 64);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('summary');
            $table->json('details')->nullable();
            $table->char('file_hash', 64)->index();
            $table->string('file_path', 255);
            $table->timestamps();

            $table->index(['type', 'subject_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_documents');
    }
};
