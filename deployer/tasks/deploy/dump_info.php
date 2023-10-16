<?php

namespace Deployer;

use function YDeploy\onHost;

desc('Dump info about current deployment');
task('deploy:dump_info', static function () {
    $branch = getenv('CI_COMMIT_REF_NAME') ?: get('branch') ?? onHost(host('local'), fn () => run('{{bin/git}} rev-parse --abbrev-ref HEAD'));
    $commit = getenv('CI_COMMIT_SHA') ?: onHost(host('local'), fn () => run("{{bin/git}} rev-list $branch -1"));

    $infos = [
        'host' => get('alias'),
        'stage' => get('labels')['stage'] ?? null,
        'timestamp' => time(),
        'branch' => $branch,
        'commit' => $commit,
    ];

    $infos = json_encode($infos, JSON_PRETTY_PRINT);

    run('mkdir -p {{release_path}}/{{data_dir}}/addons/ydeploy');
    run('echo ' . escapeshellarg($infos) . ' > {{release_path}}/{{data_dir}}/addons/ydeploy/info.json');
});
