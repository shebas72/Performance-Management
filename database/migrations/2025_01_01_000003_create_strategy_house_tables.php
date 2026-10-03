<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Strategy House core configuration
        Schema::create('strategy_houses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->year('year');
            $table->text('mission')->nullable();
            $table->text('mission_ar')->nullable();
            $table->text('vision')->nullable();
            $table->text('vision_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'year']);
        });

        // Core values linked to strategy house
        Schema::create('core_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_house_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->default('#534AB7');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Balanced Scorecard perspectives (Financial, Customer, Internal Process, People & Dev)
        Schema::create('bsc_perspectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('code')->comment('e.g. FIN, CUS, INT, PPL');
            $table->string('color')->default('#534AB7');
            $table->decimal('weight', 5, 2)->default(25.00)->comment('Must sum to 100 per company');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_values');
        Schema::dropIfExists('strategy_houses');
        Schema::dropIfExists('bsc_perspectives');
    }
};
