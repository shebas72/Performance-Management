<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();           // Arabic name
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('timezone')->default('Asia/Riyadh');
            $table->string('default_language')->default('en'); // en | ar
            $table->enum('plan', ['trial', 'starter', 'pro', 'enterprise'])->default('trial');
            $table->enum('app_mode', ['standalone', 'saas'])->default('saas');
            $table->string('fiscal_year_start')->default('01-01'); // MM-DD
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
