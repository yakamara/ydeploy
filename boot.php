<?php

/** @var rex_addon $this */

use Alexplusde\Deploy\Handler;
use Alexplusde\Deploy\YDeploy;

// Backwards-compatibility autoloader: resolves the legacy `rex_ydeploy_*` /
// `rex_api_ydeploy_*` class names to the new namespaced `Alexplusde\Deploy`
// classes. Loading the namespaced class triggers the `class_alias()` call at
// the end of each class file, which then makes the old name available.
spl_autoload_register(static function (string $class): void {
    static $aliases = [
        'rex_ydeploy' => YDeploy::class,
        'rex_ydeploy_handler' => Handler::class,
        'rex_ydeploy_diff_file' => \Alexplusde\Deploy\DiffFile::class,
        'rex_api_ydeploy_protected_page' => \Alexplusde\Deploy\Api\ProtectedPage::class,
        'rex_ydeploy_command_abstract' => \Alexplusde\Deploy\Command\AbstractCommand::class,
        'rex_ydeploy_command_diff' => \Alexplusde\Deploy\Command\Diff::class,
        'rex_ydeploy_command_migrate' => \Alexplusde\Deploy\Command\Migrate::class,
        'rex_ydeploy_command_warmup' => \Alexplusde\Deploy\Command\Warmup::class,
    ];

    if (isset($aliases[$class]) && !class_exists($class, false)) {
        // class_exists() with autoload triggers loading of the target class,
        // which in turn registers the alias via class_alias() in the class
        // file itself.
        class_exists($aliases[$class]);
    }
});

// Move media_manager cache out of the per-release cache directory into the
// addon data directory, so the (expensive) generated images survive deployments
// and full cache clears. The directory should be configured as a shared
// directory in deployer/config.php so it persists across releases.
if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::setCacheDirectory(rex_path::addonData('media_manager', 'cache'));
}

if (!rex::isBackend() || rex::getUser() === null) {
    return;
}

$this->setProperty('pending_migrations', YDeploy::getPendingMigrations());

rex_view::addCssFile($this->getAssetsUrl('ydeploy.css'));

rex_extension::register('PAGE_BODY_ATTR', Handler::addBodyClasses(...));
rex_extension::register('OUTPUT_FILTER', Handler::addBadge(...));
rex_extension::register('PAGE_TITLE_SHOWN', Handler::addPendingMigrationsWarning(...));

if (YDeploy::factory()->isDeployed()) {
    $developer = rex_addon::get('developer');
    if ($developer->isAvailable() && (bool) $developer->getConfig('yform_email')) {
        $config = $this->getProperty('config');
        $config['protected_pages']['yform']['email'] = null;
        $this->setProperty('config', $config);
    }

    rex_extension::register('PAGE_CHECKED', Handler::protectPages(...));
}
