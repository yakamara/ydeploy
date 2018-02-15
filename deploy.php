<?php

namespace Deployer;

require 'recipe/common.php';

require __DIR__.'/deployer/config.php';
require __DIR__.'/deployer/functions.php';

foreach (glob(__DIR__.'/deployer/tasks/{**/*,*}.php', GLOB_BRACE | GLOB_NOSORT) as $path) {
    require $path;
}
