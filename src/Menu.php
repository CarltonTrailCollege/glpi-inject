<?php

namespace GlpiPlugin\Glpiinject;

use CommonGLPI;

final class Menu extends CommonGLPI
{
   public static function addToMenu(array $menu): array {
       $menu['tools']['content']['glpiinject'] = self::getMenuContent();

       return $menu;
   }

   public static function getMenuName($nb = 0): string {
       return 'GLPI Inject';
   }

   public static function getMenuContent(): array {
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
                   'title' => ImportModel::getTypeName(2),
                   'page' => $models_url,
               ],
           ],
       ];
   }
}
