<?php

namespace Alexplusde\Deploy\Command;

use rex;
use rex_addon;
use rex_console_command;

/**
 * @internal
 */
abstract class AbstractCommand extends rex_console_command
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

\class_alias(AbstractCommand::class, 'rex_ydeploy_command_abstract');
