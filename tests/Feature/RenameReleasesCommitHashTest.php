<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('releases commit_hash is renamed to commit_sha', function () {
    $migration = require database_path('migrations/2026_08_12_211850_rename_releases_commit_hash_to_commit_sha.php');

    $migration->up();

    expect(Schema::hasColumn('releases', 'commit_sha'))->toBeTrue()
        ->and(Schema::hasColumn('releases', 'commit_hash'))->toBeFalse();

    Schema::table('releases', function (Blueprint $table) {
        $table->renameColumn('commit_sha', 'commit_hash');
    });

    $migration->up();

    expect(Schema::hasColumn('releases', 'commit_sha'))->toBeTrue()
        ->and(Schema::hasColumn('releases', 'commit_hash'))->toBeFalse();
});
