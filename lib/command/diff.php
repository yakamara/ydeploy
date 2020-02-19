<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class rex_ydeploy_command_diff extends rex_ydeploy_command_abstract
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
                    ->setValue('timestamp', $diffTimestamp->format('Y-m-d H:i:s.u'))
                    ->insert();
            }
        }

        if (!$schemaExists) {
            $io->success('Created initial schema and fixtures files.');
        } elseif ($diffTimestamp) {
            $io->success(sprintf('Updated schema and fixtures file and created diff file "%s.php".', $diffTimestamp->format('Y-m-d H-i-s.u')));
        } else {
            $io->success('Updated schema and fixtures files, nothing changed.');
        }

        return 0;
    }

    /**
     * @param rex_sql_table[] $tables
     */
    private function handleSchema(array $tables, rex_ydeploy_diff_file $diff): void
    {
        $charsets = rex_sql::factory()->getArray('
            SELECT T.TABLE_NAME, CCSA.CHARACTER_SET_NAME
            FROM INFORMATION_SCHEMA.TABLES T
            INNER JOIN INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY AS CCSA ON CCSA.COLLATION_NAME = T.TABLE_COLLATION
            WHERE T.TABLE_SCHEMA = DATABASE() AND T.TABLE_NAME LIKE :prefix
        ', ['prefix' => rex::getTablePrefix().'%']);

        $charsets = array_column($charsets, 'CHARACTER_SET_NAME', 'TABLE_NAME');

        $this->addSchemaDiff($diff, $tables, $charsets);
        $this->createSchema($tables, $charsets);
    }

    /**
     * @param rex_sql_table[] $tables
     */
    private function createSchema(array $tables, array $charsets): void
    {
        $schema = [];

        foreach ($tables as $table) {
            $tableName = $table->getName();

            $schema[$tableName]['charset'] = $charsets[$tableName];

            foreach ($table->getColumns() as $column) {
                $schema[$tableName]['columns'][$column->getName()] = [
                    'type' => $column->getType(),
                    'nullable' => $column->isNullable(),
                    'default' => $column->getDefault(),
                    'extra' => $column->getExtra(),
                ];
            }

            $schema[$tableName]['primaryKey'] = $table->getPrimaryKey();

            foreach ($table->getIndexes() as $index) {
                $schema[$tableName]['indexes'][$index->getName()] = [
                    'type' => $index->getType(),
                    'columns' => $index->getColumns(),
                ];
            }

            foreach ($table->getForeignKeys() as $foreignKey) {
                $schema[$tableName]['foreignKeys'][$foreignKey->getName()] = [
                    'table' => $foreignKey->getTable(),
                    'columns' => $foreignKey->getColumns(),
                    'onUpdate' => $foreignKey->getOnUpdate(),
                    'onDelete' => $foreignKey->getOnDelete(),
                ];
            }
        }

        rex_file::putConfig($this->addon->getDataPath('schema.yml'), $schema);
    }

    /**
     * @param rex_sql_table[] $tables
     */
    private function addSchemaDiff(rex_ydeploy_diff_file $diff, array $tables, array $charsets): void
    {
        $schema = rex_file::getConfig($this->addon->getDataPath('schema.yml'));

        if (!$schema) {
            return;
        }

        foreach ($tables as $table) {
            $tableName = $table->getName();

            if (!isset($schema[$tableName])) {
                $diff->createTable($table);

                $defaultCharset = rex::getConfig('utf8mb4') ? 'utf8mb4' : 'utf8';
                if ($defaultCharset !== $charsets[$tableName]) {
                    $diff->setCharset($tableName, $charsets[$tableName]);
                }

                continue;
            }

            $tableSchema = &$schema[$tableName];

            if (!isset($tableSchema['charset']) || $tableSchema['charset'] !== $charsets[$tableName]) {
                $diff->setCharset($tableName, $charsets[$tableName]);
            }

            $columns = $table->getColumns();

            $renamed = [];
            $currentOrder = [];
            $after = rex_sql_table::FIRST;
            foreach ($tableSchema['columns'] as $columnName => $columnSchema) {
                if (isset($columns[$columnName])) {
                    $currentOrder[$after] = $columnName;
                    $after = $columnName;

                    continue;
                }

                foreach ($columns as $newName => $newColumn) {
                    if (isset($tableSchema['columns'][$newName]) || isset($renamed[$newName])) {
                        continue;
                    }

                    if (!$this->columnEqualsSchema($newColumn, $columnSchema)) {
                        continue;
                    }

                    $diff->renameColumn($tableName, $columnName, $newName);
                    $renamed[$newName] = true;
                    $currentOrder[$after] = $newName;
                    $after = $newName;

                    continue 2;
                }

                $diff->removeColumn($tableName, $columnName);
            }

            $after = rex_sql_table::FIRST;
            foreach ($columns as $columnName => $column) {
                if (
                    !isset($tableSchema['columns'][$columnName]) && !isset($renamed[$columnName]) ||
                    !isset($currentOrder[$after]) || $columnName !== $currentOrder[$after]
                ) {
                    $diff->ensureColumn($tableName, $column, $after);

                    if (false !== $previous = array_search($columnName, $currentOrder)) {
                        if (isset($currentOrder[$columnName])) {
                            $currentOrder[$previous] = $currentOrder[$columnName];
                        } else {
                            unset($currentOrder[$previous]);
                        }
                    }
                    if (isset($currentOrder[$after])) {
                        $currentOrder[$columnName] = $currentOrder[$after];
                    }
                    $currentOrder[$after] = $columnName;
                    $after = $columnName;

                    continue;
                }

                $after = $columnName;

                if (!isset($tableSchema['columns'][$columnName])) {
                    continue;
                }

                if (!$this->columnEqualsSchema($column, $tableSchema['columns'][$columnName])) {
                    $diff->ensureColumn($tableName, $column);
                }
            }

            if ($tableSchema['primaryKey'] !== $table->getPrimaryKey()) {
                $diff->setPrimaryKey($tableName, $table->getPrimaryKey());
            }

            foreach ($table->getIndexes() as $indexName => $index) {
                if (!isset($tableSchema['indexes'][$indexName]) || !$this->indexEqualsSchema($index, $tableSchema['indexes'][$indexName])) {
                    $diff->ensureIndex($tableName, $index);
                }

                unset($tableSchema['indexes'][$indexName]);
            }

            if (isset($tableSchema['indexes'])) {
                foreach ($tableSchema['indexes'] as $indexName => $indexSchema) {
                    $diff->removeIndex($tableName, $indexName);
                }
            }

            foreach ($table->getForeignKeys() as $foreignKeyName => $foreignKey) {
                if (!isset($tableSchema['foreignKeys'][$foreignKeyName]) || !$this->foreignKeyEqualsSchema($foreignKey, $tableSchema['foreignKeys'][$foreignKeyName])) {
                    $diff->ensureForeignKey($tableName, $foreignKey);
                }

                unset($tableSchema['foreignKeys'][$foreignKeyName]);
            }

            if (isset($tableSchema['foreignKeys'])) {
                foreach ($tableSchema['foreignKeys'] as $foreignKeyName => $foreignKeySchema) {
                    $diff->removeForeignKey($tableName, $foreignKeyName);
                }
            }

            unset($schema[$tableName]);
        }

        foreach ($schema as $tableName => $table) {
            $diff->dropTable($tableName);
        }
    }

    private function columnEqualsSchema(rex_sql_column $column, array $schema): bool
    {
        return
            $column->getType() === $schema['type'] &&
            $column->isNullable() === $schema['nullable'] &&
            $column->getDefault() === $schema['default'] &&
            $column->getExtra() === $schema['extra'];
    }

    private function indexEqualsSchema(rex_sql_index $index, array $schema): bool
    {
        return
            $index->getType() === $schema['type'] &&
            $index->getColumns() === $schema['columns'];
    }

    private function foreignKeyEqualsSchema(rex_sql_foreign_key $foreignKey, array $schema): bool
    {
        return
            $foreignKey->getTable() === $schema['table'] &&
            $foreignKey->getColumns() === $schema['columns'] &&
            $foreignKey->getOnUpdate() === $schema['onUpdate'] &&
            $foreignKey->getOnDelete() === $schema['onDelete'];
    }

    /**
     * @param rex_sql_table[] $tables
     */
    private function handleFixtures(array $tables, rex_ydeploy_diff_file $diff): void
    {
        $fixtureTables = [];
        foreach ($this->addon->getProperty('config')['fixtures']['tables'] as $name => $config) {
            $fixtureTables[rex::getTable($name)] = $config ?: true;
        }

        $path = $this->addon->getDataPath('fixtures.yml');
        $fixturesExists = file_exists($path);
        $fixtures = $fixturesExists ? rex_file::getConfig($path) : [];

        $newFixtures = [];

        foreach ($tables as $table) {
            $tableName = $table->getName();

            if (!isset($fixtureTables[$tableName])) {
                continue;
            }

            if (!$table->getPrimaryKey()) {
                throw new Exception(sprintf('Table "%s" can not be used for fixtures because it does not have a primary key.', $tableName));
            }

            $data = $this->getData($table, is_array($fixtureTables[$tableName]) ? $fixtureTables[$tableName] : null);

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
                if (is_array($fixtureTables[$tableName]) && !$this->rowMatchesConditions($row, $fixtureTables[$tableName])) {
                    continue;
                }

                $diff->removeFixture($tableName, $this->getKey($table, $row));
            }
        }

        rex_file::putConfig($this->addon->getDataPath('fixtures.yml'), $newFixtures);
    }

    private function getKey(rex_sql_table $table, array $data): array
    {
        return array_intersect_key($data, array_flip($table->getPrimaryKey()));
    }

    private function hash(array $data): string
    {
        ksort($data);

        return sha1(json_encode($data));
    }

    private function getData(rex_sql_table $table, array $conditions = null): array
    {
        $sql = rex_sql::factory();

        $where = '';
        $params = [];

        if (null !== $conditions) {
            $where = [];

            foreach ($conditions as $condition) {
                $parts = [];
                foreach ($condition as $name => $value) {
                    $parts[] = $sql->escapeIdentifier($name).' = ?';
                    $params[] = $value;
                }
                $where[] = implode(' AND ', $parts);
            }

            $where = ' WHERE '.implode(' OR ', $where);
        }

        $data = $sql->getArray('SELECT * FROM '.$sql->escapeIdentifier($table->getName()).$where, $params);

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

    private function rowMatchesConditions(array $row, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $match = true;

            foreach ($condition as $name => $value) {
                if (!isset($row[$name]) || $row[$name] !== $value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                return true;
            }
        }

        return false;
    }

    private function saveDiff(rex_ydeploy_diff_file $diff): DateTime
    {
        $timestamp = DateTime::createFromFormat('U.u', sprintf('%.f', microtime(true)));
        $timestamp->setTimezone(new DateTimeZone('UTC'));
        $filename = $timestamp->format('Y-m-d H-i-s.u').'.php';
        $path = $this->addon->getDataPath('migrations/'.$filename);

        if (file_exists($path)) {
            throw new Exception(sprintf('File "%s" already exists.', $path));
        }

        rex_file::put($path, $diff->getContent());

        return $timestamp;
    }
}
