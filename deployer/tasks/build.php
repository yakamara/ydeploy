<?php

namespace Deployer;

desc('Prepare the next release locally');
task('build', [
    'deploy:info',
    'build:setup',
    'build:vendors',
    'build:assets',
    'deploy:clear_paths',
])->once();

task('build:vendors', static function () {
    // replace this task in project deploy.php
});
