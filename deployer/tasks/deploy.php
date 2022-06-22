<?php

namespace Deployer;

desc('Deploy (build and release) to server');
task('deploy', [
    'build:start',
    'release',
]);
