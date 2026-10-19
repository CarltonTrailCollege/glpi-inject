<?php

namespace GlpiPlugin\Glpiinject;

use CommonDBTM;

final class ImportModel extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0): string
    {
        return _n('Import model', 'Import models', $nb, 'glpiinject');
    }

    public static function getMenuName($nb = 0): string
    {
        return 'GLPI Inject';
    }

    public static function getMenuContent(): array
    {
        global $CFG_GLPI;

        $import_url = $CFG_GLPI['root_doc'] . '/plugins/glpiinject/front/index.php';
        $models_url = $CFG_GLPI['root_doc'] . '/plugins/glpiinject/front/models.php';

        return [
            'title' => self::getMenuName(),
            'page' => $import_url,
            'icon' => 'ti ti-file-import',
            'options' => [
                'client' => [
                    'title' => __('Import CSV', 'glpiinject'),
                    'page' => $import_url,
                ],
                'models' => [
                    'title' => self::getTypeName(2),
                    'page' => $models_url,
                    'links' => [
                        'search' => $models_url,
                        'add' => $models_url,
                    ],
                ],
            ],
        ];
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_glpiinject_models';
    }

    public function rawSearchOptions(): array
    {
        return [
            [
                'id' => 'common',
                'name' => __('Characteristics'),
            ],
            [
                'id' => 1,
                'table' => self::getTable(),
                'field' => 'name',
                'name' => __('Name'),
                'datatype' => 'itemlink',
            ],
            [
                'id' => 2,
                'table' => self::getTable(),
                'field' => 'id',
                'name' => __('ID'),
                'datatype' => 'number',
            ],
        ];
    }
}