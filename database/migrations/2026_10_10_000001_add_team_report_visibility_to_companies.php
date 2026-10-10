<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** colleagues = employees see the whole ranking of their team; own = employees see only their own row. */
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'team_report_visibility')) {
            Schema::table('companies', function (Blueprint $t) {
                $t->string('team_report_visibility', 16)->default('colleagues');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'team_report_visibility')) {
            Schema::table('companies', function (Blueprint $t) {
                $t->dropColumn('team_report_visibility');
            });
        }
    }
};
