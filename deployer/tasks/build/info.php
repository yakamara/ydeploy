<?php

namespace Deployer;

desc('Show branch info');
task('build:info', static function () {
    $what = '';
    $branch = get('branch');

    if (!empty($branch)) {
        $what = "<fg=magenta>$branch</fg=magenta>";
    }

    if (input()->hasOption('tag') && !empty(input()->getOption('tag'))) {
        $tag = input()->getOption('tag');
        $what = "tag <fg=magenta>$tag</fg=magenta>";
    } elseif (input()->hasOption('revision') && !empty(input()->getOption('revision'))) {
        $revision = input()->getOption('revision');
        $what = "revision <fg=magenta>$revision</fg=magenta>";
    }

    if (empty($what)) {
        $what = '<fg=magenta>HEAD</fg=magenta>';
    }

    writeln("✂ Building $what on <fg=cyan>{{hostname}}</fg=cyan>");
})->hidden();
