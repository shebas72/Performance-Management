<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'provider')) $table->string('provider', 20)->nullable();
            if (! Schema::hasColumn('subscriptions', 'paypal_subscription_id')) $table->string('paypal_subscription_id')->nullable()->index();
            if (! Schema::hasColumn('subscriptions', 'cancel_at_period_end')) $table->boolean('cancel_at_period_end')->default(false);
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'provider')) $table->string('provider', 20)->nullable();
            if (! Schema::hasColumn('invoices', 'external_id')) $table->string('external_id')->nullable()->index();
        });

        if (! Schema::hasTable('upgrade_requests')) {
            Schema::create('upgrade_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
                $table->string('billing_cycle', 10)->default('monthly');
                $table->text('note')->nullable();
                $table->string('status', 20)->default('pending'); // pending | approved | rejected
                $table->text('decision_note')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'status']);
            });
        }

        // PayPal needs a product and a billing plan per (plan, cycle); created on first use and remembered here.
        if (! Schema::hasTable('gateway_plans')) {
            Schema::create('gateway_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 20);
                $table->string('billing_cycle', 10);
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3);
                $table->string('product_id')->nullable();
                $table->string('external_id');
                $table->timestamps();
                $table->unique(['plan_id', 'provider', 'billing_cycle']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_plans');
        Schema::dropIfExists('upgrade_requests');
    }
};