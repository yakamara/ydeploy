<?php

namespace Deployer;

use RuntimeException;

use const GLOB_NOSORT;

$version = ltrim(Deployer::get()->getConsole()->getVersion(), 'v');
if (7 !== (int) $version) {
    throw new RuntimeException('YDeploy 2.x requires Deployer 7.x, but Deployer ' . $version . ' is used');
}

require 'recipe/common.php'; // @phpstan-ignore require.fileNotFound

require __DIR__ . '/deployer/config.php';
require __DIR__ . '/deployer/functions.php';

foreach (glob(__DIR__ . '/deployer/tasks/**/*.php', GLOB_NOSORT) as $path) {
    require $path;
}
foreach (glob(__DIR__ . '/deployer/tasks/*.php', GLOB_NOSORT) as $path) {
    require $path;
}
