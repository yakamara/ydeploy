<?php

namespace Deployer;

desc('Prepare the next release locally');
task('build', [
    'deploy:info',
    'build:setup',
    'build:assets',
    'deploy:clear_paths',
])->once();
