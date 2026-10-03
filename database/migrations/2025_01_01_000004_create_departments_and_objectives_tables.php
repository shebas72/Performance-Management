<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Departments (can be hierarchical - parent_id for sub-departments)
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('code')->nullable();
            $table->string('color')->default('#534AB7');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'parent_id']);
        });

        // Strategic Objectives linked to BSC perspectives
        Schema::create('strategic_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bsc_perspective_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_house_id')->constrained()->cascadeOnDelete();
            $table->string('code')->comment('e.g. SO-FIN-01');
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('weight', 5, 2)->default(10.00)->comment('Weight within BSC perspective');
            $table->year('year');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code', 'year']);
            $table->index(['company_id', 'bsc_perspective_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_objectives');
        Schema::dropIfExists('departments');
    }
};
