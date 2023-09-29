<?php

namespace Deployer;

desc('Dump info about current deployment');
task('deploy:dump_info', static function () {
    $infos = [
        'host' => get('alias'),
        'stage' => get('stage', false) ?: null,
        'timestamp' => time(),
        'branch' => runLocally('{{bin/git}} -C .build rev-parse --abbrev-ref HEAD'),
        'commit' => runLocally('{{bin/git}} -C .build rev-parse HEAD'),
    ];

    $infos = json_encode($infos, JSON_PRETTY_PRINT);

    run('mkdir -p {{release_path}}/{{data_dir}}/addons/ydeploy');
    run('echo ' . escapeshellarg($infos) . ' > {{release_path}}/{{data_dir}}/addons/ydeploy/info.json');
});
