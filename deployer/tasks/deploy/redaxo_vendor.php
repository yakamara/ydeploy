<?php

namespace Deployer;

desc('Run autoloader within redaxo directory');
task('deploy:redaxo_vendor', function () {
    // Install composer dependencies for redaxo core
    $oldPath = get('release_or_current_path');
    set('release_or_current_path', '{{release_path}}/{{src_dir}}/core');
    invoke('deploy:vendors');

    // Install composer dependencies for addons using composer.
    $addonsDir = parse('{{release_path}}/{{src_dir}}/addons/');
    $composerLocks = within($addonsDir, fn() => run('find . -type f -name "composer.lock" -print0'));
    foreach (explode("\0", $composerLocks) as $composerLock) {
        $addonName = ltrim(dirname($composerLock), './');
        writeln('<fg=blue;options=bold>Composer Vendors for addon </>' . $addonName);
        set('release_or_current_path', $addonsDir . $addonName);
        invoke('deploy:vendors');
    }

    // Set all changed paths back to original values
    set('release_or_current_path', $oldPath);
});
