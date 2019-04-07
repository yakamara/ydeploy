<?php

/**
 * @internal
 */
abstract class rex_ydeploy_command_abstract extends rex_console_command
{
    /** @var rex_addon */
    protected $addon;

    /** @var string */
    protected $migrationTable;

    public function __construct()
    {
        $this->addon = rex_addon::get('ydeploy');
        $this->migrationTable = rex::getTable('ydeploy_migration');

        parent::__construct();
    }
}
