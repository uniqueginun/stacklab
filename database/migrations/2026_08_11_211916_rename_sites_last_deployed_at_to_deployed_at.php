<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('sites', 'last_deployed_at') && ! Schema::hasColumn('sites', 'deployed_at')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->renameColumn('last_deployed_at', 'deployed_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sites', 'deployed_at') && ! Schema::hasColumn('sites', 'last_deployed_at')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->renameColumn('deployed_at', 'last_deployed_at');
            });
        }
    }
};
