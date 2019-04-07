<?php

/**
 * @internal
 */
final class rex_ydeploy_diff_file
{
    private $create = [];
    private $alter = [];
    private $drop = [];
    private $fixtures = [];

    public function createTable(rex_sql_table $table): void
    {
        $this->create[] = $table;
    }

    public function dropTable(string $tableName): void
    {
        $this->drop[] = $tableName;
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

    public function ensureFixture(string $tableName, array $data): void
    {
        $this->fixtures[$tableName]['ensure'][] = $data;
    }

    public function removeFixture(string $tableName, array $key): void
    {
        $this->fixtures[$tableName]['remove'][] = $key;
    }

    public function isEmpty(): bool
    {
        return !$this->create && !$this->alter && !$this->drop && !$this->fixtures;
    }

    public function getContent(): string
    {
        $changes = $this->addCreateTables();
        $changes .= $this->addAlterTables();
        $changes .= $this->addDropTables();
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

        /** @var rex_sql_table $table */
        foreach ($this->create as $table) {
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

            $content .= "\n        ->ensure();";
        }

        return $content;
    }

    private function addAlterTables(): string
    {
        $content = '';

        foreach ($this->alter as $tableName => $alter) {
            $content .= $this->sprintf("\n\n    rex_sql_table::get(%s)", $tableName);

            if (isset($alter['renameColumn'])) {
                foreach ($alter['renameColumn'] as $oldName => $newName) {
                    $content .= $this->addRenameColumn($oldName, $newName);
                }
            }

            if (isset($alter['ensureColumn'])) {
                foreach ($alter['ensureColumn'] as list($column, $after)) {
                    $content .= $this->addEnsureColumn($column, $after);
                }
            }

            if (isset($alter['removeColumn'])) {
                foreach ($alter['removeColumn'] as $columnName) {
                    $content .= $this->sprintf("\n        ->removeColumn(%s)", $columnName);
                }
            }

            if (isset($alter['primaryKey'])) {
                $content .= $this->addSetPrimaryKey($alter['primaryKey']);
            }

            if (isset($alter['ensureIndex'])) {
                foreach ($alter['ensureIndex'] as $index) {
                    $content .= $this->addEnsureIndex($index);
                }
            }

            if (isset($alter['removeIndex'])) {
                foreach ($alter['removeIndex'] as $indexName) {
                    $content .= $this->sprintf("\n        ->removeIndex(%s)", $indexName);
                }
            }

            $content .= "\n        ->alter();";
        }

        return $content;
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

    private function addDropTables(): string
    {
        $content = '';

        foreach ($this->drop as $tableName) {
            $content .= $this->sprintf("\n\n    rex_sql_table::get(%s)->drop();", $tableName);
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
                    $data = array_map(function ($value) use ($sql) {
                        if (is_int($value)) {
                            return $value;
                        }

                        return $sql->escape($value);
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

                $query = '        INSERT INTO '.$sql->escapeIdentifier($tableName);
                $query .= ' ('.implode(', ', array_map([$sql, 'escapeIdentifier'], $columns)).')';
                $query .= "\n        VALUES\n            ";
                $query .= implode(",\n            ", $rows);
                if ($updates) {
                    $query .= "\n        ON DUPLICATE KEY UPDATE ".implode(', ', $updates);
                }

                $content .= "\n\n    \$sql->setQuery(".$this->nowdoc($query).'    );';
            }

            if (isset($changes['remove'])) {
                $where = [];
                foreach ($changes['remove'] as $key) {
                    $parts = [];
                    foreach ($key as $name => $value) {
                        $parts[] = $sql->escapeIdentifier($name).' = '.$sql->escape($value);
                    }
                    $where[] = implode(' AND ', $parts);
                }

                $query = '        DELETE FROM '.$sql->escapeIdentifier($tableName);
                $query .= "\n        WHERE\n            ";
                $query .= implode(" OR\n            ", $where);

                $content .= "\n\n    \$sql->setQuery(".$this->nowdoc($query).'    );';
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
        return "<<<'SQL'\n$var\nSQL\n";
    }
}
