<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class rex_ydeploy_command_migrate extends rex_ydeploy_command_abstract
{
    protected function configure()
    {
        $this
            ->setName('ydeploy:migrate')
            ->setDescription('Executes all pending migrations')
            ->addOption('fake', null, InputOption::VALUE_NONE, 'Marks all migrations as executed without actually executing them')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = $this->getStyle($input, $output);

        $io->title('YDeploy migration');

        $sql = rex_sql::factory();
        $migrated = $sql->getArray('SELECT `timestamp` FROM '.$this->migrationTable);
        $migrated = array_column($migrated, 'timestamp', 'timestamp');

        $fake = $input->getOption('fake');

        $paths = glob($this->addon->getDataPath('migrations/*-*-* *:*:*.*.php'));

        foreach ($paths as $i => $path) {
            $timestamp = substr(basename($path), 0, -4);

            if (isset($migrated[$timestamp])) {
                unset($paths[$i]);
            }
        }

        if (!$paths) {
            $io->success('Nothing to migrate.');

            return;
        }

        $io->text(count($paths).' migrations to execute');

        $countMigrated = 0;

        $path = null;
        try {
            foreach ($paths as $path) {
                $timestamp = substr(basename($path), 0, -4);

                if (!$fake) {
                    $this->migrate($path);
                }

                rex_sql::factory()
                    ->setTable($this->migrationTable)
                    ->setValue('timestamp', $timestamp)
                    ->insert();

                ++$countMigrated;
            }
        } finally {
            rex_file::delete(rex_path::coreCache('config.cache'));

            if ($countMigrated === count($paths)) {
                $io->success(sprintf('%s %d migrations.', $fake ? 'Faked' : 'Executed', $countMigrated));

                return;
            }

            $io->error(sprintf('%s %d of %d migrations, aborted with "%s".', $fake ? 'Faked' : 'Executed', $countMigrated, count($paths), basename($path)));
        }
    }

    private function migrate($path)
    {
        require $path;
    }
}
