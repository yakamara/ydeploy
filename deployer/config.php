<?php

namespace Deployer;

use Deployer\Task\Context;
use function dirname;
use function strlen;

$baseDir = dirname(__DIR__, 5);
if (0 === strpos($baseDir, getcwd())) {
    $baseDir = substr($baseDir, strlen(getcwd()));
    $baseDir = ltrim($baseDir.'/', '/');
}

set('base_dir', $baseDir);
set('media_dir', '{{base_dir}}media');
set('cache_dir', '{{base_dir}}redaxo/cache');
set('data_dir', '{{base_dir}}redaxo/data');
set('src_dir', '{{base_dir}}redaxo/src');

localhost('local')
    ->set('deploy_path', '{{base_dir}}.build')
    ->set('release_path', '{{deploy_path}}/release')
    ->set('current_path', '{{deploy_path}}/current')
    ->set('labels', ['stage' => 'build']);


set('bin/console', '{{base_dir}}redaxo/bin/console');

set('shared_dirs', array_merge(
    get('shared_dirs', []),
    [
        '{{media_dir}}',
        '{{data_dir}}/addons/cronjob',
        '{{data_dir}}/addons/phpmailer',
        '{{data_dir}}/addons/yform',
        '{{data_dir}}/core',
    ]
));

set('writable_dirs', array_merge(
    get('writable_dirs', []),
    [
        '{{base_dir}}assets',
        '{{media_dir}}',
        '{{cache_dir}}',
        '{{data_dir}}',
    ]
));

set('copy_dirs', array_merge(
    get('copy_dirs', []),
    [
        '{{base_dir}}assets',
        '{{src_dir}}',
    ]
));

set('clear_paths', array_merge(
    get('clear_paths', []),
    [
        'gulpfile.js',
        'node_modules',
        '.gitlab-ci.yml',
        'deploy.php',
        'package.json',
        'yarn.lock',
    ]
));

set('url', static function () {
    return 'https://'.Context::get()->getHost()->getHostname();
});

set('allow_anonymous_stats', false);

after('deploy:failed', 'deploy:unlock');

set('bin/mysql', static function () {
    return which('mysql');
});

set('bin/mysqldump', static function () {
    return which('mysqldump');
});
