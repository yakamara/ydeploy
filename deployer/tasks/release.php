<?php

namespace Deployer;

desc('Release locally prepared release to server');
task('release', [
    'deploy:info',
    'deploy:setup',
    'deploy:release',
    'deploy:copy_dirs',
    'upload',
    'deploy:shared',
    'deploy:dump_info',
    'deploy:writable',
    'setup',
    'database:migration',
    'deploy:symlink',
    'server:clear_cache',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:success',
]);
