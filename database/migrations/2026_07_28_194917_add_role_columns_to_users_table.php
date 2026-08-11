<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The source app scattered identity across four tables — `users`, `roles`, `user_roles` and a
 * hand-rolled `sessions` table. Fortify already owns authentication here, so the only thing
 * missing is authorization, and a single role column carries that until the domain actually
 * needs many-to-many. Phase 2 builds the Role enum, middleware and policies on top of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('ap')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->boolean('accepts_queue')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'accepts_queue']);
        });
    }
};
