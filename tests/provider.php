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

        public function canCreate(): bool
        {
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
        public static array $definitions = [];

        public static function getInstance(): self
        {
            return new self();
        }

        public function getDefinitions(): array
        {
            return self::$definitions;
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

    final class Computer extends CommonDBTM
    {
        public static function getTypeName(int $count = 0): string
        {
            return 'Computer';
        }
    }

    final class TestAssetDefinition
    {
        public array $fields = ['name' => 'Vehicle', 'is_active' => 1];

        public function getAssetClassName(): string
        {
            return TestCustomAsset::class;
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

    $CFG_GLPI['asset_types'] = [Computer::class];
    \Glpi\Asset\AssetDefinitionManager::$definitions = [new TestAssetDefinition()];
    $targets = (new \GlpiPlugin\Glpiinject\AssetTargetProvider())->getTargets();
    $target = $targets[TestCustomAsset::class];

    assert(isset($targets[Computer::class]));
    assert($target['label'] === 'Vehicle');
    assert($target['mapping_options'][0]['itemtype_label'] === 'Vehicle');
    assert($target['mapping_options'][0]['field'] === 'custom_serial');

    echo "Custom asset discovery handles generic Asset type labels safely.\n";
}
