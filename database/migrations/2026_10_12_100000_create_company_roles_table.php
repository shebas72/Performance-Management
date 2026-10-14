<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('key', 64);                    // admin | manager | employee | viewer | role-xxxxxxxx
            $t->string('name');
            $t->string('name_ar')->nullable();
            $t->json('permissions')->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamps();

            $t->unique(['company_id', 'key']);
        });

        // A user's role. Null means "the Spatie role they already have", so existing users keep working untouched.
        Schema::table('users', function (Blueprint $t) {
            $t->string('role_key', 64)->nullable()->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role_key'));
        Schema::dropIfExists('company_roles');
    }
};
