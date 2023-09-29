<?php

namespace Deployer;

set('assets_install', false);
set('assets_build', false);

desc('Load and build assets');
task('build:assets', static function () {
    $install = get('assets_install');

    if (!$install) {
        return;
    }

    cd('{{release_path}}');

    $isLocal = !getenv('CI');
    if ($isLocal && test('[ -d {{deploy_path}}/.node_modules ]')) {
        run('mv {{deploy_path}}/.node_modules node_modules');
    }

    run($install);

    if ($build = get('assets_build')) {
        run($build);
    }

    if ($isLocal) {
        run('mv node_modules {{deploy_path}}/.node_modules');
    }
});
