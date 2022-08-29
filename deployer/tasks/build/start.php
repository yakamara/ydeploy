<?php

namespace Deployer;

desc('Force building the next release on local stage.');
task('build:start', static function () {
    $gitBranch = get('branch');
    if (!empty($gitBranch ?? null)) {
        host('local')
            ->set('branch', get('branch'));
    }
    on(host('local'), static fn() => invoke('build'));
})->once()->hidden();
