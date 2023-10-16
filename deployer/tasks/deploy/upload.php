<?php

namespace Deployer;

desc('Upload locally prepared release to server');
task('deploy:upload', static function () {
    $source = host('local')->get('release_path');

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
