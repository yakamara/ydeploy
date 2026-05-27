<?php

namespace Deployer;

desc('Backup the database on the remote host');
task('database:backup', static function () {
    if (!test('[ -L {{current_path}} ] || [ -d {{current_path}} ]')) {
        writeln('<comment>No current release found, skipping database backup.</comment>');
        return;
    }

    cd('{{current_path}}');

    $path = get('shared_path') . '/' . get('data_dir') . '/addons/backup/backup-data/ydeploy/backup-' . date('YmdHis') . '.sql';

    run('mkdir -p ' . escapeshellarg(dirname($path)));
    run('{{bin/php}} {{bin/console}} db:connection-options | xargs {{bin/mysqldump}} > ' . escapeshellarg($path));

    writeln('Database backup written to: <info>' . $path . '</info>');
});
