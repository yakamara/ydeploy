<?php

/**
 * @internal
 */
abstract class rex_ydeploy_command_abstract extends rex_console_command
{
    protected readonly rex_addon $addon;
    protected readonly string $migrationTable;

    public function __construct()
    {
        $this->addon = rex_addon::require('ydeploy');
        $this->migrationTable = rex::getTable('ydeploy_migration');

        parent::__construct();
    }
}
