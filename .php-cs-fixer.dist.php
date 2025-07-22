<?php

use PhpCsFixer\Finder;
use Redaxo\PhpCsFixerConfig\Config;

return Config::redaxo5()
    ->setFinder(Finder::create()
        ->in(__DIR__)
        ->append([
            __FILE__,
        ]),
    )
;
