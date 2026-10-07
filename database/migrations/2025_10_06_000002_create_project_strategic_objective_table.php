<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A project can serve several strategic objectives.
        Schema::create('project_strategic_objective', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategic_objective_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'strategic_objective_id'], 'project_objective_unique');
        });

        // Carry over each project's existing objective. projects.strategic_objective_id stays as the primary (first) objective.
        DB::table('project_strategic_objective')->insertUsing(
            ['project_id', 'strategic_objective_id', 'created_at', 'updated_at'],
            DB::table('projects')->whereNotNull('strategic_objective_id')->selectRaw('id, strategic_objective_id, ?, ?', [now(), now()])
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('project_strategic_objective');
    }
};
