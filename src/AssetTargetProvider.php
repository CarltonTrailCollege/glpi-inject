<?php

namespace GlpiPlugin\Glpiinject;

use CommonDBTM;
use Glpi\Asset\AssetDefinitionManager;
use Search;

final class AssetTargetProvider
{
    /**
     * @return array<string, array{label: string, itemtype: class-string<CommonDBTM>, fields: array}>
     */
    public function getTargets(): array
    {
        $targets = [];
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        foreach ($CFG_GLPI['asset_types'] ?? [] as $itemtype) {
            if (!is_a($itemtype, CommonDBTM::class, true)) {
                continue;
            }

            $label = $this->getSafeTypeName($itemtype, $this->fallbackTypeName($itemtype));
            $targets[$itemtype] = [
                'label' => $label,
                'itemtype' => $itemtype,
                'fields' => $this->getFields($itemtype),
                'mapping_options' => $this->getMappingOptions($itemtype, $label),
            ];
        }

        $definitions = AssetDefinitionManager::getInstance()->getDefinitions(only_active: true);

        foreach ($definitions as $definition) {
            $itemtype = $definition->getAssetClassName();

            if (!is_a($itemtype, CommonDBTM::class, true)) {
                continue;
            }

            $targets[$itemtype] = [
                'label' => $definition->fields['name'],
                'itemtype' => $itemtype,
                'fields' => $this->getFields($itemtype),
                'mapping_options' => $this->getMappingOptions($itemtype, (string) $definition->fields['name']),
            ];
        }

        return $targets;
    }

    private function getFields(string $itemtype): array
    {
        $fields = [];
        $item = new $itemtype();

        foreach (Search::getOptions($itemtype) as $option) {
            if (
                !isset($option['field'], $option['name'])
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

    private function getMappingOptions(string $primary_itemtype, string $primary_label): array
    {
        $primary = new $primary_itemtype();
        $primary->getEmpty();
        $primary_table = $primary->getTable();
        $primary_fields = array_fill_keys(array_keys($primary->fields), true);
        $options = [];

        foreach (Search::getOptions($primary_itemtype) as $option) {
            if (!isset($option['table'], $option['field'], $option['name'])) {
                continue;
            }

            $table = (string) $option['table'];
            $destination_itemtype = getItemTypeForTable($table);
            if (!is_string($destination_itemtype) || !class_exists($destination_itemtype)) {
                continue;
            }

            $is_primary_field = $table === $primary_table;
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
                    : $this->getSafeTypeName($destination_itemtype, (string) $option['name']),
                'field' => (string) $option['field'],
                'linkfield' => $linkfield,
                'label' => (string) $option['name'],
                'table' => $table,
                'is_custom' => str_starts_with((string) $option['field'], 'custom_'),
                'is_relation' => !$is_primary_field,
                'datatype' => $option['datatype'] ?? 'string',
            ];
        }

        return $options;
    }

    private function getSafeTypeName(string $itemtype, string $fallback): string
    {
        try {
            if (is_a($itemtype, CommonDBTM::class, true) && method_exists($itemtype, 'getTypeName')) {
                return (string) $itemtype::getTypeName(1);
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }

    private function fallbackTypeName(string $itemtype): string
    {
        $parts = explode('\\', $itemtype);
        return end($parts) ?: $itemtype;
    }

    public function getTarget(string $itemtype): ?array
    {
        return $this->getTargets()[$itemtype] ?? null;
    }
}