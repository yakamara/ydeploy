<?php

namespace Deployer;

desc('Upload locally prepared release to server');
task('upload', static function () {
    upload(getcwd() . '/.build/current/', '{{release_path}}', [
        'options' => ['--exclude=".git/"', '--delete'],
    ]);
});
