<?php

namespace YDeploy;

use DateTimeImmutable;
use DateTimeZone;
use Deployer\Host\Host;

use function Deployer\cd;
use function Deployer\download;
use function Deployer\get;
use function Deployer\on;
use function Deployer\run;
use function Deployer\test;
use function Deployer\upload;

function uploadContent(string $destination, string $content): void
{
    if (!empty($workingPath = get('working_path', ''))) {
        $destination = "$workingPath/$destination";
    } else {
        $destination = "{{release_or_current_path}}/$destination";
    }

    $path = tempnam(getcwd() . '/' . get('data_dir') . '/addons/ydeploy', 'tmp');
    file_put_contents($path, $content);

    try {
        upload($path, $destination, ['progress_bar' => false]);
    } finally {
        unlink($path);
    }
}

function downloadContent(string $source): string
{
    if (!empty($workingPath = get('working_path', ''))) {
        $source = "$workingPath/$source";
    } else {
        $source = "{{release_or_current_path}}/$source";
    }

    $path = tempnam(getcwd() . '/' . get('data_dir') . '/addons/ydeploy', 'tmp');

    download($source, $path, ['progress_bar' => false]);
    $content = file_get_contents($path);
    unlink($path);

    return $content;
}

function onHost(Host $host, callable $callback)
{
    $return = null;

    on($host, static function () use ($callback, &$return) {
        $return = $callback();
    });

    return $return;
}

function upgradeReleasesList(): void
{
    cd('{{deploy_path}}');

    if (test('[ -f .dep/releases_log ]') || !test('[ -f .dep/releases ]')) {
        return;
    }

    $releasesString = trim(run('cat .dep/releases'));
    if (!$releasesString) {
        return;
    }

    $releases = [];
    foreach (explode("\n", $releasesString) as $release) {
        $release = explode(',', $release);

        $releases[$release[1]] = json_encode([
            'created_at' => DateTimeImmutable::createFromFormat('YmdHis', $release[0])->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sO'),
            'release_name' => $release[1],
            'user' => 'unknown',
            'target' => 'HEAD',
        ]);
    }

    run('echo ' . escapeshellarg(implode("\n", $releases)) . ' > .dep/releases_log');
    run('echo ' . escapeshellarg(array_key_last($releases)) . ' > .dep/latest_release');
}
