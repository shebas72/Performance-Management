<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->boolean('arabic_enabled')->default(false)->after('default_language'); // new companies start English only
        });

        DB::table('companies')->update(['arabic_enabled' => true]); // companies that exist today keep Arabic
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('arabic_enabled'));
    }
};
