<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Monthly progress history for projects and initiatives (drives the YTD trend lines).
        Schema::create('progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('subject_type', ['project', 'initiative']);
            $table->unsignedBigInteger('subject_id');
            $table->year('year');
            $table->tinyInteger('month');
            $table->decimal('completion_pct', 5, 2);
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'year', 'month']);
            $table->index(['company_id', 'subject_type', 'year']);
        });

        // An initiative can span several projects.
        Schema::create('initiative_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['initiative_id', 'project_id']);
        });

        // Carry over the single project each initiative already had. initiatives.project_id is left in place but no longer used.
        DB::table('initiative_project')->insertUsing(
            ['initiative_id', 'project_id', 'created_at', 'updated_at'],
            DB::table('initiatives')->whereNotNull('project_id')->selectRaw('id, project_id, ?, ?', [now(), now()])
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('initiative_project');
        Schema::dropIfExists('progress_updates');
    }
};
