<?php

declare(strict_types=1);

namespace {
    class CommonDBTM
    {
        public array $fields = [];

        public function getTable(): string
        {
            return 'glpi_assets_assets';
        }

        public function getEmpty(): bool
        {
            $this->fields = ['id' => 0, 'custom_serial' => ''];
            return true;
        }

        public static function getTypeName(int $count = 0): string
        {
            return 'Item';
        }
    }
}

namespace Glpi\Asset {
    final class Asset extends \CommonDBTM
    {
        public static function getTypeName(int $count = 0): string
        {
            throw new \Error('Typed static property definition_system_name is uninitialized');
        }
    }

    final class AssetDefinitionManager
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function getDefinitions(bool $only_active = false): array
        {
            return [];
        }
    }
}

namespace {
    final class Search
    {
        public static function getOptions(string $itemtype): array
        {
            return [[
                'id' => 1001,
                'table' => 'glpi_assets_assets',
                'field' => 'custom_serial',
                'name' => 'Serial number',
                'datatype' => 'string',
            ]];
        }
    }

    final class TestCustomAsset extends CommonDBTM
    {
        public static function getTypeName(int $count = 0): string
        {
            return 'Custom Equipment';
        }
    }

    function getItemTypeForTable(string $table): string
    {
        return \Glpi\Asset\Asset::class;
    }

    function getForeignKeyFieldForTable(string $table): string
    {
        return 'assets_id';
    }

    require_once dirname(__DIR__) . '/src/AssetTargetProvider.php';

    $CFG_GLPI['asset_types'] = [TestCustomAsset::class];
    $targets = (new \GlpiPlugin\Glpiinject\AssetTargetProvider())->getTargets();
    $target = $targets[TestCustomAsset::class];

    assert($target['label'] === 'Custom Equipment');
    assert($target['mapping_options'][0]['itemtype_label'] === 'Custom Equipment');
    assert($target['mapping_options'][0]['field'] === 'custom_serial');

    echo "Custom asset discovery handles generic Asset type labels safely.\n";
}
