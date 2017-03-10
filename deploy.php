<?php

namespace Deployer;

require 'recipe/common.php';

/*
 * REDAXO Configuration
 */

$baseDir = dirname(dirname(dirname(dirname(__DIR__))));
if (0 === strpos($baseDir, getcwd())) {
    $baseDir = substr($baseDir, strlen(getcwd()));
    $baseDir = ltrim($baseDir.'/', '/');
}

set('shared_dirs', [
    $baseDir.'media',
    $baseDir.'redaxo/data/addons/cronjob',
    $baseDir.'redaxo/data/addons/phpmailer',
    $baseDir.'redaxo/data/core',
]);

set('writable_dirs', [
    $baseDir.'assets',
    $baseDir.'media',
    $baseDir.'redaxo/cache',
    $baseDir.'redaxo/data',
]);

set('ssh_type', 'native');
set('ssh_multiplexing', true);

/*
 * Tasks
 */

task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:clear_paths',
    'deploy:shared',
    'deploy:writable',
    'database:migration',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
])->desc('Deploy project');

task('database:migration', function () use ($baseDir) {
    run("cd {{release_path}}/$baseDir && redaxo/bin/console ydeploy:migrate");

    run("cd {{release_path}}/$baseDir && if [[ $(redaxo/bin/console list --raw | grep developer:sync) ]]; then redaxo/bin/console developer:sync; fi");

    run("cd {{release_path}}/$baseDir && rm -f redaxo/cache/core/config.cache && rm -rf redaxo/cache/addons");
})->desc('Migrate database');

after('deploy', 'success');
