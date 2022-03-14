<?php

namespace Deployer;

/** @psalm-suppress MissingFile */
require 'recipe/common.php';

require __DIR__.'/deployer/config.php';
require __DIR__.'/deployer/functions.php';

foreach (glob(__DIR__.'/deployer/tasks/**/*.php', GLOB_NOSORT) as $path) {
    require $path;
}
foreach (glob(__DIR__.'/deployer/tasks/*.php', GLOB_NOSORT) as $path) {
    require $path;
}
