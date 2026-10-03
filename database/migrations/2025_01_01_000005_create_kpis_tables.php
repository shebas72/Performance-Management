<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // KPI definitions
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategic_objective_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bsc_perspective_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->string('code')->comment('e.g. KPI-1.1.1');
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();

            $table->enum('type', ['strategic', 'operational'])->default('strategic');
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->enum('direction', ['higher_is_better', 'lower_is_better'])->default('higher_is_better');
            $table->enum('value_type', ['percentage', 'number', 'currency', 'ratio'])->default('percentage');
            $table->string('unit')->nullable()->comment('e.g. %, $, users, days');

            $table->decimal('weight', 5, 2)->default(10.00)->comment('Weight within strategic objective');
            $table->decimal('annual_target', 15, 4)->nullable();
            $table->year('year');

            // Threshold configuration
            $table->decimal('threshold_red', 5, 2)->default(60.00)->comment('Below this = red');
            $table->decimal('threshold_yellow', 5, 2)->default(80.00)->comment('Below this = yellow');

            $table->boolean('has_recovery_target')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code', 'year']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'department_id']);
            $table->index(['company_id', 'strategic_objective_id']);
        });

        // Monthly targets per KPI (12 rows per KPI per year)
        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->year('year');
            $table->tinyInteger('month')->comment('1–12');
            $table->decimal('target_value', 15, 4);
            $table->decimal('recovery_target', 15, 4)->nullable()->comment('Revised target after underperformance');
            $table->timestamps();

            $table->unique(['kpi_id', 'year', 'month']);
            $table->index(['kpi_id', 'year']);
        });

        // Actual values entered per period
        Schema::create('kpi_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logged_by')->constrained('users');
            $table->year('year');
            $table->tinyInteger('month')->comment('1–12');
            $table->decimal('actual_value', 15, 4)->nullable();
            $table->decimal('achievement_pct', 8, 4)->nullable()->comment('Calculated: actual/target * 100');
            $table->enum('status', ['on_track', 'at_risk', 'behind', 'not_entered'])->default('not_entered');
            $table->text('note')->nullable();
            $table->text('note_ar')->nullable();
            $table->enum('data_status', ['complete', 'incomplete'])->default('complete');
            $table->enum('incomplete_reason', ['unavailable', 'not_recorded', 'cooperation_issue', 'other'])->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['kpi_id', 'year', 'month']);
            $table->index(['kpi_id', 'year']);
            $table->index(['company_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_entries');
        Schema::dropIfExists('kpi_targets');
        Schema::dropIfExists('kpis');
    }
};