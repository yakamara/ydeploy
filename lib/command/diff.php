<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class rex_ydeploy_command_diff extends rex_ydeploy_command_abstract
{
    protected function configure()
    {
        $this
            ->setName('ydeploy:diff')
            ->setDescription('Updates schema file and creates diff file if necessary')
            ->addOption('empty', null, InputOption::VALUE_NONE, 'Create (empty) diff file even if nothing has changed')
            ->addOption('unmarked', null, InputOption::VALUE_NONE, 'Do not mark the diff file as already executed')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = $this->getStyle($input, $output);

        $io->title('YDeploy diff');

        $tables = rex_sql::showTables(1, rex::getTablePrefix());

        /** @var rex_sql_table[] $tables */
        $tables = array_map('rex_sql_table::get', $tables);

        $schema = rex_file::getConfig($this->addon->getDataPath('schema.yml'));

        $diff = null;
        if ($schema) {
            $diff = $this->createDiff($schema, $tables, $input->getOption('empty'));
        }

        $this->createSchema($tables);

        if (!$schema) {
            $io->success('Created initial schema file.');
        } elseif ($diff) {
            $io->success(sprintf('Updated schema file and created diff file "%s".', $diff));
        } else {
            $io->success('Updated schema file, nothing changed.');
        }
    }

    /**
     * @param rex_sql_table[] $tables
     */
    private function createSchema(array $tables)
    {
        $schema = [];

        foreach ($tables as $table) {
            $columns = [];

            foreach ($table->getColumns() as $column) {
                $columns[$column->getName()] = [
                    'type' => $column->getType(),
                    'nullable' => $column->isNullable(),
                    'default' => $column->getDefault(),
                    'extra' => $column->getExtra(),
                ];
            }

            $schema[$table->getName()] = [
                'columns' => $columns,
                'primaryKey' => $table->getPrimaryKey(),
            ];
        }

        rex_file::putConfig($this->addon->getDataPath('schema.yml'), $schema);
    }

    /**
     * @param array           $schema
     * @param rex_sql_table[] $tables
     * @param bool            $allowEmpty
     *
     * @return null|string
     */
    private function createDiff(array $schema, array $tables, $allowEmpty)
    {
        $diff = new rex_ydeploy_diff_file();

        foreach ($tables as $table) {
            $tableName = $table->getName();

            if (!isset($schema[$tableName])) {
                $diff->createTable($table);

                continue;
            }

            $tableSchema = &$schema[$tableName];
            foreach ($table->getColumns() as $columnName => $column) {
                if (!isset($tableSchema['columns'][$columnName])) {
                    $diff->ensureColumn($tableName, $column);

                    continue;
                }

                $columnSchema = $tableSchema['columns'][$columnName];
                $oldColumn = new rex_sql_column(
                    $columnName,
                    $columnSchema['type'],
                    $columnSchema['nullable'],
                    $columnSchema['default'],
                    $columnSchema['extra']
                );

                if (!$oldColumn->equals($column)) {
                    $diff->ensureColumn($tableName, $column);
                }

                unset($tableSchema['columns'][$columnName]);
            }

            foreach ($tableSchema['columns'] as $columnName => $column) {
                $diff->removeColumn($tableName, $columnName);
            }

            if ($tableSchema['primaryKey'] !== $table->getPrimaryKey()) {
                $diff->setPrimaryKey($tableName, $table->getPrimaryKey());
            }

            unset($schema[$tableName]);
        }

        foreach ($schema as $tableName => $table) {
            $diff->dropTable($tableName);
        }

        if ($diff->isEmpty() && !$allowEmpty) {
            return null;
        }

        $timestamp = DateTime::createFromFormat('U.u', sprintf('%.f', microtime(true)));
        $timestamp->setTimezone(new DateTimeZone('UTC'));
        $timestamp = $timestamp->format('Y-m-d H:i:s.u');
        $filename = $timestamp.'.php';
        $path = $this->addon->getDataPath('migrations/'.$filename);

        if (file_exists($path)) {
            throw new Exception(sprintf('File "%s" already exists.', $path));
        }

        rex_file::put($path, $diff->getContent());

        rex_sql::factory()
            ->setTable($this->migrationTable)
            ->setValue('timestamp', $timestamp)
            ->insert();

        return $filename;
    }
}
