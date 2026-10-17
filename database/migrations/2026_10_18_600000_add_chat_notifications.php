<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat-Benachrichtigungen (docs/oasis-chat.md, "Benachrichtigungen"), metadata only:
 * - conversation_members.last_read_at: when the member last had the chat open (unread = newer messages from others);
 * - conversation_members.notified_at: last e-mail/push about this chat, so a chat notifies once until it is read;
 * - privacy_settings.chat_email / chat_push: off by default, switched on in the Privatsphäre-Center.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_members', function (Blueprint $table): void {
            $table->timestamp('last_read_at')->nullable()->after('left_at');
            $table->timestamp('notified_at')->nullable()->after('last_read_at');
        });
        Schema::table('privacy_settings', function (Blueprint $table): void {
            $table->boolean('chat_email')->default(false)->after('login_alerts');
            $table->boolean('chat_push')->default(false)->after('chat_email');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_members', function (Blueprint $table): void {
            $table->dropColumn(['last_read_at', 'notified_at']);
        });
        Schema::table('privacy_settings', function (Blueprint $table): void {
            $table->dropColumn(['chat_email', 'chat_push']);
        });
    }
};
