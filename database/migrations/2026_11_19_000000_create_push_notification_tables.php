<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** الإشعارات المنبثقة على الهاتف — إضافية فقط، لا تمس البيانات الموجودة. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->enum('platform', ['android', 'ios']);
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 60);
            $table->string('body', 180);
            $table->string('link', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('pushed_count')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_prefs')->nullable();
            $table->timestamp('offers_prompted_at')->nullable();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('group', 30)->nullable()->after('type');
            $table->json('target')->nullable()->after('link');
            $table->enum('push_status', [
                'pending', 'sent', 'skipped_disabled', 'skipped_quiet', 'skipped_limit', 'no_device', 'not_configured', 'failed',
            ])->nullable()->after('target');
            $table->timestamp('pushed_at')->nullable()->after('push_status');
            $table->foreignId('broadcast_id')->nullable()->after('pushed_at')->constrained('push_broadcasts')->nullOnDelete();
            $table->index(['user_id', 'group', 'pushed_at'], 'notifications_user_group_pushed_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['broadcast_id']);
            $table->dropIndex('notifications_user_group_pushed_index');
            $table->dropColumn(['group', 'target', 'push_status', 'pushed_at', 'broadcast_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notification_prefs', 'offers_prompted_at']);
        });

        Schema::dropIfExists('push_broadcasts');
        Schema::dropIfExists('push_devices');
    }
};
