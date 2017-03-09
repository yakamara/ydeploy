<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class rex_ydeploy_command_abstract extends Command
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

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return SymfonyStyle
     */
    protected function getStyle(InputInterface $input, OutputInterface $output)
    {
        return new SymfonyStyle($input, $output);
    }
}
