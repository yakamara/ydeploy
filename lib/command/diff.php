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

        $schemaExists = file_exists($this->addon->getDataPath('schema.yml'));

        $diff = new rex_ydeploy_diff_file();

        $this->handleSchema($tables, $diff);
        $this->handleFixtures($tables, $diff);

        $diffTimestamp = null;
        if (!$diff->isEmpty() || $input->getOption('empty')) {
            $diffTimestamp = $this->saveDiff($diff);

            if (!$input->getOption('unmarked')) {
                rex_sql::factory()
                    ->setTable($this->migrationTable)
                    ->setValue('timestamp', $diffTimestamp)
                    ->insert();
            }
        }

        if (!$schemaExists) {
            $io->success('Created initial schema and fixtures files.');
        } elseif ($diffTimestamp) {
            $io->success(sprintf('Updated schema and fixtures file and created diff file "%s.php".', $diffTimestamp));
        } else {
            $io->success('Updated schema and fixtures files, nothing changed.');
        }
    }

    /**
     * @param rex_sql_table[]       $tables
     * @param rex_ydeploy_diff_file $diff
     */
    private function handleSchema(array $tables, rex_ydeploy_diff_file $diff)
    {
        $this->addSchemaDiff($diff, $tables);
        $this->createSchema($tables);
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
     * @param rex_ydeploy_diff_file $diff
     * @param rex_sql_table[]       $tables
     */
    private function addSchemaDiff(rex_ydeploy_diff_file $diff, array $tables)
    {
        $schema = rex_file::getConfig($this->addon->getDataPath('schema.yml'));

        if (!$schema) {
            return;
        }

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
    }

    /**
     * @param rex_sql_table[]       $tables
     * @param rex_ydeploy_diff_file $diff
     */
    private function handleFixtures(array $tables, rex_ydeploy_diff_file $diff)
    {
        $fixtureTableNames = [
            'action',
            'markitup_profiles',
            'media_manager_type',
            'media_manager_type_effect',
            'metainfo_field',
            'metainfo_type',
            'module',
            'module_action',
            'redactor2_profiles',
            'template',
            'url_generate',
            'yform_field',
            'yform_table',
        ];

        $fixtureTables = [];
        foreach ($fixtureTableNames as $name) {
            $fixtureTables[rex::getTable($name)] = true;
        }

        $path = $this->addon->getDataPath('fixtures.yml');
        $fixturesExists = file_exists($path);
        $fixtures = $fixturesExists ? rex_file::getConfig($path) : [];

        $newFixtures = [];
        $sql = rex_sql::factory();

        foreach ($tables as $table) {
            $tableName = $table->getName();

            if (!isset($fixtureTables[$tableName])) {
                continue;
            }

            if (!$table->getPrimaryKey()) {
                throw new Exception(sprintf('Table "%s" can not be used for fixtures because it does not have a primary key.', $tableName));
            }

            $data = $sql->getArray('SELECT * FROM '.$sql->escapeIdentifier($tableName));
            $data = $this->normalize($data);

            $newFixtures[$tableName] = $data;

            if (!$fixturesExists) {
                continue;
            }

            $hashedFixtures = [];
            if (isset($fixtures[$tableName])) {
                foreach ($fixtures[$tableName] as $row) {
                    $hashedFixtures[$this->hash($this->getKey($table, $row))] = $row;
                }
                unset($fixtures[$tableName]);
            }

            foreach ($data as $row) {
                $key = $this->getKey($table, $row);
                $hash = $this->hash($key);

                if (isset($hashedFixtures[$hash]) && $this->hash($hashedFixtures[$hash]) === $this->hash($row)) {
                    unset($hashedFixtures[$hash]);

                    continue;
                }

                unset($hashedFixtures[$hash]);

                $diff->ensureFixture($tableName, $row);
            }

            foreach ($hashedFixtures as $row) {
                $diff->removeFixture($tableName, $this->getKey($table, $row));
            }
        }

        rex_file::putConfig($this->addon->getDataPath('fixtures.yml'), $newFixtures);
    }

    private function getKey(rex_sql_table $table, array $data)
    {
        return array_intersect_key($data, array_flip($table->getPrimaryKey()));
    }

    private function hash(array $data)
    {
        ksort($data);

        return sha1(json_encode($data));
    }

    private function normalize(array $data)
    {
        foreach ($data as &$row) {
            foreach ($row as &$value) {
                if (!is_string($value)) {
                    continue;
                }

                if ('0' === $value) {
                    $value = 0;
                } elseif (preg_match('/^[1-9]\d{0,8}$/', $value)) {
                    $value = (int) $value;
                }
            }
        }

        return $data;
    }

    /**
     * @param rex_ydeploy_diff_file $diff
     *
     * @return string
     */
    private function saveDiff(rex_ydeploy_diff_file $diff)
    {
        $timestamp = DateTime::createFromFormat('U.u', sprintf('%.f', microtime(true)));
        $timestamp->setTimezone(new DateTimeZone('UTC'));
        $timestamp = $timestamp->format('Y-m-d H:i:s.u');
        $filename = $timestamp.'.php';
        $path = $this->addon->getDataPath('migrations/'.$filename);

        if (file_exists($path)) {
            throw new Exception(sprintf('File "%s" already exists.', $path));
        }

        rex_file::put($path, $diff->getContent());

        return $timestamp;
    }
}
