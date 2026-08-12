<?php

use App\Models\Server;
use App\Models\Site;
use App\Support\SiteNginxConfig;
use Illuminate\Support\Str;

test('install script refreshes placeholder index when no app index.php exists', function () {
    $server = Server::factory()->make([
        'ssh_user' => 'forge',
    ]);

    $site = Site::factory()->make([
        'uuid' => (string) Str::uuid(),
        'domain' => 'app.example.test',
        'root_path' => '/home/forge/app.example.test',
        'web_directory' => 'public',
        'php_version' => '8.4',
    ]);
    $site->setRelation('server', $server);

    $script = app(SiteNginxConfig::class)->installScript($site);

    expect($script)
        ->toContain('if [[ ! -f "${PUBLIC_ROOT}/index.php" ]]; then')
        ->toContain("grep -q 'mini-forge-site:' \"\${PUBLIC_ROOT}/index.html\"")
        ->toContain($site->headerMarker())
        ->not->toContain('[[ ! -f "${PUBLIC_ROOT}/index.php" && ! -f "${PUBLIC_ROOT}/index.html" ]]');
});
