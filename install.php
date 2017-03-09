<?php

rex_sql_table::get(rex::getTable('ydeploy_migration'))
    ->ensureColumn(new rex_sql_column('timestamp', 'varchar(26)'))
    ->setPrimaryKey('timestamp')
    ->ensure();
