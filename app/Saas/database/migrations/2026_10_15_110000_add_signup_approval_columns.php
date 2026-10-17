<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Existing companies stay approved; only new self-registrations start as "pending".
            if (! Schema::hasColumn('companies', 'approval_status')) {
                $table->string('approval_status', 20)->default('approved')->index();
            }
            if (! Schema::hasColumn('companies', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('companies', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            // Set while a sign-up email is unverified (hash of the link token); cleared once verified.
            if (! Schema::hasColumn('users', 'email_verification_token')) {
                $table->string('email_verification_token', 64)->nullable();
            }
            if (! Schema::hasColumn('users', 'email_verification_sent_at')) {
                $table->timestamp('email_verification_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (['approval_status', 'approved_at', 'rejection_reason'] as $c) {
                if (Schema::hasColumn('companies', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('users', function (Blueprint $table) {
            foreach (['email_verification_token', 'email_verification_sent_at'] as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
    }
};
