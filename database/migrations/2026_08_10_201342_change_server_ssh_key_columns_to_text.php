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
        Schema::table('servers', function (Blueprint $table) {
            $table->text('ssh_private_key')->nullable()->change();
            $table->text('ssh_public_key')->nullable()->change();
            $table->text('host_key')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('ssh_private_key')->nullable()->change();
            $table->string('ssh_public_key')->nullable()->change();
            $table->string('host_key')->nullable()->change();
        });
    }
};
