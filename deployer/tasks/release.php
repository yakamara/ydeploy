<?php

namespace Deployer;

desc('Release locally prepared release to server');
task('release', [
    'deploy:info',
    'deploy:prepare',
    'deploy:release',
    'deploy:copy_dirs',
    'upload',
    'deploy:shared',
    'deploy:dump_info',
    'deploy:writable',
    'database:migration',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success',
]);
