<?php

namespace Deployer;

use RuntimeException;

$version = ltrim(Deployer::get()->getConsole()->getVersion(), 'v');
if (6 !== (int) $version) {
    throw new RuntimeException('YDeploy 1.x requires Deployer 6.x, but Deployer '.$version.' is used');
}

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
