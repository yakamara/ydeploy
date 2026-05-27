<?php

namespace Deployer;

// Whether to run the warmup task automatically after deploy:publish.
// When false (default), the task can still be invoked manually via
// `dep deploy:warmup [hostname]`.
set('warmup_after_deploy', false);

// When true, the warmup is skipped on initial deployments (no previous_release).
// Useful because on the very first deployment the domain may not yet point at
// the new symlink.
set('warmup_skip_initial', true);

// Additional options that are passed to `bin/console ydeploy:warmup`.
// Examples: '--no-sitemap', '--skip-search-it', '--concurrency=10'.
set('warmup_console_options', '');

desc('Warm up the cache (crawl sitemap.xml and rebuild search indexes)');
task('deploy:warmup', static function () {
    if (get('warmup_skip_initial') && !has('previous_release')) {
        writeln('<comment>Initial deployment detected, skipping cache warmup.</comment>');
        return;
    }

    if (!get('warmup_after_deploy')) {
        if (input()->isInteractive()) {
            if (!askConfirmation('Run cache warmup now?', true)) {
                writeln('<comment>Skipping cache warmup.</comment>');
                return;
            }
        } else {
            // non-interactive and not enabled: skip silently
            return;
        }
    }

    cd('{{release_path}}');

    $options = (string) get('warmup_console_options');
    $options = '' === trim($options) ? '' : ' ' . trim($options);

    run('{{bin/php}} {{bin/console}} ydeploy:warmup -v' . $options);
});

after('deploy:publish', 'deploy:warmup');
