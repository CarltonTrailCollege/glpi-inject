<?php

namespace GlpiPlugin\Glpiinject;

use CommonDBTM;
use Glpi\Asset\AssetDefinitionManager;
use Search;

final class AssetTargetProvider
{
   private const CORE_TARGETS = [
        'Appliance',
        'AutoUpdateSystem',
        'Budget',
        'CartridgeItem',
        'CartridgeItemType',
        'Certificate',
        'Computer',
        'ComputerModel',
        'ComputerType',
        'ConsumableItem',
        'ConsumableItemType',
        'Contact',
        'ContactType',
        'Contract',
        'ContractType',
        'Database',
        'DatabaseInstance',
        'Contact_Supplier',
        'Contract_Item',
        'Contract_Supplier',
        'DeviceCase',
        'DeviceCaseType',
        'DeviceControl',
        'DeviceDrive',
        'DeviceHardDrive',
        'DeviceMemory',
        'DeviceMemoryType',
        'DeviceMotherboard',
        'DeviceNetworkCard',
        'DeviceProcessor',
        'DocumentType',
        'Domain',
        'Entity',
        'Filesystem',
        'Group',
        'Group_User',
        'ITILCategory',
        'ITILFollowupTemplate',
        'InterfaceType',
        'KnowbaseItem',
        'Location',
        'Manufacturer',
        'Monitor',
        'MonitorModel',
        'MonitorType',
        'Network',
        'NetworkEquipment',
        'NetworkEquipmentModel',
        'NetworkEquipmentType',
        'NetworkInterface',
        'NetworkPort',
        'Networkport_Vlan',
        'NetworkName',
        'Notepad',
        'OperatingSystem',
        'OperatingSystemEdition',
        'OperatingSystemKernel',
        'OperatingSystemKernelVersion',
        'OperatingSystemServicePack',
        'OperatingSystemVersion',
        'Peripheral',
        'PeripheralModel',
        'PeripheralType',
        'Phone',
        'PhoneModel',
        'PhonePowerSupply',
        'PhoneType',
        'Printer',
        'PrinterModel',
        'PrinterType',
        'Profile',
        'Profile_User',
        'RequestType',
        'Software',
        'SoftwareLicense',
        'SoftwareLicenseType',
        'SoftwareVersion',
        'SolutionTemplate',
        'SolutionType',
        'Socket',
        'State',
        'Supplier',
        'SupplierType',
        'TaskCategory',
        'TaskTemplate',
        'User',
        'VirtualMachineState',
        'VirtualMachineSystem',
        'VirtualMachineType',
        'Vlan',
    ];

    /**
     * @return array<string, array{label: string, itemtype: class-string<CommonDBTM>, fields: array}>
     */
   public function getTargets(): array {
       $targets = [];
       /** @var array $CFG_GLPI */
       global $CFG_GLPI;

       $core_candidates = self::CORE_TARGETS;
      foreach ($CFG_GLPI as $config_key => $configured_types) {
         if (is_array($configured_types)
              && (str_ends_with((string) $config_key, '_types') || str_ends_with((string) $config_key, '_itemtypes'))
          ) {
            foreach ($configured_types as $configured_type) {
               if (is_string($configured_type)) {
                  $core_candidates[] = $configured_type;
               }
            }
         }
      }
       $core_itemtypes = array_unique(array_merge($core_candidates, $CFG_GLPI['asset_types'] ?? []));
      foreach ($core_itemtypes as $candidate) {
          $itemtype = is_string($candidate) && class_exists($candidate) ? $candidate : $candidate;
         if (!is_string($itemtype) || !is_a($itemtype, CommonDBTM::class, true)) {
             continue;
         }

         try {
             $item = new $itemtype();
            if (!$item->canCreate()) {
                continue;
            }
             $label = $this->getSafeTypeName($itemtype, $this->fallbackTypeName($itemtype));
            if (strcasecmp(trim($label), 'General') === 0) {
                continue;
            }
             $targets[$itemtype] = [
                 'label' => $label,
                 'itemtype' => $itemtype,
                 'fields' => $this->getFields($itemtype),
                 'mapping_options' => $this->getMappingOptions($itemtype, $label),
             ];
         } catch (\Throwable) {
             continue;
         }
      }

       $definitions = AssetDefinitionManager::getInstance()->getDefinitions(true);

      foreach ($definitions as $definition) {
          $itemtype = $definition->getAssetClassName();

         if (!is_a($itemtype, CommonDBTM::class, true)) {
             continue;
         }

         try {
             new $itemtype();
         } catch (\Throwable) {
             continue;
         }

          $targets[$itemtype] = [
              'label' => $this->getDefinitionLabel($definition),
              'itemtype' => $itemtype,
              'fields' => $this->getFields($itemtype),
              'mapping_options' => $this->getMappingOptions($itemtype, $this->getDefinitionLabel($definition)),
          ];
      }

       $label_counts = [];
      foreach ($targets as $target) {
          $label_key = strtolower(trim($target['label']));
          $label_counts[$label_key] = ($label_counts[$label_key] ?? 0) + 1;
      }

      foreach ($targets as $itemtype => &$target) {
          $label_key = strtolower(trim($target['label']));
         if (($label_counts[$label_key] ?? 0) > 1) {
             $target['label'] = sprintf('%s (%s)', $target['label'], $this->fallbackTypeName($itemtype));
         }
      }
       unset($target);

       uasort($targets, static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
       return $targets;
   }

   private function getFields(string $itemtype): array {
       $fields = [];
       $item = new $itemtype();

      foreach (Search::getOptions($itemtype) as $option) {
         if (!isset($option['field'], $option['name'])
              || trim((string) $option['name']) === ''
              || $option['field'] === 'id'
              || ($option['table'] ?? null) !== $item->getTable()
          ) {
            continue;
         }

          $fields[] = [
              'name' => $option['name'],
              'field' => $option['field'],
              'datatype' => $option['datatype'] ?? 'string',
              'is_custom' => str_starts_with($option['field'], 'custom_'),
          ];
      }

       return $fields;
   }

   private function getMappingOptions(string $primary_itemtype, string $primary_label): array {
       $primary = new $primary_itemtype();
       $primary->getEmpty();
       $primary_table = $primary->getTable();
       $primary_fields = array_fill_keys(array_keys($primary->fields), true);
       $options = [];

      foreach (Search::getOptions($primary_itemtype) as $option) {
         if (!isset($option['table'], $option['field'], $option['name'])
              || trim((string) $option['name']) === ''
          ) {
            continue;
         }

          $table = (string) $option['table'];
         if ($table === \Infocom::getTable()) {
             $infocom_label = \Infocom::getTypeName(1);
             $options[] = [
                 'itemtype' => \Infocom::class,
                 'itemtype_label' => $infocom_label,
                 'field' => (string) $option['field'],
                 'linkfield' => '__infocom',
                 'label' => sprintf('%s -> %s', $infocom_label, trim((string) $option['name'])),
                 'table' => $table,
                 'is_custom' => false,
                 'is_relation' => false,
                 'is_infocom' => true,
                 'datatype' => $option['datatype'] ?? 'string',
             ];
             continue;
         }

          $destination_itemtype = $option['itemtype'] ?? getItemTypeForTable($table);
         if (!is_string($destination_itemtype) || !class_exists($destination_itemtype)) {
             continue;
         }

          $is_primary_field = $table === $primary_table;
          $source_label = $is_primary_field
              ? $primary_label
              : $this->getSafeTypeName($destination_itemtype, $destination_itemtype);
          $linkfield = (string) ($option['linkfield'] ?? (
              $is_primary_field
                  ? $option['field']
                  : getForeignKeyFieldForTable($table)
          ));
         if ($linkfield === '' || !isset($primary_fields[$linkfield])) {
            continue;
         }

          $options[] = [
              'itemtype' => $is_primary_field ? $primary_itemtype : $destination_itemtype,
              'itemtype_label' => $is_primary_field
                  ? $primary_label
                  : $source_label,
              'field' => (string) $option['field'],
              'linkfield' => $linkfield,
              'label' => sprintf('%s -> %s', $source_label, trim((string) $option['name'])),
              'table' => $table,
              'is_custom' => str_starts_with((string) $option['field'], 'custom_'),
              'is_relation' => !$is_primary_field,
              'datatype' => $option['datatype'] ?? 'string',
          ];
      }

       return $options;
   }

   private function getDefinitionLabel(object $definition): string {
      try {
         if (method_exists($definition, 'getTranslatedName')) {
            $label = trim((string) $definition->getTranslatedName());
            if ($label !== '') {
                return $label;
            }
         }
      } catch (\Throwable) {
          error_log('GLPI Inject: unable to read a translated custom asset label.');
      }

       $fields = $definition->fields ?? [];
       return trim((string) ($fields['label'] ?? $fields['system_name'] ?? 'Custom asset'));
   }

   private function getSafeTypeName(string $itemtype, string $fallback): string {
      try {
         if (is_a($itemtype, CommonDBTM::class, true) && method_exists($itemtype, 'getTypeName')) {
            return (string) $itemtype::getTypeName(1);
         }
      } catch (\Throwable) {
          error_log('GLPI Inject: unable to read an asset type label.');
      }

       return $fallback;
   }

   private function fallbackTypeName(string $itemtype): string {
       $parts = explode('\\', $itemtype);
       return end($parts) ?: $itemtype;
   }

   public function getTarget(string $itemtype): ?array {
       return $this->getTargets()[$itemtype] ?? null;
   }
}
