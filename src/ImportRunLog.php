<?php

namespace GlpiPlugin\Glpiinject;

use CommonDBTM;

final class ImportRunLog extends CommonDBTM
{
   public static $rightname = 'config';

   public static function getTypeName($nb = 0): string {
       return _n('Import log entry', 'Import log entries', $nb, 'glpiinject');
   }

   public static function getTable($classname = null): string {
       return 'glpi_plugin_glpiinject_runlogs';
   }
}
