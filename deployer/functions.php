<?php

namespace YDeploy;

use function Deployer\{get, upload};

function uploadContent(string $destination, string $content)
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
