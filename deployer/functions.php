<?php

namespace YDeploy;

use Deployer\Host\Host;
use Deployer\Host\Localhost;
use Deployer\Task\Context;
use function Deployer\cd;
use function Deployer\download;
use function Deployer\get;
use function Deployer\upload;

function uploadContent(string $destination, string $content): void
{
    if (!empty($workingPath = get('working_path', ''))) {
        $destination = "$workingPath/$destination";
    }

    $path = tempnam(getcwd().'/'.get('data_dir').'/addons/ydeploy', 'tmp');
    file_put_contents($path, $content);

    try {
        upload($path, $destination);
    } finally {
        unlink($path);
    }
}

function downloadContent(string $source): string
{
    if (!empty($workingPath = get('working_path', ''))) {
        $source = "$workingPath/$source";
    }

    $path = tempnam(getcwd().'/'.get('data_dir').'/addons/ydeploy', 'tmp');

    download($source, $path);
    $content = file_get_contents($path);
    unlink($path);

    return $content;
}

function onHost(Host $host, callable $callback)
{
    Context::push(new Context($host));

    try {
        if (!$host instanceof Localhost) {
            cd('{{release_path}}');
        }

        return $callback($host);
    } finally {
        Context::pop();
    }
}
