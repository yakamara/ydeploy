<?php

namespace Deployer;

require 'recipe/common.php';

require __DIR__.'/deployer/config.php';

foreach (glob(__DIR__.'/deployer/tasks/**/*.php', GLOB_NOSORT) as $path) {
    require $path;
}
