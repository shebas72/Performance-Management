<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Invitations carry the access setup the admin chose, applied when the invitation is accepted.
        Schema::table('invitations', function (Blueprint $t) {
            if (! Schema::hasColumn('invitations', 'manager_id')) {
                $t->unsignedBigInteger('manager_id')->nullable()->after('role');
            }
            if (! Schema::hasColumn('invitations', 'access_scope')) {
                $t->string('access_scope', 20)->default('own')->after('role');
            }
            if (! Schema::hasColumn('invitations', 'department_ids')) {
                $t->json('department_ids')->nullable();
            }
            if (! Schema::hasColumn('invitations', 'access_department_ids')) {
                $t->json('access_department_ids')->nullable();
            }
        });

        // 2) Execution plan tasks get an assignee (the model already lists assigned_to; add the column only if it is missing).
        if (! Schema::hasColumn('execution_plan_tasks', 'assigned_to')) {
            Schema::table('execution_plan_tasks', function (Blueprint $t) {
                $t->unsignedBigInteger('assigned_to')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $t) {
            foreach (['manager_id', 'access_scope', 'department_ids', 'access_department_ids'] as $c) {
                if (Schema::hasColumn('invitations', $c)) $t->dropColumn($c);
            }
        });
        // assigned_to is left in place: it may have existed before this migration.
    }
};
