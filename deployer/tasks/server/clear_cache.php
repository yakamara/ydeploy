<?php

namespace Deployer;

// restart apache server
set('restart_apache', false);

// kill processes with the given name (eg. "fcgi")
set('kill_process', false);

// clear the php opcache and realpath cache for the web environment
// (by calling a temp php file via curl)
set('clear_web_php_cache', false);

set('bin/apachectl', static function () {
    return locateBinaryPath('apachectl');
});

desc('Clear the server cache');
task('server:clear_cache', new class() {
    public function __invoke(): void
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

    private function restartApache(): void
    {
        run('{{bin/apachectl}} configtest && {{bin/apachectl}} graceful');
    }

    private function killProcess(): void
    {
        // ignore error when no process with given name is running
        run('pkill -u `whoami` {{kill_process}} || true');
    }

    private function clearWebPhpCache(): void
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

    private function clearCliPhpCache(): void
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
