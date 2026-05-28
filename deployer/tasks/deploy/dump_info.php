<?php

namespace Deployer;

use function in_array;
use function YDeploy\onHost;

use const JSON_PRETTY_PRINT;

desc('Dump info about current deployment');
task('deploy:dump_info', static function () {
    $host = get('alias');
    $stage = get('labels')['stage'] ?? null;

    if (null === $stage && in_array($host, ['staging', 'test', 'testing', 'live', 'prod', 'production'], true)) {
        $stage = $host;
    }

    $branchEnv = getenv('CI_COMMIT_REF_NAME');
    $branch = $branchEnv !== false && $branchEnv !== '' ? $branchEnv : (get('branch') ?? onHost(host('local'), static fn () => run('{{bin/git}} rev-parse --abbrev-ref HEAD')));
    $commitEnv = getenv('CI_COMMIT_SHA');
    $commit = $commitEnv !== false && $commitEnv !== '' ? $commitEnv : onHost(host('local'), static fn () => run("{{bin/git}} rev-list $branch -1"));

    $infos = [
        'host' => $host,
        'stage' => $stage,
        'timestamp' => time(),
        'branch' => $branch,
        'commit' => $commit,
    ];

    $infos = json_encode($infos, JSON_PRETTY_PRINT);
    if ($infos === false) {
        $infos = '{}';
    }

    run('mkdir -p {{release_path}}/{{data_dir}}/addons/ydeploy');
    run('echo ' . escapeshellarg($infos) . ' > {{release_path}}/{{data_dir}}/addons/ydeploy/info.json');
});
