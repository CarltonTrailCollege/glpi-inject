<?php

function plugin_glpiinject_install(): bool
{
    global $DB;

    $charset = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $key_sign = DBConnection::getDefaultPrimaryKeySignOption();
    $table = 'glpi_plugin_glpiinject_models';

    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `$table` (
            `id` int $key_sign NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `comment` text DEFAULT NULL,
            `itemtype` varchar(255) NOT NULL,
            `entities_id` int $key_sign NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0',
            `users_id` int $key_sign NOT NULL DEFAULT '0',
            `is_private` tinyint NOT NULL DEFAULT '1',
            `behavior_add` tinyint NOT NULL DEFAULT '1',
            `behavior_update` tinyint NOT NULL DEFAULT '0',
            `can_add_dropdown` tinyint NOT NULL DEFAULT '0',
            `delimiter` char(1) NOT NULL DEFAULT ';',
            `file_encoding` varchar(20) NOT NULL DEFAULT 'Automatic',
            `has_header` tinyint NOT NULL DEFAULT '1',
            `date_format` varchar(11) NOT NULL DEFAULT 'yyyy-mm-dd',
            `float_format` varchar(20) NOT NULL DEFAULT '1,234.56',
            `match_field` varchar(255) DEFAULT NULL,
            `mappings` text DEFAULT NULL,
            `csv_file` varchar(255) DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `itemtype` (`itemtype`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation ROW_FORMAT=DYNAMIC");
    } else {
        $migration = new Migration(PLUGIN_GLPIINJECT_VERSION);
        foreach ([
            'comment' => ['text', ['null' => true]],
            'is_private' => ['bool', ['value' => 1]],
            'behavior_add' => ['bool', ['value' => 1]],
            'behavior_update' => ['bool', ['value' => 0]],
            'can_add_dropdown' => ['bool', ['value' => 0]],
            'delimiter' => ['char(1)', ['value' => ';']],
            'file_encoding' => ['string', ['value' => 'Automatic']],
            'has_header' => ['bool', ['value' => 1]],
            'date_format' => ['string', ['value' => 'yyyy-mm-dd']],
            'float_format' => ['string', ['value' => '1,234.56']],
            'match_field' => ['string', ['null' => true]],
            'mappings' => ['text', ['null' => true]],
            'csv_file' => ['string', ['null' => true]],
        ] as $field => [$type, $options]) {
            if (!$DB->fieldExists($table, $field)) {
                $migration->addField($table, $field, $type, $options);
            }
        }
        $migration->executeMigration();
    }

    $runs_table = 'glpi_plugin_glpiinject_runs';
    if (!$DB->tableExists($runs_table)) {
        $DB->doQuery("CREATE TABLE `$runs_table` (
            `id` int $key_sign NOT NULL AUTO_INCREMENT,
            `models_id` int $key_sign NOT NULL,
            `users_id` int $key_sign NOT NULL,
            `entities_id` int $key_sign NOT NULL DEFAULT '0',
            `status` varchar(20) NOT NULL DEFAULT 'running',
            `total` int unsigned NOT NULL DEFAULT '0',
            `processed` int unsigned NOT NULL DEFAULT '0',
            `created` int unsigned NOT NULL DEFAULT '0',
            `updated` int unsigned NOT NULL DEFAULT '0',
            `failed` int unsigned NOT NULL DEFAULT '0',
            `csv_offset` int unsigned NOT NULL DEFAULT '0',
            `csv_line` int unsigned NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `models_id` (`models_id`),
            KEY `users_id` (`users_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation ROW_FORMAT=DYNAMIC");
    }
    $run_migration = new Migration(PLUGIN_GLPIINJECT_VERSION);
    foreach ([
        'csv_offset' => ['integer', ['value' => 0]],
        'csv_line' => ['integer', ['value' => 0]],
    ] as $field => [$type, $options]) {
        if (!$DB->fieldExists($runs_table, $field)) {
            $run_migration->addField($runs_table, $field, $type, $options);
        }
    }
    $run_migration->executeMigration();

    $logs_table = 'glpi_plugin_glpiinject_runlogs';
    if (!$DB->tableExists($logs_table)) {
        $DB->doQuery("CREATE TABLE `$logs_table` (
            `id` int $key_sign NOT NULL AUTO_INCREMENT,
            `runs_id` int $key_sign NOT NULL,
            `line` int unsigned NOT NULL,
            `status` varchar(20) NOT NULL,
            `message` text DEFAULT NULL,
            `itemtype` varchar(255) DEFAULT NULL,
            `items_id` int $key_sign NOT NULL DEFAULT '0',
            `source_row` text DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `runs_id` (`runs_id`),
            KEY `line` (`line`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation ROW_FORMAT=DYNAMIC");
    }

    return true;
}

function plugin_glpiinject_uninstall(): bool
{
    global $DB;

    foreach ([
        'glpi_plugin_glpiinject_runlogs',
        'glpi_plugin_glpiinject_runs',
        'glpi_plugin_glpiinject_models',
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    if (is_dir(PLUGIN_GLPIINJECT_UPLOAD_DIR)) {
        Toolbox::deleteDir(PLUGIN_GLPIINJECT_UPLOAD_DIR);
    }

    return true;
}