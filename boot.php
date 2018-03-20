<?php

/** @var rex_addon $this */

if (!rex::isBackend() || !rex::getUser()) {
    return;
}

rex_view::addCssFile($this->getAssetsUrl('ydeploy.css'));

rex_extension::register('PAGE_BODY_ATTR', 'rex_ydeploy_handler::addBodyClasses');
rex_extension::register('OUTPUT_FILTER', 'rex_ydeploy_handler::addBadge');

if (rex_ydeploy::factory()->isDeployed()) {
    rex_extension::register('PAGES_PREPARED', 'rex_ydeploy_handler::protectPages');
}
