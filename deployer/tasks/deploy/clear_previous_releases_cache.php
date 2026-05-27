<?php

namespace Deployer;

desc('Clear cache directories (addons/*, core/*) of previous releases');
task('deploy:clear_previous_releases_cache', static function (): void {
    $releases = get('releases_list');
    $current = get('release_name');

    foreach ($releases as $release) {
        if ($release === $current) {
            continue;
        }

        $releaseArg = escapeshellarg($release);
        $cachePath = "{{deploy_path}}/releases/$releaseArg/{{cache_dir}}";

        // Only clear if the cache directory exists in the previous release
        if (!test("[ -d $cachePath ]")) {
            continue;
        }

        run("mkdir -p $cachePath/addons $cachePath/core");
        run("find $cachePath/addons $cachePath/core -mindepth 1 -exec rm -rf -- {} +");
    }
});

before('deploy:cleanup', 'deploy:clear_previous_releases_cache');
