<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS-only tables: plans, subscriptions, invoices. They used to be created inside
 * 2025_01_01_000007_create_corrective_saas_tables; each is guarded so a database that already has them is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('name_ar')->nullable();
                $table->string('stripe_price_id_monthly')->nullable();
                $table->string('stripe_price_id_yearly')->nullable();
                $table->integer('kpi_limit')->default(50)->comment('-1 = unlimited');
                $table->integer('user_limit')->default(10)->comment('-1 = unlimited');
                $table->integer('department_limit')->default(5)->comment('-1 = unlimited');
                $table->decimal('price_monthly', 10, 2)->default(0);
                $table->decimal('price_yearly', 10, 2)->default(0);
                $table->boolean('has_pdf_export')->default(false);
                $table->boolean('has_arabic')->default(false);
                $table->boolean('has_projects')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained();
                $table->string('stripe_subscription_id')->nullable()->unique();
                $table->string('stripe_customer_id')->nullable();
                $table->enum('status', ['trialing', 'active', 'past_due', 'cancelled', 'expired'])->default('trialing');
                $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('current_period_ends_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('stripe_invoice_id')->nullable()->unique();
                $table->decimal('amount', 10, 2);
                $table->string('currency')->default('USD');
                $table->enum('status', ['draft', 'open', 'paid', 'void', 'uncollectible'])->default('open');
                $table->string('pdf_url')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
