<?php

use GlpiPlugin\Glpiinject\CsvExport;
use GlpiPlugin\Glpiinject\ImportModel;
use GlpiPlugin\Glpiinject\ImportRun;
use GlpiPlugin\Glpiinject\ImportRunLog;

include('../../../inc/includes.php');
require_once dirname(__DIR__) . '/src/CsvExport.php';

Session::checkRight('config', READ);

$run_id = (int) ($_GET['run'] ?? 0);
$run = new ImportRun();
if (!$run->getFromDB($run_id) || (int) $run->fields['users_id'] !== (int) Session::getLoginUserID() || !Session::haveAccessToEntity((int) $run->fields['entities_id'])) {
    Html::displayRightError();
}

$model = new ImportModel();
if (!$model->getFromDB((int) $run->fields['models_id'])
    || (int) $model->fields['users_id'] !== (int) Session::getLoginUserID()
    || (int) $model->fields['entities_id'] !== (int) $run->fields['entities_id']
    || !Session::haveAccessToEntity((int) $model->fields['entities_id'])
) {
    Html::displayNotFoundError();
}

global $DB;
$errors = [];
foreach ($DB->request([
    'FROM' => ImportRunLog::getTable(),
    'WHERE' => ['runs_id' => $run_id, 'status' => 'failed'],
    'ORDER' => 'line',
]) as $row) {
    $errors[] = [
        'line' => (int) $row['line'],
        'message' => (string) $row['message'],
        'row' => json_decode((string) $row['source_row'], true) ?: [],
    ];
}
if ($errors === []) {
    Html::displayNotFoundError();
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="glpiinject-errors-' . $run_id . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'wb');
fputcsv($output, [__('Line', 'glpiinject'), __('Error', 'glpiinject'), __('Original row', 'glpiinject')]);
foreach ($errors as $error) {
    fputcsv($output, array_map([CsvExport::class, 'sanitizeCell'], [
        (string) $error['line'],
        $error['message'],
        json_encode($error['row'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]));
}
fclose($output);
