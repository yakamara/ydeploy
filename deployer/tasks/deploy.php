<?php

namespace Deployer;

desc('Deploy (build and release) to server');
task('deploy', [
    'build:start',
    'release',
]);

task('build:start', static function () {
    on(host('local'), static fn () => invoke('build'));
})->once()->hidden();
