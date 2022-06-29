<?php

/**
 * @internal
 */
final class rex_ydeploy_diff_file
{
    /** @var array<string, rex_sql_table> */
    private $create = [];

    /**
     * @var array<string, array{
     *          charset?: array{string, string},
     *          ensureColumn?: list<array{rex_sql_column, ?string}>,
     *          renameColumn?: array<string, string>,
     *          removeColumn?: list<string>,
     *          primaryKey?: ?list<string>,
     *          ensureIndex?: list<rex_sql_index>,
     *          removeIndex?: list<string>,
     *          ensureForeignKey?: list<rex_sql_foreign_key>,
     *          removeForeignKey?: list<string>
     *      }>
     */
    private $alter = [];

    /** @var list<string> */
    private $drop = [];

    /** @var array<string, string> */
    private $views = [];

    /** @var list<string> */
    private $dropViews = [];

    /** @var array<string, array{ensure?: list<array<?scalar>>, remove?: list<array<string, int|string>>}> */
    private $fixtures = [];

    public function createTable(rex_sql_table $table): void
    {
        $this->create[$table->getName()] = $table;
    }

    public function dropTable(string $tableName): void
    {
        $this->drop[] = $tableName;
    }

    public function setCharset(string $tableName, string $charset, string $collation): void
    {
        $this->alter[$tableName]['charset'] = [$charset, $collation];
    }

    public function ensureColumn(string $tableName, rex_sql_column $column, ?string $afterColumn = null): void
    {
        $this->alter[$tableName]['ensureColumn'][] = [$column, $afterColumn];
    }

    public function renameColumn(string $tableName, string $oldName, string $newName): void
    {
        $this->alter[$tableName]['renameColumn'][$oldName] = $newName;
    }

    public function removeColumn(string $tableName, string $columnName): void
    {
        $this->alter[$tableName]['removeColumn'][] = $columnName;
    }

    /** @param ?list<string> $primaryKey */
    public function setPrimaryKey(string $tableName, ?array $primaryKey): void
    {
        $this->alter[$tableName]['primaryKey'] = $primaryKey;
    }

    public function ensureIndex(string $tableName, rex_sql_index $index): void
    {
        $this->alter[$tableName]['ensureIndex'][] = $index;
    }

    public function removeIndex(string $tableName, string $indexName): void
    {
        $this->alter[$tableName]['removeIndex'][] = $indexName;
    }

    public function ensureForeignKey(string $tableName, rex_sql_foreign_key $foreignKey): void
    {
        $this->alter[$tableName]['ensureForeignKey'][] = $foreignKey;
    }

    public function removeForeignKey(string $tableName, string $foreignKeyName): void
    {
        $this->alter[$tableName]['removeForeignKey'][] = $foreignKeyName;
    }

    public function ensureView(string $viewName, string $query): void
    {
        $this->views[$viewName] = $query;
    }

    public function dropView(string $viewName): void
    {
        $this->dropViews[] = $viewName;
    }

    /** @param array<?scalar> $data */
    public function ensureFixture(string $tableName, array $data): void
    {
        $this->fixtures[$tableName]['ensure'][] = $data;
    }

    /** @param array<string, int|string> $key */
    public function removeFixture(string $tableName, array $key): void
    {
        $this->fixtures[$tableName]['remove'][] = $key;
    }

    public function isEmpty(): bool
    {
        return !$this->create && !$this->alter && !$this->drop && !$this->views && !$this->dropViews && !$this->fixtures;
    }

    public function getContent(): string
    {
        $changes = $this->addDropViews();
        $changes .= $this->addCreateTables();
        $changes .= $this->addAlterTables();
        $changes .= $this->addDropTables();
        $changes .= $this->addEnsureViews();
        $changes .= $this->addFixtures();
        $changes = ltrim($changes);

        $content = <<<'EOL'
            <?php

            $sql = rex_sql::factory();
            $sql->setQuery('SET FOREIGN_KEY_CHECKS = 0');

            try {
            EOL;

        if ($changes) {
            $content .= "\n    ".$changes;
        } else {
            $content .= "\n    // Add migration stuff here";
        }

        $content .= <<<'EOL'

            } finally {
                $sql = rex_sql::factory();
                $sql->setQuery('SET FOREIGN_KEY_CHECKS = 1');
            }

            EOL;

        return $content;
    }

    private function addCreateTables(): string
    {
        $content = '';

        foreach ($this->create as $tableName => $table) {
            $content .= $this->sprintf("\n\n    rex_sql_table::get(%s)", $table->getName());

            foreach ($table->getColumns() as $column) {
                $content .= $this->addEnsureColumn($column);
            }

            if ($table->getPrimaryKey()) {
                $content .= $this->addSetPrimaryKey($table->getPrimaryKey());
            }

            foreach ($table->getIndexes() as $index) {
                $content .= $this->addEnsureIndex($index);
            }

            foreach ($table->getForeignKeys() as $foreignKey) {
                $content .= $this->addEnsureForeignKey($foreignKey);
            }

            $content .= "\n        ->ensure();";

            if (isset($this->alter[$tableName]['charset'])) {
                $content .= $this->addConvertCharset($tableName, $this->alter[$tableName]['charset']);
            }
        }

        return $content;
    }

    private function addAlterTables(): string
    {
        $content = '';

        foreach ($this->alter as $tableName => $alter) {
            $lines = '';

            if (isset($alter['renameColumn'])) {
                foreach ($alter['renameColumn'] as $oldName => $newName) {
                    $lines .= $this->addRenameColumn($oldName, $newName);
                }
            }

            if (isset($alter['ensureColumn'])) {
                foreach ($alter['ensureColumn'] as [$column, $after]) {
                    $lines .= $this->addEnsureColumn($column, $after);
                }
            }

            if (isset($alter['removeColumn'])) {
                foreach ($alter['removeColumn'] as $columnName) {
                    $lines .= $this->sprintf("\n        ->removeColumn(%s)", $columnName);
                }
            }

            if (isset($alter['primaryKey'])) {
                $lines .= $this->addSetPrimaryKey($alter['primaryKey']);
            }

            if (isset($alter['ensureIndex'])) {
                foreach ($alter['ensureIndex'] as $index) {
                    $lines .= $this->addEnsureIndex($index);
                }
            }

            if (isset($alter['removeIndex'])) {
                foreach ($alter['removeIndex'] as $indexName) {
                    $lines .= $this->sprintf("\n        ->removeIndex(%s)", $indexName);
                }
            }

            if (isset($alter['ensureForeignKey'])) {
                foreach ($alter['ensureForeignKey'] as $foreignKey) {
                    $lines .= $this->addEnsureForeignKey($foreignKey);
                }
            }

            if (isset($alter['removeForeignKey'])) {
                foreach ($alter['removeForeignKey'] as $foreignKeyName) {
                    $lines .= $this->sprintf("\n        ->removeForeignKey(%s)", $foreignKeyName);
                }
            }

            if ($lines) {
                $content .= $this->sprintf("\n\n    rex_sql_table::get(%s)", $tableName);
                $content .= $lines;
                $content .= "\n        ->alter();";
            }

            if (isset($alter['charset']) && !isset($this->create[$tableName])) {
                $content .= $this->addConvertCharset($tableName, $alter['charset']);
            }
        }

        return $content;
    }

    private function addConvertCharset(string $tableName, array $charsetAndCollation): string
    {
        $tableName = addslashes(rex_sql::factory()->escapeIdentifier($tableName));
        $charset = addslashes($charsetAndCollation[0]);
        $collation = addslashes($charsetAndCollation[1]);

        return "\n\n    \$sql->setQuery('ALTER TABLE $tableName CONVERT TO CHARACTER SET $charset COLLATE $collation');";
    }

    private function addRenameColumn(string $oldName, string $newName): string
    {
        return $this->sprintf("\n        ->renameColumn(%s, %s)", $oldName, $newName);
    }

    private function addEnsureColumn(rex_sql_column $column, ?string $afterColumn = null): string
    {
        $addAfter = '';
        if (rex_sql_table::FIRST == $afterColumn) {
            $addAfter = ', rex_sql_table::FIRST';
        } elseif (null !== $afterColumn) {
            $addAfter = $this->sprintf(', %s', $afterColumn);
        }

        return $this->sprintf(
            "\n        ->ensureColumn(new rex_sql_column(%s, %s, %s, %s, %s)$addAfter)",
            $column->getName(),
            $column->getType(),
            $column->isNullable(),
            $column->getDefault(),
            $column->getExtra()
        );
    }

    private function addSetPrimaryKey(array $primaryKey): string
    {
        return $this->sprintf("\n        ->setPrimaryKey(%s)", $primaryKey);
    }

    private function addEnsureIndex(rex_sql_index $index): string
    {
        /** @var array<rex_sql_index::*, string> $types */
        static $types = [
            rex_sql_index::UNIQUE => 'rex_sql_index::UNIQUE',
            rex_sql_index::FULLTEXT => 'rex_sql_index::FULLTEXT',
        ];

        $add = '';
        if (rex_sql_index::INDEX !== $index->getType()) {
            $add .= ', '.$types[$index->getType()];
        }

        return $this->sprintf(
            "\n        ->ensureIndex(new rex_sql_index(%s, %s$add))",
            $index->getName(),
            $index->getColumns(),
            $index->getType()
        );
    }

    private function addEnsureForeignKey(rex_sql_foreign_key $foreignKey): string
    {
        /** @var array<rex_sql_foreign_key::*, string> $modes */
        static $modes = [
            'NO ACTION' => 'rex_sql_foreign_key::RESTRICT',
            rex_sql_foreign_key::RESTRICT => 'rex_sql_foreign_key::RESTRICT',
            rex_sql_foreign_key::CASCADE => 'rex_sql_foreign_key::CASCADE',
            rex_sql_foreign_key::SET_NULL => 'rex_sql_foreign_key::SET_NULL',
        ];

        $add = $modes[$foreignKey->getOnUpdate()].', '.$modes[$foreignKey->getOnDelete()];

        return $this->sprintf(
            "\n        ->ensureForeignKey(new rex_sql_foreign_key(%s, %s, %s, $add))",
            $foreignKey->getName(),
            $foreignKey->getTable(),
            $foreignKey->getColumns()
        );
    }

    private function addDropTables(): string
    {
        $content = '';

        foreach ($this->drop as $tableName) {
            $content .= $this->sprintf("\n\n    rex_sql_table::get(%s)->drop();", $tableName);
        }

        return $content;
    }

    private function addEnsureViews(): string
    {
        $content = '';
        $sql = rex_sql::factory();

        foreach ($this->views as $viewName => $query) {
            $statement = 'CREATE OR REPLACE VIEW '.$sql->escapeIdentifier($viewName)." AS\n".$query;
            $content .= "\n\n    \$sql->setQuery(".$this->nowdoc($statement).');';
        }

        return $content;
    }

    private function addDropViews(): string
    {
        $content = '';
        $sql = rex_sql::factory();

        foreach ($this->dropViews as $viewName) {
            $statement = 'DROP VIEW IF EXISTS '.$sql->escapeIdentifier($viewName);
            $content .= "\n\n    \$sql->setQuery(".$this->nowdoc($statement).');';
        }

        return $content;
    }

    private function addFixtures(): string
    {
        $content = '';
        $sql = rex_sql::factory();

        foreach ($this->fixtures as $tableName => $changes) {
            if (isset($changes['ensure'])) {
                $rows = [];
                foreach ($changes['ensure'] as $data) {
                    $data = array_map(static function ($value) use ($sql) {
                        if (null === $value) {
                            return 'NULL';
                        }
                        if (is_int($value)) {
                            return $value;
                        }

                        return $sql->escape((string)$value);
                    }, $data);

                    $rows[] = '('.implode(', ', $data).')';
                }

                $columns = array_keys($changes['ensure'][0]);
                $primaryKey = rex_sql_table::get($tableName)->getPrimaryKey();

                $updates = [];
                foreach ($columns as $column) {
                    if (!in_array($column, $primaryKey)) {
                        $column = $sql->escapeIdentifier($column);
                        $updates[] = $column.' = VALUES('.$column.')';
                    }
                }

                $query = 'INSERT INTO '.$sql->escapeIdentifier($tableName);
                $query .= ' ('.implode(', ', array_map([$sql, 'escapeIdentifier'], $columns)).')';
                $query .= "\nVALUES\n    ";
                $query .= implode(",\n    ", $rows);
                if ($updates) {
                    $query .= "\nON DUPLICATE KEY UPDATE ".implode(', ', $updates);
                }

                $content .= "\n\n    \$sql->setQuery(".$this->nowdoc($query).');';
            }

            if (isset($changes['remove'])) {
                $where = [];
                foreach ($changes['remove'] as $key) {
                    $parts = [];
                    foreach ($key as $name => $value) {
                        $parts[] = $sql->escapeIdentifier($name).' = '.(is_int($value) ? $value : $sql->escape((string)$value));
                    }
                    $where[] = implode(' AND ', $parts);
                }

                $query = 'DELETE FROM '.$sql->escapeIdentifier($tableName);
                $query .= "\nWHERE\n    ";
                $query .= implode(" OR\n    ", $where);

                $content .= "\n\n    \$sql->setQuery(".$this->nowdoc($query).');';
            }
        }

        return $content;
    }

    private function sprintf(string $format, ...$args): string
    {
        return sprintf($format, ...array_map([$this, 'quote'], $args));
    }

    private function quote($var): string
    {
        if (null === $var) {
            return 'null';
        }

        if (!is_array($var)) {
            return var_export($var, true);
        }

        $i = 0;
        $elements = [];
        foreach ($var as $key => $value) {
            $value = $this->quote($value);

            if ($i === $key) {
                $elements[] = $value;
                ++$i;

                continue;
            }

            if (is_int($key) && $key > $i) {
                $i = $key + 1;
            }

            $elements[] = $this->quote($key).' => '.$value;
        }

        return '['.implode(', ', $elements).']';
    }

    private function nowdoc(string $var): string
    {
        $var = '        '.preg_replace('/(?<=\\n)(?=.)/', '        ', $var);

        return "<<<'SQL'\n$var\n        SQL";
    }
}
