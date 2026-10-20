<?php

require_once __DIR__ . '/src/AssetTargetProvider.php';
require_once __DIR__ . '/src/CsvImportService.php';
require_once __DIR__ . '/src/ImportModel.php';
require_once __DIR__ . '/src/ImportRun.php';
require_once __DIR__ . '/src/ImportRunLog.php';
require_once __DIR__ . '/src/Menu.php';

define('PLUGIN_GLPIINJECT_VERSION', '0.1.0-alpha.30');
define('PLUGIN_GLPIINJECT_MIN_GLPI', '11.0.8');
define('PLUGIN_GLPIINJECT_MAX_GLPI', '11.99.99');
if (!defined('PLUGIN_GLPIINJECT_UPLOAD_DIR')) {
    define('PLUGIN_GLPIINJECT_UPLOAD_DIR', GLPI_PLUGIN_DOC_DIR . '/glpiinject');
}

function plugin_init_glpiinject(): void
{
    global $PLUGIN_HOOKS;

    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
        'glpiinject',
        '#^/ajax/import_batch\.php$#',
        \Glpi\Http\Firewall::STRATEGY_CENTRAL_ACCESS
    );

    Plugin::registerClass(GlpiPlugin\Glpiinject\ImportModel::class);
    Plugin::registerClass(GlpiPlugin\Glpiinject\ImportRun::class);
    Plugin::registerClass(GlpiPlugin\Glpiinject\ImportRunLog::class);
    $PLUGIN_HOOKS['csrf_compliant']['glpiinject'] = true;
    $PLUGIN_HOOKS['config_page']['glpiinject'] = 'front/index.php';
    $PLUGIN_HOOKS['redefine_menus']['glpiinject'] = [GlpiPlugin\Glpiinject\Menu::class, 'addToMenu'];
}

function plugin_version_glpiinject(): array
{
    return [
        'name' => __('GLPI Inject', 'glpiinject'),
        'version' => PLUGIN_GLPIINJECT_VERSION,
        'author' => 'Carlton Trail College',
        'license' => 'GPL-2.0-or-later',
        'homepage' => 'https://github.com/CarltonTrailCollege/glpi-inject',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_GLPIINJECT_MIN_GLPI,
                'max' => PLUGIN_GLPIINJECT_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_glpiinject_check_prerequisites(): bool
{
    return version_compare(GLPI_VERSION, PLUGIN_GLPIINJECT_MIN_GLPI, '>=')
        && version_compare(GLPI_VERSION, PLUGIN_GLPIINJECT_MAX_GLPI, '<=');
}

function plugin_glpiinject_check_config(): bool
{
    return true;
}