<?php

namespace Deployer;

use DateTimeImmutable;
use DateTimeZone;

desc('Upgrade from deployer v1');
task('deploy:upgrade', static function () {
    cd('{{deploy_path}}');

    if (test('[ -f .dep/releases_log ]') || !test('[ -f .dep/releases ]')) {
        return;
    }

    $releases = [];
    foreach (explode("\n", trim(run('cat .dep/releases'))) as $release) {
        $release = explode(',', $release);

        $releases[$release[1]] = json_encode([
            'created_at' => DateTimeImmutable::createFromFormat('YmdHis', $release[0])->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sO'),
            'release_name' => $release[1],
            'user' => 'unknown',
            'target' => 'HEAD',
        ]);
    }

    if (!$releases) {
        return;
    }

    run('echo ' . escapeshellarg(implode("\n", $releases)) . ' > .dep/releases_log');
    run('echo ' . escapeshellarg(array_key_last($releases)) . ' > .dep/latest_release');
});
