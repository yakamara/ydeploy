<?php

namespace Deployer;

task('deploy', [
    'build',
    'release',
]);
