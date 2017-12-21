<?php

namespace Deployer;

require 'recipe/common.php';

/*
 * REDAXO Configuration
 */

$baseDir = dirname(dirname(dirname(dirname(__DIR__))));
if (0 === strpos($baseDir, getcwd())) {
    $baseDir = substr($baseDir, strlen(getcwd()));
    $baseDir = ltrim($baseDir.'/', '/');
}

set('base_dir', $baseDir);
set('cache_dir', '{{base_dir}}redaxo/cache');
set('data_dir', '{{base_dir}}redaxo/data');
set('src_dir', '{{base_dir}}redaxo/src');

set('bin/console', '{{base_dir}}redaxo/bin/console');

set('yarn', false);
set('gulp', false);
set('gulp_options', '');

set('shared_dirs', [
    '{{base_dir}}media',
    '{{data_dir}}/addons/cronjob',
    '{{data_dir}}/addons/phpmailer',
    '{{data_dir}}/addons/yform',
    '{{data_dir}}/core',
]);

set('writable_dirs', [
    '{{base_dir}}assets',
    '{{base_dir}}media',
    '{{cache_dir}}',
    '{{data_dir}}',
]);

set('copy_dirs', [
    '{{base_dir}}assets',
    '{{src_dir}}',
]);

set('clear_paths', [
    'gulpfile.js',
    'node_modules',
    'deploy.php',
    'package.json',
    'yarn.lock',
]);

set('allow_anonymous_stats', false);

/*
 * Tasks
 */

task('build', function () {
    set('deploy_path', getcwd().'/.build');
    set('keep_releases', 1);

    invoke('build:info');
    invoke('deploy:prepare');
    invoke('deploy:release');
    invoke('deploy:update_code');
    invoke('build:assets');
    invoke('deploy:clear_paths');
    invoke('deploy:symlink');
    invoke('cleanup');
})->shallow()->local();

task('release', [
    'deploy:info',
    'deploy:prepare',
    'deploy:release',
    'deploy:copy_dirs',
    'upload',
    'deploy:shared',
    'deploy:writable',
    'database:migration',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success',
]);

task('deploy', [
    'build',
    'release',
]);

task('build:info', function () {
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
})->shallow()->setPrivate();

task('build:assets', function () {
    cd('{{release_path}}');

    if (get('yarn')) {
        run('yarn');
    }

    if (get('gulp')) {
        run('gulp {{gulp_options}}');
    }
});

task('upload', function () {
    upload(getcwd().'/.build/current/', '{{release_path}}', [
        'options' => ['--exclude=".git/"', '--delete'],
    ]);
});

task('database:migration', function () {
    cd('{{release_path}}');

    run('{{bin/php}} {{bin/console}} ydeploy:migrate');

    run('if [[ $({{bin/php}} {{bin/console}} list --raw | grep developer:sync) ]]; then {{bin/php}} {{bin/console}} developer:sync; fi');
});

after('deploy:failed', 'deploy:unlock');
