<?php

namespace Deployer;

desc('Displays info about build');
task('build:info', function () {
    info('building <fg=magenta;options=bold>{{target}}</>');
});
