<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'manual' = reported by a person (wins for its month), 'auto' = calculated from tasks / initiatives.
        Schema::table('progress_updates', function (Blueprint $table) {
            $table->enum('source', ['manual', 'auto'])->default('manual')->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('progress_updates', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
