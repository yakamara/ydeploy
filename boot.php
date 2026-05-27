<?php

/** @var rex_addon $this */

// Move media_manager cache out of the per-release cache directory into the
// addon data directory, so the (expensive) generated images survive deployments
// and full cache clears. The directory should be configured as a shared
// directory in deployer/config.php so it persists across releases.
if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::setCacheDirectory(rex_path::addonData('media_manager', 'cache'));
}

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
