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

desc('Pause on initial setup until the domain is pointed at the "current" symlink');
task('setup:wait_for_symlink', static function () {
    if (has('previous_release')) {
        return;
    }

    writeln('');
    writeln('<comment>Initial deployment detected.</comment>');
    writeln('The <info>current</info> symlink has just been created at <info>{{current_path}}</info>.');
    writeln('Before the server cache can be cleared, the domain must point to this directory.');
    writeln('');

    while (!askConfirmation('Is the webserver configured so that {{url}} points to {{current_path}}?', true)) {
        writeln('<comment>Please configure the webserver/domain before continuing.</comment>');
        writeln('');
    }

    writeln('');
});

before('server:clear_cache', 'setup:wait_for_symlink');
before('deploy:cleanup', 'server:clear_cache');
