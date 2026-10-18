<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // Per-user choices: {"proposals": {"app": true, "email": false}, ...}
        if (! Schema::hasColumn('users', 'notification_prefs')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('notification_prefs')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'notification_prefs')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_prefs'));
        }
    }
};