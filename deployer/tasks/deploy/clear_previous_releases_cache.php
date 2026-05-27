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

        $cachePath = parse("{{deploy_path}}/releases/$release/{{cache_dir}}");
        $cachePathArg = escapeshellarg($cachePath);
        $addonsPathArg = escapeshellarg("$cachePath/addons");
        $corePathArg = escapeshellarg("$cachePath/core");

        // Only clear if the cache directory exists in the previous release
        if (!test("[ -d $cachePathArg ]")) {
            continue;
        }

        run("mkdir -p $addonsPathArg $corePathArg 2>/dev/null || true");
        run("find $addonsPathArg $corePathArg -mindepth 1 -delete 2>/dev/null || true");
    }
});

before('deploy:cleanup', 'deploy:clear_previous_releases_cache');
