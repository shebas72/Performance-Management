<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Projects
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('strategic_objective_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('code')->nullable();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->enum('type', ['strategic', 'digital_transformation', 'operational'])->default('strategic');

            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            $table->decimal('completion_pct', 5, 2)->default(0);
            $table->boolean('is_on_timeline')->default(true);
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed', 'cancelled'])->default('not_started');
            $table->year('year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'year']);
            $table->index(['company_id', 'strategic_objective_id']);
        });

        // Initiatives (linked to KPIs and objectives as corrective/improvement actions)
        Schema::create('initiatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('strategic_objective_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('code')->nullable();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();

            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->decimal('completion_pct', 5, 2)->default(0);
            $table->boolean('is_on_timeline')->default(true);
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed', 'cancelled'])->default('not_started');
            $table->year('year');
            $table->timestamps();

            $table->index(['company_id', 'year']);
        });

        // Pivot: KPI ↔ Initiative (proposed initiatives for a KPI)
        Schema::create('initiative_kpi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['initiative_id', 'kpi_id']);
        });

        // Execution plan tasks under initiatives
        Schema::create('execution_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('task_name');
            $table->string('task_name_ar')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->decimal('completion_pct', 5, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['initiative_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_plan_tasks');
        Schema::dropIfExists('initiative_kpi');
        Schema::dropIfExists('initiatives');
        Schema::dropIfExists('projects');
    }
};
