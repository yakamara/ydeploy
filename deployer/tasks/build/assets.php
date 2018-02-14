<?php

namespace Deployer;

set('yarn', false);
set('gulp', false);
set('gulp_options', '');

desc('Load (yarn) and build (gulp) assets');
task('build:assets', function () {
    cd('{{release_path}}');

    if (get('yarn')) {
        run('yarn');
    }

    if (get('gulp')) {
        run('gulp {{gulp_options}}');
    }
});
