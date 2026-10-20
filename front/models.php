<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Glpiinject\AssetTargetProvider;
use GlpiPlugin\Glpiinject\ImportModel;
use GlpiPlugin\Glpiinject\ImportRun;

include('../../../inc/includes.php');

Session::checkRight('config', READ);

global $DB, $CFG_GLPI;

$user_id = (int) Session::getLoginUserID();
$provider = new AssetTargetProvider();
$model_item = new ImportModel();
$error = null;
$edit_model = null;

if (isset($_GET['edit']) && $model_item->getFromDB((int) $_GET['edit'])) {
    if ((int) $model_item->fields['users_id'] !== $user_id) {
        Html::displayRightError();
    }
    $edit_model = $model_item->fields;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkRight('config', UPDATE);

    try {
        $action = (string) ($_POST['action'] ?? 'create');
        if ($action === 'delete') {
            $model_id = (int) ($_POST['id'] ?? 0);
            if (!$model_item->getFromDB($model_id) || (int) $model_item->fields['users_id'] !== $user_id) {
                Html::displayRightError();
            }
            $filename = (string) ($model_item->fields['csv_file'] ?? '');
            $model_item->delete(['id' => $model_id], true);
            if ($filename !== '' && basename($filename) === $filename) {
                $path = PLUGIN_GLPIINJECT_UPLOAD_DIR . '/' . $filename;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpiinject/front/models.php');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $model_id = (int) ($_POST['id'] ?? 0);
        $itemtype = (string) ($_POST['itemtype'] ?? '');
        if ($name === '' || ($model_id === 0 && $provider->getTarget($itemtype) === null)) {
            throw new RuntimeException(__('Enter a model name and choose an available asset type.', 'glpiinject'));
        }
        $delimiter = (string) ($_POST['delimiter'] ?? ',');
        $date_format = (string) ($_POST['date_format'] ?? 'yyyy-mm-dd');
        $float_format = (string) ($_POST['float_format'] ?? '1,234.56');
        if (!in_array($delimiter, [',', ';', "\t", '|'], true)) {
            throw new RuntimeException(__('Choose a supported CSV delimiter.', 'glpiinject'));
        }
        if (!in_array($date_format, ['yyyy-mm-dd', 'dd-mm-yyyy', 'mm-dd-yyyy'], true)) {
            throw new RuntimeException(__('Choose a supported date format.', 'glpiinject'));
        }
        if (!in_array($float_format, ['1,234.56', '1 234,56'], true)) {
            throw new RuntimeException(__('Choose a supported number format.', 'glpiinject'));
        }

        $model_values = [
            'name' => $name,
            'comment' => trim((string) ($_POST['comment'] ?? '')),
            'is_private' => isset($_POST['is_private']) ? 1 : 0,
            'behavior_add' => isset($_POST['behavior_add']) ? 1 : 0,
            'behavior_update' => isset($_POST['behavior_update']) ? 1 : 0,
            'can_add_dropdown' => isset($_POST['can_add_dropdown']) ? 1 : 0,
            'delimiter' => $delimiter,
            'has_header' => isset($_POST['has_header']) ? 1 : 0,
            'date_format' => $date_format,
            'float_format' => $float_format,
        ];

        if ($model_id > 0) {
            if (!$model_item->getFromDB($model_id) || (int) $model_item->fields['users_id'] !== $user_id) {
                Html::displayRightError();
            }
            $updated = $model_item->update(['id' => $model_id] + $model_values);
            $new_id = $updated ? $model_id : false;
        } else {
            $new_id = $model_item->add($model_values + [
                'itemtype' => $itemtype,
                'entities_id' => (int) Session::getActiveEntity(),
                'is_recursive' => isset($_POST['is_recursive']) ? 1 : 0,
                'users_id' => $user_id,
                'match_field' => 'name',
                'mappings' => '{}',
            ]);
        }

        if ($new_id === false) {
            throw new RuntimeException(__('The import model could not be created.', 'glpiinject'));
        }

        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpiinject/front/models.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$models = [];
foreach ($DB->request([
    'FROM' => ImportModel::getTable(),
    'WHERE' => [
        'users_id' => $user_id,
        'entities_id' => Session::getActiveEntity(),
    ],
    'ORDER' => 'name',
]) as $row) {
    $models[] = $row;
}

$runs = [];
foreach ($DB->request([
    'FROM' => ImportRun::getTable(),
    'WHERE' => [
        'users_id' => $user_id,
        'entities_id' => Session::getActiveEntity(),
    ],
    'ORDER' => ['date_creation DESC', 'id DESC'],
    'LIMIT' => 20,
]) as $row) {
    $runs[] = $row;
}

$runs = [];
foreach ($DB->request([
    'FROM' => ImportRun::getTable(),
    'WHERE' => ['users_id' => $user_id],
    'ORDER' => ['date_creation DESC', 'id DESC'],
    'LIMIT' => 20,
]) as $row) {
    $runs[] = $row;
}

Html::header(__('Import models', 'glpiinject'), $_SERVER['PHP_SELF'], 'tools', 'glpiinject');
TemplateRenderer::getInstance()->display('@glpiinject/model_management.html.twig', [
    'models' => $models,
    'runs' => $runs,
    'runs' => $runs,
    'targets' => $provider->getTargets(),
    'error' => $error,
    'edit_model' => $edit_model,
    'root_doc' => $CFG_GLPI['root_doc'],
]);
Html::footer();