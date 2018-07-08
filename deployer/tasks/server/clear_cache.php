<?php

namespace Deployer;

use Deployer\Task\Context;

set('restart_apache', false);
set('kill_process', false);
set('clear_web_php_cache', false);

set('bin/apachectl', function () {
    return locateBinaryPath('apachectl');
});

desc('Clear the server cache');
task('server:clear_cache', new class {
    public function __invoke()
    {
        if (get('restart_apache')) {
            $this->restartApache();
        }

        if (get('kill_process')) {
            $this->killProcess();
        }

        if (get('clear_web_php_cache')) {
            $this->clearWebPhpCache();
        }

        $this->clearCliPhpCache();
    }

    private function restartApache()
    {
        run('{{bin/apachectl}} configtest && {{bin/apachectl}} graceful');
    }

    private function killProcess()
    {
        run('pkill -u `whoami` {{kill_process}} || true');
    }

    private function clearWebPhpCache()
    {
        cd('{{release_path}}/{{base_dir}}');

        $dir = '_clear_cache';
        $htaccessFile = $dir.'/.htaccess';
        $phpFile = $dir.'/_clear_cache.php';

        try {
            run('mkdir -p '.$dir);
            run('echo "Require all granted" > '.$htaccessFile);
            run('echo '.escapeshellarg("<?php\n\n".$this->getPhpClearCacheCode()).' > '.$phpFile);
            run("curl -fsS {{url}}/$phpFile");
        } finally {
            run('rm -rf '.$dir);
        }
    }

    private function clearCliPhpCache()
    {
        run('{{bin/php}} -r '.escapeshellarg($this->getPhpClearCacheCode()));
    }

    private function getPhpClearCacheCode(): string
    {
        return <<<'PHP'
clearstatcache(true);

if (function_exists('opcache_reset')) {
    opcache_reset();
}
PHP;
    }
});
