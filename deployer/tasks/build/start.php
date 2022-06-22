<?php

namespace Deployer;

desc('Force building the next release on local stage.');
task('build:start', static function () {
    on(host('local'), static fn () => invoke('build'));
})->once()->hidden();
