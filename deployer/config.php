<?php

namespace Deployer;

use Deployer\Task\Context;
use Symfony\Component\Console\Input\ArgvInput;

use function dirname;
use function in_array;
use function strlen;
use function YDeploy\upgradeReleasesList;

$rootPath = dirname(DEPLOYER_DEPLOY_FILE); /** @phpstan-ignore-line */
$command = (new ArgvInput())->getFirstArgument();
$localBuildDir = 'setup' !== $command && !getenv('CI');

Deployer::get()->hosts = new class($rootPath, $localBuildDir) extends Host\HostCollection {
    public function __construct(
        private readonly string $rootPath,
        private readonly bool $buildDir,
    ) {}

    public function has(string $name): bool
    {
        if ('local' === $name) {
            return true;
        }

        return parent::has($name);
    }

    public function get(string $name): Host\Host
    {
        if ('local' === $name && !parent::has($name)) {
            localhost('local')
                ->set('root_path', $this->rootPath)
                ->set('deploy_path', $this->rootPath . ($this->buildDir ? '/.build' : ''))
                ->set('release_path', $this->rootPath . ($this->buildDir ? '/.build/release' : ''))
                ->set('current_path', '{{release_path}}')
            ;
        }

        return parent::get($name);
    }
};

if (in_array($command, ['build', 'setup', 'worker'], true)) {
    host('local');
}

set('branch', static function () {
    $branch = null;
    on(host('local'), static function () use (&$branch) {
        $branch = run('{{bin/git}} rev-parse --abbrev-ref HEAD');
    });

    return $branch;
});

$releaseName = Deployer::get()->config->fetch('release_name');
set('release_name', static function () use ($releaseName) {
    upgradeReleasesList();
    return $releaseName();
});

$releasesList = Deployer::get()->config->fetch('releases_list');
set('releases_list', static function () use ($releasesList) {
    upgradeReleasesList();
    return $releasesList();
});

$baseDir = $rootPath;
if (str_starts_with($baseDir, getcwd())) {
    $baseDir = substr($baseDir, strlen(getcwd()));
    $baseDir = ltrim($baseDir . '/', '/');
}

set('base_dir', $baseDir);
set('media_dir', '{{base_dir}}media');
set('cache_dir', '{{base_dir}}redaxo/cache');
set('data_dir', '{{base_dir}}redaxo/data');
set('src_dir', '{{base_dir}}redaxo/src');

set('bin/console', '{{base_dir}}redaxo/bin/console');

set('shared_dirs', [
    '{{media_dir}}',
    '{{data_dir}}/addons/cronjob',
    '{{data_dir}}/addons/phpmailer',
    '{{data_dir}}/addons/yform',
    '{{data_dir}}/core',
]);

set('writable_dirs', [
    '{{base_dir}}assets',
    '{{media_dir}}',
    '{{cache_dir}}',
    '{{data_dir}}',
]);

set('copy_dirs', [
    '{{base_dir}}assets',
    '{{src_dir}}',
]);

set('clear_paths', [
    '.github',
    '.idea',
    'gulpfile.js',
    '.gitignore',
    '.gitlab-ci.yml',
    '.php-cs-fixer.dist.php',
    'package.json',
    'README.md',
    'webpack.config.js',
    'yarn.lock',
    'REVISION',
]);

set('keep_releases', 5);

set('url', static function () {
    return 'https://' . Context::get()->getHost()->getHostname();
});

after('deploy:failed', 'deploy:unlock');

set('bin/mysql', static function () {
    return which('mysql');
});

set('bin/mysqldump', static function () {
    return which('mysqldump');
});
