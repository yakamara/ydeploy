<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
