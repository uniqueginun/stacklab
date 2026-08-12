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
        if (Schema::hasColumn('releases', 'commit_hash') && ! Schema::hasColumn('releases', 'commit_sha')) {
            Schema::table('releases', function (Blueprint $table) {
                $table->renameColumn('commit_hash', 'commit_sha');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('releases', 'commit_sha') && ! Schema::hasColumn('releases', 'commit_hash')) {
            Schema::table('releases', function (Blueprint $table) {
                $table->renameColumn('commit_sha', 'commit_hash');
            });
        }
    }
};
