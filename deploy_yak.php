<?php

namespace Deployer;

require __DIR__.'/deploy.php';

set('base_dir', 'public/');
set('cache_dir', 'var/cache');
set('data_dir', 'var/data');
set('src_dir', 'src');
set('bin/console', 'bin/console');

add('shared_dirs', [
    'var/log',
]);

add('writable_dirs', [
    'var/log',
]);

add('clear_paths', [
    'assets',
]);
