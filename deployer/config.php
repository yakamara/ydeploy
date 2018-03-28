<?php

namespace Deployer;

use Deployer\Task\Context;

$baseDir = dirname(__DIR__, 5);
if (0 === strpos($baseDir, getcwd())) {
    $baseDir = substr($baseDir, strlen(getcwd()));
    $baseDir = ltrim($baseDir.'/', '/');
}

set('base_dir', $baseDir);
set('cache_dir', '{{base_dir}}redaxo/cache');
set('data_dir', '{{base_dir}}redaxo/data');
set('src_dir', '{{base_dir}}redaxo/src');

set('bin/console', '{{base_dir}}redaxo/bin/console');

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

set('url', function () {
    return 'https://'.Context::get()->getHost()->getRealHostname();
});

set('allow_anonymous_stats', false);

after('deploy:failed', 'deploy:unlock');
