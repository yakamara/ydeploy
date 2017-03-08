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
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
])->desc('Deploy your project');

after('deploy', 'success');
