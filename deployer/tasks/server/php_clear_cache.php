<?php

namespace Deployer;

desc('Clear the php opcache and realpath cache');
task('server:php:clear_cache', function () {
    cd('{{release_path}}/{{base_dir}}');

    $dir = '_clear_cache';
    $htaccessFile = $dir.'/.htaccess';
    $phpFile = $dir.'/_clear_cache.php';

    $htaccess = <<<'HTACCESS'
Require all granted
HTACCESS;

    $php = <<<'PHP'
<?php

clearstatcache(true);

if (function_exists('opcache_reset')) {
    opcache_reset();
}
PHP;

    try {
        run('mkdir -p '.$dir);
        run('echo '.escapeshellarg($htaccess).' > '.$htaccessFile);
        run('echo '.escapeshellarg($php).' > '.$phpFile);
        run('curl -fsS {{url}}/'.$phpFile);
    } finally {
        run('rm -rf '.$dir);
    }
});
