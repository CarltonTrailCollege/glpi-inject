<?php

declare(strict_types=1);

namespace {
    class CommonGLPI {}

    class CommonDBTM
    {
        public array $fields = [];

        public static function getTable($classname = null): string
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

        public function getDefinitions(bool $only_active = false): array
        {
            if (!$only_active) {
                return self::$definitions;
            }

            return array_filter(self::$definitions, static fn ($definition) => !empty($definition->fields['is_active']));
        }
    }
}

namespace {
    function __($message, $domain = null): string
    {
        return $message;
    }

    function _n($singular, $plural, $count): string
    {
        return $plural;
    }

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
    require_once dirname(__DIR__) . '/src/ImportModel.php';
    require_once dirname(__DIR__) . '/src/Menu.php';

    $CFG_GLPI['root_doc'] = '';
    $CFG_GLPI['asset_types'] = [Computer::class];
    $inactive_definition = new TestAssetDefinition();
    $inactive_definition->fields['name'] = 'Inactive Equipment';
    $inactive_definition->fields['is_active'] = 0;
    \Glpi\Asset\AssetDefinitionManager::$definitions = [new TestAssetDefinition(), $inactive_definition];
    $targets = (new \GlpiPlugin\Glpiinject\AssetTargetProvider())->getTargets();
    $target = $targets[TestCustomAsset::class];

    assert(isset($targets[Computer::class]));
    assert($target['label'] === 'Vehicle');
    assert($target['mapping_options'][0]['itemtype_label'] === 'Vehicle');
    assert($target['mapping_options'][0]['field'] === 'custom_serial');

    $menu = \GlpiPlugin\Glpiinject\Menu::getMenuContent();
    assert($menu['title'] === 'GLPI Inject');
    assert($menu['options']['client']['title'] === 'Import CSV');
    assert($menu['options']['models']['title'] === 'Import models');

    echo "Custom asset discovery and GLPI Inject menu registration checks passed.\n";
}
