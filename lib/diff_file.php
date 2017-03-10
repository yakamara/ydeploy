<?php

/**
 * @internal
 */
class rex_ydeploy_diff_file
{
    private $create = [];
    private $alter = [];
    private $drop = [];

    public function createTable(rex_sql_table $table)
    {
        $this->create[] = $table;
    }

    public function dropTable($tableName)
    {
        $this->drop[] = $tableName;
    }

    public function ensureColumn($tableName, rex_sql_column $column)
    {
        $this->alter[$tableName]['ensure'][] = $column;
    }

    public function removeColumn($tableName, $columnName)
    {
        $this->alter[$tableName]['remove'][] = $columnName;
    }

    public function setPrimaryKey($tableName, array $primaryKey)
    {
        $this->alter[$tableName]['primaryKey'] = $primaryKey;
    }

    public function isEmpty()
    {
        return !$this->create && !$this->alter && !$this->drop;
    }

    public function getContent()
    {
        $changes = $this->addCreateTables();
        $changes .= $this->addAlterTables();
        $changes .= $this->addDropTables();
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

    private function addCreateTables()
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

            $content .= "\n        ->ensure();";
        }

        return $content;
    }

    private function addAlterTables()
    {
        $content = '';

        foreach ($this->alter as $tableName => $alter) {
            $content .= $this->sprintf("\n\n    rex_sql_table::get(%s)", $tableName);

            if (isset($alter['ensure'])) {
                foreach ($alter['ensure'] as $column) {
                    $content .= $this->addEnsureColumn($column);
                }
            }

            if (isset($alter['remove'])) {
                foreach ($alter['remove'] as $columnName) {
                    $content .= $this->sprintf("\n        ->removeColumn(%s)", $columnName);
                }
            }

            if (isset($alter['primaryKey'])) {
                $content .= $this->addSetPrimaryKey($alter['primaryKey']);
            }

            $content .= "\n        ->alter();";
        }

        return $content;
    }

    private function addEnsureColumn(rex_sql_column $column)
    {
        return $this->sprintf(
            "\n        ->ensureColumn(new rex_sql_column(%s, %s, %s, %s, %s))",
            $column->getName(),
            $column->getType(),
            $column->isNullable(),
            $column->getDefault(),
            $column->getExtra()
        );
    }

    private function addSetPrimaryKey(array $primaryKey)
    {
        return $this->sprintf("\n        ->setPrimaryKey(%s)", $primaryKey);
    }

    private function addDropTables()
    {
        $content = '';

        foreach ($this->drop as $tableName) {
            $content .= $this->sprintf("\n\n    rex_sql_table::get(%s)->drop();", $tableName);
        }

        return $content;
    }

    private function sprintf($format, ...$args)
    {
        return sprintf($format, ...array_map([$this, 'quote'], $args));
    }

    private function quote($var)
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
}
