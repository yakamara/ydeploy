<?php

namespace Deployer;

use RuntimeException;

preg_match('/(?P<version>(\d+\.?)+)/', Deployer::get()->getConsole()->getVersion(), $matches);
$version = $matches['version'] ?? 'unknown version';
if (true !== version_compare($version, '7.0', '>=')) {
    throw new RuntimeException('YDeploy 2.x requires Deployer 7.x, but Deployer '.$version.' is used');
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
