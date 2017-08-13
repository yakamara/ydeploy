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

set('base_dir', $baseDir);

set('shared_dirs', [
    '{{base_dir}}media',
    '{{base_dir}}redaxo/data/addons/cronjob',
    '{{base_dir}}redaxo/data/addons/phpmailer',
    '{{base_dir}}redaxo/data/core',
]);

set('writable_dirs', [
    '{{base_dir}}assets',
    '{{base_dir}}media',
    '{{base_dir}}redaxo/cache',
    '{{base_dir}}redaxo/data',
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

task('database:migration', function () {
    run('cd {{release_path}}/{{base_dir}} && redaxo/bin/console ydeploy:migrate');

    run('cd {{release_path}}/{{base_dir}} && if [[ $(redaxo/bin/console list --raw | grep developer:sync) ]]; then redaxo/bin/console developer:sync; fi');
})->desc('Migrate database');

after('deploy', 'success');
