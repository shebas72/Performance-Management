<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Corrective proposals for low-performing KPIs
        Schema::create('corrective_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->text('description');
            $table->text('description_ar')->nullable();

            $table->enum('root_cause', [
                'resources_shortage',
                'technical_challenges',
                'administrative',
                'lack_of_followup',
                'other'
            ]);
            $table->text('root_cause_detail')->nullable();

            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'rejected'])->default('pending');
            $table->year('year');
            $table->tinyInteger('month')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'kpi_id']);
            $table->index(['company_id', 'status']);
        });

        // Periodic KPI performance snapshots (for dashboard caching + history)
        Schema::create('performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bsc_perspective_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('strategic_objective_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('scope', ['company', 'department', 'perspective', 'objective'])->default('company');
            $table->year('year');
            $table->tinyInteger('month');

            $table->decimal('achievement_pct', 7, 4)->nullable();
            $table->integer('kpi_count')->default(0);
            $table->integer('kpi_high')->default(0)->comment('80-100%');
            $table->integer('kpi_medium')->default(0)->comment('60-80%');
            $table->integer('kpi_low')->default(0)->comment('0-60%');
            $table->integer('kpi_not_entered')->default(0);

            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'scope', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_snapshots');
        Schema::dropIfExists('corrective_proposals');
    }
};
