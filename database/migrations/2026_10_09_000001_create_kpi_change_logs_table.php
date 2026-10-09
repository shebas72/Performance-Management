<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_change_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 30);
            $t->string('field', 40)->nullable();
            $t->unsignedSmallInteger('period_year')->nullable();
            $t->unsignedTinyInteger('period_month')->nullable();
            $t->text('old_value')->nullable();
            $t->text('new_value')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['kpi_id', 'id']);
        });

        // The code already writes kpis.created_by, so the column may exist. Add it only if missing.
        if (! Schema::hasColumn('kpis', 'created_by')) {
            Schema::table('kpis', function (Blueprint $t) {
                $t->foreignId('created_by')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_change_logs');
    }
};
