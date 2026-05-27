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

        $cachePath = "{{deploy_path}}/releases/$release/{{cache_dir}}";

        // Only clear if the cache directory exists in the previous release
        if (!test("[ -d $cachePath ]")) {
            continue;
        }

        run("rm -rf $cachePath/addons/* $cachePath/core/* 2>/dev/null || true");
    }
});

before('deploy:cleanup', 'deploy:clear_previous_releases_cache');
