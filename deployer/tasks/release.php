<?php

namespace Deployer;

desc('Release locally prepared release to server');
task('release', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:copy_dirs',
    'deploy:upload',
    'deploy:shared',
    'deploy:dump_info',
    'deploy:writable',
    'setup',
    'database:migration',
    'deploy:publish',
]);

before('deploy:cleanup', 'server:clear_cache');
