<?php

namespace Deployer;

desc('Prepare the next release in local subdir ".build"');
task('build', static function () {
    set('deploy_path', getcwd().'/.build');
    set('keep_releases', 1);

    invoke('build:info');
    invoke('deploy:setup');
    invoke('deploy:release');
    invoke('deploy:update_code');
    invoke('build:assets');
    invoke('deploy:clear_paths');
    invoke('deploy:symlink');
    invoke('deploy:cleanup');
})->once(true);
