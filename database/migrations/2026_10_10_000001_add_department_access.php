<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'access_scope')) {
            Schema::table('users', function (Blueprint $t) {
                $t->string('access_scope', 10)->default('own'); // own | all | selected
            });
            // Everyone who exists today keeps seeing everything; only new users start on "own".
            DB::table('users')->update(['access_scope' => 'all']);
        }

        if (! Schema::hasColumn('users', 'manager_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        Schema::create('department_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10)->default('member'); // member = the user's own departments, access = the "chosen list"
            $t->unique(['user_id', 'department_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_user');
    }
};
