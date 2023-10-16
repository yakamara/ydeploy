<?php

namespace Deployer;

set('assets_install', static function () {
    if (!test('[ -f {{release_path}}/package.json ]')) {
        return false;
    }

    if (commandExist('yarn')) {
        return 'yarn install';
    }
    if (commandExist('npm')) {
        return 'npm install';
    }

    return false;
});

set('assets_build', static function () {
    if (!get('assets_install')) {
        return false;
    }

    if (!test('[ -f {{release_path}}/webpack.config.js ]') && test('[ -d {{release_path}}/gulpfile.js ]')) {
        return 'APP_ENV=prod gulp build';
    }

    if (commandExist('yarn')) {
        return 'yarn build';
    }
    if (commandExist('npm')) {
        return 'npm run build';
    }

    return false;
});

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
