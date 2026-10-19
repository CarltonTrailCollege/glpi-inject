<?php

use GlpiPlugin\Glpiinject\ImportModel;
use GlpiPlugin\Glpiinject\ImportRun;
use GlpiPlugin\Glpiinject\ImportRunLog;

include('../../../inc/includes.php');

Session::checkRight('config', READ);

$run_id = (int) ($_GET['run'] ?? 0);
$run = new ImportRun();
if (!$run->getFromDB($run_id) || (int) $run->fields['users_id'] !== (int) Session::getLoginUserID()) {
    Html::displayRightError();
}

$model = new ImportModel();
if (
    !$model->getFromDB((int) $run->fields['models_id'])
    || (int) $model->fields['users_id'] !== (int) Session::getLoginUserID()
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
    $original = array_map(static function ($value): string {
        $value = (string) $value;
        return preg_match('/^[\s]*[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }, $error['row']);
    fputcsv($output, [$error['line'], $error['message'], json_encode($original, JSON_UNESCAPED_UNICODE)]);
}
fclose($output);