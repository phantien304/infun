<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('user', 'remember_token')) {
            return;
        }
        Schema::table('user', function (Blueprint $table) {
            $table->rememberToken()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('user', 'remember_token')) {
            return;
        }
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
