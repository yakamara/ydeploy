<?php

namespace Deployer;

desc('Migrate the database');
task('database:migration', static function () {
    cd('{{release_path}}');

    run('{{bin/php}} {{bin/console}} ydeploy:migrate -v');

    run('if [[ $({{bin/php}} {{bin/console}} list --raw | grep developer:sync) ]]; then {{bin/php}} {{bin/console}} developer:sync --force-files -v; fi');
});
