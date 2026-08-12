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
        if (Schema::hasColumn('operation_steps', 'aftermath')) {
            return;
        }

        Schema::table('operation_steps', function (Blueprint $table) {
            $table->string('aftermath')->nullable()->after('recipe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('operation_steps', 'aftermath')) {
            return;
        }

        Schema::table('operation_steps', function (Blueprint $table) {
            $table->dropColumn('aftermath');
        });
    }
};
