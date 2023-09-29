<?php

namespace Deployer;

desc('Upload locally prepared release to server');
task('upload', static function () {
    $source = host('local')->get(getenv('CI') ? 'root_dir' : 'release_path');

    upload($source . '/', '{{release_path}}', [
        'flags' => '-az',
        'options' => [
            '--exclude', '.cache',
            '--exclude', '.git',
            '--exclude', '.tools',
            '--exclude', 'deploy.php',
            '--exclude', 'node_modules',
            '--delete',
        ],
    ]);
});
