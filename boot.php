<?php

/** @var rex_addon $this */

if (!rex::isBackend() || !rex::getUser()) {
    return;
}

$this->setProperty('pending_migrations', rex_ydeploy::getPendingMigrations());

rex_view::addCssFile($this->getAssetsUrl('ydeploy.css'));

rex_extension::register('PAGE_BODY_ATTR', rex_ydeploy_handler::addBodyClasses(...));
rex_extension::register('OUTPUT_FILTER', rex_ydeploy_handler::addBadge(...));
rex_extension::register('PAGE_TITLE_SHOWN', rex_ydeploy_handler::addPendingMigrationsWarning(...));

if (rex_ydeploy::factory()->isDeployed()) {
    $developer = rex_addon::get('developer');
    if ($developer->isAvailable() && $developer->getConfig('yform_email')) {
        $config = $this->getProperty('config');
        $config['protected_pages']['yform']['email'] = null;
        $this->setProperty('config', $config);
    }

    rex_extension::register('PAGE_CHECKED', rex_ydeploy_handler::protectPages(...));
}
