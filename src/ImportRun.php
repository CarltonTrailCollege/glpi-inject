<?php

namespace GlpiPlugin\Glpiinject;

use CommonDBTM;

final class ImportRun extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0): string
    {
        return _n('Import run', 'Import runs', $nb, 'glpiinject');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_glpiinject_runs';
    }
}