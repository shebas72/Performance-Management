<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_deletion_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('kpi_id');                       // no foreign key: the KPI no longer exists
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code');
            $t->string('name');
            $t->string('name_ar')->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();    // kept so the log respects department access scope
            $t->string('department_name')->nullable();
            $t->unsignedInteger('entries_count')->default(0);
            $t->unsignedInteger('targets_count')->default(0);
            $t->timestamp('created_at')->useCurrent();

            $t->index(['company_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_deletion_logs');
    }
};
