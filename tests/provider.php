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
            $this->fields = ['id' => 0, 'custom_serial' => '', 'assets_id' => 0];
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

    final class Budget extends CommonDBTM
    {
        public static function getTypeName(int $count = 0): string
        {
            return 'Budget';
        }
    }

    final class Supplier extends CommonDBTM
    {
        public static function getTypeName(int $count = 0): string
        {
            return 'Supplier';
        }
    }

    final class Infocom
    {
        public static function getTable(): string
        {
            return 'glpi_infocoms';
        }

        public static function getTypeName(int $count = 0): string
        {
            return 'Financial and administrative information';
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
            $options = [[
                    'id' => 1001,
                    'table' => 'glpi_assets_assets',
                    'field' => 'custom_serial',
                    'name' => 'Serial number',
                    'datatype' => 'string',
                ], [
                    'id' => 1002,
                    'table' => 'glpi_assets_assets',
                    'field' => 'custom_blank',
                    'name' => '   ',
                    'datatype' => 'string',
                ]];

            if ($itemtype === TestCustomAsset::class) {
                $options[] = [
                    'id' => 1005,
                    'table' => 'glpi_infocoms',
                    'field' => 'buy_date',
                    'name' => 'Date of purchase',
                    'datatype' => 'date',
                ];
                $options[] = [
                    'id' => 1003,
                    'table' => 'glpi_computers',
                    'field' => 'name',
                    'linkfield' => 'assets_id',
                    'itemtype' => Computer::class,
                    'name' => 'General',
                    'datatype' => 'dropdown',
                ];
                $options[] = [
                    'id' => 1004,
                    'table' => 'glpi_monitors',
                    'field' => 'name',
                    'linkfield' => 'assets_id',
                    'itemtype' => Monitor::class,
                    'name' => 'General',
                    'datatype' => 'dropdown',
                ];
            }

            return $options;
        }
    }

    final class TestCustomAsset extends CommonDBTM
    {
        public function canCreate(): bool
        {
            return false;
        }

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

    final class Monitor extends CommonDBTM
    {
        public static function getTypeName(int $count = 0): string
        {
            return 'Monitor';
        }
    }

    final class GenericDeviceOne extends CommonDBTM
    {
        public static function getTypeName(int $count = 0): string
        {
            return 'General';
        }
    }

    final class GenericDeviceTwo extends CommonDBTM
    {
        public static function getTypeName(int $count = 0): string
        {
            return 'General';
        }
    }

    final class GenericDevice extends CommonDBTM
    {
        public static function getTypeName(int $count = 0): string
        {
            return 'Generic Device';
        }
    }

    final class TestAssetDefinition
    {
        public array $fields = ['label' => 'Vehicle', 'system_name' => 'Vehicle', 'is_active' => 1];

        public function getTranslatedName(): string
        {
            return $this->fields['label'];
        }

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
    $CFG_GLPI['test_types'] = [GenericDeviceOne::class, GenericDeviceTwo::class, GenericDevice::class];
    $inactive_definition = new TestAssetDefinition();
    $inactive_definition->fields['label'] = 'Inactive Equipment';
    $inactive_definition->fields['is_active'] = 0;
    \Glpi\Asset\AssetDefinitionManager::$definitions = [new TestAssetDefinition(), $inactive_definition];
    $targets = (new \GlpiPlugin\Glpiinject\AssetTargetProvider())->getTargets();
    $target = $targets[TestCustomAsset::class];

    assert(isset($targets[Computer::class]));
    assert(!isset($targets[GenericDeviceOne::class]));
    assert(!isset($targets[GenericDeviceTwo::class]));
    assert($targets[GenericDevice::class]['label'] === 'Generic Device');
    assert($target['label'] === 'Vehicle');
    assert(!isset($targets['InactiveEquipment']));
    assert($target['mapping_options'][0]['itemtype_label'] === 'Vehicle');
    assert($target['mapping_options'][0]['field'] === 'custom_serial');
    assert($target['mapping_options'][0]['label'] === 'Vehicle -> Serial number');
    assert(count($target['mapping_options']) === 6);
    assert($target['mapping_options'][4]['itemtype'] === Budget::class);
    assert($target['mapping_options'][4]['linkfield'] === 'budgets_id');
    assert($target['mapping_options'][4]['is_infocom'] === true);
    assert($target['mapping_options'][5]['itemtype'] === Supplier::class);
    assert($target['mapping_options'][5]['linkfield'] === 'suppliers_id');
    assert($target['mapping_options'][5]['is_relation'] === true);
    assert($target['mapping_options'][1]['itemtype'] === Infocom::class);
    assert($target['mapping_options'][1]['label'] === 'Financial and administrative information -> Date of purchase');
    assert($target['mapping_options'][2]['label'] === 'Computer -> General');
    assert($target['mapping_options'][3]['label'] === 'Monitor -> General');

    $menu = \GlpiPlugin\Glpiinject\Menu::getMenuContent();
    assert($menu['title'] === 'GLPI Inject');
    assert($menu['options']['client']['title'] === 'Import CSV');
    assert($menu['options']['models']['title'] === 'Import models');
    $menu_tree = \GlpiPlugin\Glpiinject\Menu::addToMenu(['tools' => ['title' => 'Tools', 'content' => []]]);
    assert($menu_tree['tools']['title'] === 'Tools');
    assert($menu_tree['tools']['content']['glpiinject']['title'] === 'GLPI Inject');

    echo "Custom asset discovery and cache-independent GLPI Inject menu checks passed.\n";
}
