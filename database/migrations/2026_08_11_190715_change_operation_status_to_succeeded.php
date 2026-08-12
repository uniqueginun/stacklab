<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rewriteStatusEnum('operations', includeCompleted: true);
        $this->rewriteStatusEnum('operation_steps', includeCompleted: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rewriteStatusEnum('operations', includeCompleted: false);
        $this->rewriteStatusEnum('operation_steps', includeCompleted: false);
    }

    private function rewriteStatusEnum(string $table, bool $includeCompleted): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($includeCompleted) {
            DB::table($table)->where('status', 'completed')->update(['status' => 'succeeded']);
        } else {
            DB::table($table)->where('status', 'succeeded')->update(['status' => 'completed']);
        }

        $values = $includeCompleted
            ? ['pending', 'running', 'succeeded', 'failed']
            : ['pending', 'running', 'completed', 'failed'];

        if ($driver === 'mysql') {
            $enum = collect($values)
                ->map(fn (string $value): string => "'{$value}'")
                ->implode(',');

            DB::statement("ALTER TABLE {$table} MODIFY status ENUM({$enum}) NOT NULL DEFAULT 'pending'");

            return;
        }

        // SQLite and others keep a free-form string column; value rewrite above is enough.
    }
};
