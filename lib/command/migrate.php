<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class rex_ydeploy_command_migrate extends rex_ydeploy_command_abstract
{
    protected function configure(): void
    {
        $this
            ->setName('ydeploy:migrate')
            ->setDescription('Executes all pending migrations')
            ->addOption('fake', null, InputOption::VALUE_NONE, 'Marks all migrations as executed without actually executing them')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $io->title('YDeploy migration');

        $sql = rex_sql::factory();
        $migrated = $sql->getArray('SELECT `timestamp` FROM ' . $sql->escapeIdentifier($this->migrationTable));
        $migrated = array_column($migrated, 'timestamp', 'timestamp');

        $fake = $input->getOption('fake');

        $glob = glob($this->addon->getDataPath('migrations/*-*-* *.*.php'));
        $paths = [];

        foreach ($glob as $path) {
            $timestamp = substr(basename($path), 0, -4);

            if (!preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2})[-:](\d{2})[-:](\d{2}\.\d+)$/', $timestamp, $match)) {
                continue;
            }

            $timestamp = $match[1] . ' ' . $match[2] . ':' . $match[3] . ':' . $match[4];

            if (!isset($migrated[$timestamp])) {
                $paths[$path] = $timestamp;
            }
        }

        if (!$paths) {
            $io->success('Nothing to migrate.');

            return Command::SUCCESS;
        }

        $countMigrations = count($paths);
        $countMigrationsText = 1 === $countMigrations ? '1 migration' : $countMigrations . ' migrations';
        $countMigrated = 0;

        $io->text($countMigrationsText . ' to execute');

        $path = null;
        try {
            foreach ($paths as $path => $timestamp) {
                if (!$fake) {
                    $name = basename($path);
                    $time = time();
                    $io->text(sprintf('Migration "<comment>%s</comment>" started at <comment>%s</comment>', $name, date('H:i:s', $time)));

                    $this->migrate($path);

                    $io->text(sprintf('Migration "<comment>%s</comment>" finished in <comment>%s</comment>', $name, Helper::formatTime(time() - $time)));
                }

                rex_sql::factory()
                    ->setTable($this->migrationTable)
                    ->setValue('timestamp', $timestamp)
                    ->insert();

                ++$countMigrated;
            }
        } finally {
            rex_delete_cache();

            if ($countMigrated === $countMigrations) {
                $io->success(sprintf('%s %s.', $fake ? 'Faked' : 'Executed', $countMigrationsText));

                return Command::SUCCESS;
            }

            $io->error(sprintf('%s %d of %s, aborted with "%s".', $fake ? 'Faked' : 'Executed', $countMigrated, $countMigrationsText, basename($path)));
        }

        return Command::FAILURE;
    }

    private function migrate($path): void
    {
        require $path;
    }
}
