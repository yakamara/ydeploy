<?php

namespace Deployer;

desc('Run autoloader within redaxo directory');
task('deploy:redaxo_vendor', function () {
    $oldPath = get('release_or_current_path');
    set('release_or_current_path', '{{release_path}}/{{src_dir}}/core');
    invoke('deploy:vendors');
    set('release_or_current_path', $oldPath);
});
